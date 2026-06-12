import fitz  # PyMuPDF

import pdfplumber

import os

import base64

import tkinter as tk

from tkinter import filedialog, messagebox, ttk

import io

from PIL import Image

import pytesseract



# Configuración estricta de la ruta del ejecutable de Tesseract en Windows.

pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'



# --- Lógica de Procesamiento y Compresión ---



def reducir_y_codificar(pix):

    """

    Optimiza la imagen limitando dimensiones, eliminando metadatos y canales ocultos.

    Preserva calidad suficiente para la posterior lectura mediante OCR.

    """

    img = Image.open(io.BytesIO(pix.tobytes("png")))

    

    if img.mode in ("RGBA", "P", "LA"):

        fondo = Image.new("RGB", img.size, (255, 255, 255))

        if len(img.split()) == 4:

            fondo.paste(img, mask=img.split()[3])

        else:

            fondo.paste(img)

        img = fondo

    elif img.mode != "RGB":

        img = img.convert("RGB")

        

    max_dim = 1536

    if img.width > max_dim or img.height > max_dim:

        img.thumbnail((max_dim, max_dim), Image.Resampling.LANCZOS)

        

    buffer = io.BytesIO()

    img.save(buffer, format="JPEG", quality=85)

    

    return base64.b64encode(buffer.getvalue()).decode("utf-8")





def convertir_tabla_a_md(tabla):

    """Convierte listas de pdfplumber a sintaxis de tabla Markdown."""

    if not tabla or not tabla[0]:

        return ""

    

    tabla_limpia = []

    for fila in tabla:

        fila_limpia = [str(celda).replace('\n', ' ').strip() if celda else "" for celda in fila]

        tabla_limpia.append(fila_limpia)

        

    num_cols = len(tabla_limpia[0])

    cols_validas = []

    for col_idx in range(num_cols):

        if any(fila[col_idx] != "" for fila in tabla_limpia):

            cols_validas.append(col_idx)

            

    if not cols_validas:

        return ""

        

    tabla_filtrada = [[fila[i] for i in cols_validas] for fila in tabla_limpia]

    encabezados = tabla_filtrada[0]

    encabezados = [enc if enc != "" else " " for enc in encabezados]

    

    md_tabla = "\n| " + " | ".join(encabezados) + " |\n"

    md_tabla += "| " + " | ".join(["---"] * len(encabezados)) + " |\n"

    

    for fila in tabla_filtrada[1:]:

        md_tabla += "| " + " | ".join(fila) + " |\n"

        

    return md_tabla + "\n"





def generar_markdown_final(ruta_pdf, ruta_md, elementos_configurados, ventana_previa):

    """Ejecuta la conversión combinando el texto nativo con las acciones visuales seleccionadas."""

    if ventana_previa:

        ventana_previa.destroy()

        

    btn_analizar.config(state=tk.DISABLED, text="Generando Archivo...")

    ventana.update()



    try:

        contenido_md = []

        doc_plumber = pdfplumber.open(ruta_pdf)

        num_paginas = len(doc_plumber.pages)



        for num_pagina in range(num_paginas):

            contenido_md.append(f"\n\n\n")

            

            # 1. Extracción de tablas y texto nativo

            pagina_plumber = doc_plumber.pages[num_pagina]

            tablas = pagina_plumber.extract_tables()

            if tablas:

                contenido_md.append(f"**Tablas detectadas:**\n")

                for tabla in tablas:

                    md_tabla = convertir_tabla_a_md(tabla)

                    contenido_md.append(md_tabla)

            

            texto = pagina_plumber.extract_text(layout=True)

            if texto:

                texto_limpio = "\n".join([linea.strip() for linea in texto.split('\n') if linea.strip()])

                contenido_md.append(texto_limpio + "\n\n")



            # 2. Procesamiento de elementos visuales según la acción elegida

            imgs_pagina = [img for img in elementos_configurados if img["pagina"] == num_pagina]

            for img in imgs_pagina:

                if img["accion"] == "Incrustar":

                    contenido_md.append(f"![Elemento_Visual_Pag_{num_pagina + 1}](data:image/jpeg;base64,{img['base64_full']})\n\n")

                

                elif img["accion"] == "OCR":

                    try:

                        # Se decodifica la imagen optimizada para pasarla al motor Tesseract

                        img_data = base64.b64decode(img['base64_full'])

                        img_pil = Image.open(io.BytesIO(img_data))

                        texto_ocr = pytesseract.image_to_string(img_pil, lang='spa')

                        

                        if texto_ocr.strip():

                            contenido_md.append(f"**Texto extraído (OCR):**\n{texto_ocr.strip()}\n\n")

                    except Exception as e:

                        contenido_md.append(f"*(Error al aplicar OCR en este elemento: {str(e)})*\n\n")



        doc_plumber.close()



        with open(ruta_md, "w", encoding="utf-8") as archivo_salida:

            archivo_salida.write("".join(contenido_md))

            

        messagebox.showinfo("Conversión Exitosa", f"Archivo generado correctamente en:\n{ruta_md}\n\nOperación finalizada.")



    except Exception as e:

        messagebox.showerror("Error de Ejecución", f"Se produjo un error durante la generación:\n\n{e}")

    finally:

        btn_analizar.config(state=tk.NORMAL, text="Analizar PDF y Seleccionar Elementos")





def escanear_imagenes(ruta_pdf):

    """Escanea el PDF para extraer imágenes y vectores procesándolos con limpieza profunda."""

    btn_analizar.config(state=tk.DISABLED, text="Escaneando...")

    ventana.update()

    

    elementos_visuales = []

    try:

        doc_fitz = fitz.open(ruta_pdf)

        for num_pagina in range(len(doc_fitz)):

            pagina_fitz = doc_fitz[num_pagina]

            

            bloques = pagina_fitz.get_text("dict")["blocks"]

            for idx, bloque in enumerate(bloques):

                if bloque["type"] == 1:

                    try:

                        pix_full = fitz.Pixmap(bloque["image"])

                        if pix_full.n >= 4:

                            pix_full = fitz.Pixmap(fitz.csRGB, pix_full)

                            

                        escala = 200.0 / pix_full.width if pix_full.width > 200 else 1.0

                        if escala < 1.0:

                            new_w = max(1, int(pix_full.width * escala))

                            new_h = max(1, int(pix_full.height * escala))

                            pix_thumb = fitz.Pixmap(pix_full, new_w, new_h, None)

                        else:

                            pix_thumb = pix_full

                            

                        base64_full = reducir_y_codificar(pix_full)

                        base64_thumb = base64.b64encode(pix_thumb.tobytes("png")).decode("utf-8")

                        

                        elementos_visuales.append({

                            "pagina": num_pagina,

                            "tipo": "Imagen Incrustada",

                            "base64_full": base64_full,

                            "base64_thumb": base64_thumb

                        })

                    except Exception:

                        continue 



            dibujos = pagina_fitz.get_drawings()

            rectangulos_dibujos = [d["rect"] for d in dibujos if not d["rect"].is_empty]

            

            cambio = True

            while cambio:

                cambio = False

                nuevos_rects = []

                while rectangulos_dibujos:

                    r1 = rectangulos_dibujos.pop(0)

                    fusionado = False

                    for i, r2 in enumerate(nuevos_rects):

                        r1_exp = fitz.Rect(r1.x0 - 15, r1.y0 - 15, r1.x1 + 15, r1.y1 + 15)

                        if r1_exp.intersects(r2):

                            nuevos_rects[i] |= r1

                            fusionado = True

                            cambio = True

                            break

                    if not fusionado:

                        nuevos_rects.append(r1)

                rectangulos_dibujos = nuevos_rects

                

            for rect in rectangulos_dibujos:

                if rect.width > 30 and rect.height > 30:

                    pix_full = pagina_fitz.get_pixmap(clip=rect, dpi=150)

                    escala = 200.0 / pix_full.width if pix_full.width > 200 else 1.0

                    dpi_thumb = max(15, int(150 * escala))

                    pix_thumb = pagina_fitz.get_pixmap(clip=rect, dpi=dpi_thumb)

                    

                    base64_full = reducir_y_codificar(pix_full)

                    base64_thumb = base64.b64encode(pix_thumb.tobytes("png")).decode("utf-8")

                    

                    elementos_visuales.append({

                        "pagina": num_pagina,

                        "tipo": "Gráfico Vectorial",

                        "base64_full": base64_full,

                        "base64_thumb": base64_thumb

                    })

                    

        doc_fitz.close()

        mostrar_ventana_previsualizacion(elementos_visuales)

        

    except Exception as e:

        messagebox.showerror("Error de Análisis", f"Se produjo un error crítico al escanear el PDF:\n\n{e}")

        btn_analizar.config(state=tk.NORMAL, text="Analizar PDF y Seleccionar Elementos")





# --- Interfaz Gráfica (Previsualización y Selección) ---



def mostrar_ventana_previsualizacion(elementos_visuales):

    """Crea ventana interactiva con menú desplegable (Combobox) de tres estados."""

    if not elementos_visuales:

        respuesta = messagebox.askyesno(

            "Información", 

            "No se detectaron imágenes ni gráficos vectoriales.\n\n¿Desea generar el archivo Markdown de todas formas?"

        )

        if respuesta:

            generar_markdown_final(var_entrada.get(), var_salida.get(), [], None)

        else:

            btn_analizar.config(state=tk.NORMAL, text="Analizar PDF y Seleccionar Elementos")

        return



    ventana_previa = tk.Toplevel(ventana)

    ventana_previa.title("Selección y Acción sobre Elementos Visuales")

    ventana_previa.geometry("650x650")

    ventana_previa.configure(padx=10, pady=10)

    

    tk.Label(ventana_previa, text="Determine la acción a ejecutar para cada gráfico detectado:", font=("Arial", 9, "bold")).pack(pady=(0, 5))



    variables_seleccion = []

    

    # --- Panel de Selección Masiva (Tres Botones) ---

    marco_botones_seleccion = tk.Frame(ventana_previa)

    marco_botones_seleccion.pack(fill=tk.X, pady=5)

    

    def aplicar_masivo(accion_requerida):

        for var, _ in variables_seleccion:

            var.set(accion_requerida)

            

    tk.Button(marco_botones_seleccion, text="Incrustar Todos", command=lambda: aplicar_masivo("Incrustar"), bg="#e0e0e0").pack(side=tk.LEFT, padx=(10, 5))

    tk.Button(marco_botones_seleccion, text="Aplicar OCR a Todos", command=lambda: aplicar_masivo("OCR"), bg="#e0e0e0").pack(side=tk.LEFT, padx=5)

    tk.Button(marco_botones_seleccion, text="Descartar Todos", command=lambda: aplicar_masivo("Descartar"), bg="#e0e0e0").pack(side=tk.LEFT, padx=5)

    # ------------------------------------------------



    marco_principal = tk.Frame(ventana_previa)

    marco_principal.pack(fill=tk.BOTH, expand=True, pady=10)



    canvas = tk.Canvas(marco_principal, bg="#f0f0f0")

    scrollbar = ttk.Scrollbar(marco_principal, orient=tk.VERTICAL, command=canvas.yview)

    marco_scroll = tk.Frame(canvas, bg="#f0f0f0")



    marco_scroll.bind("<Configure>", lambda e: canvas.configure(scrollregion=canvas.bbox("all")))

    canvas.create_window((0, 0), window=marco_scroll, anchor="nw")

    canvas.configure(yscrollcommand=scrollbar.set)



    canvas.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)

    scrollbar.pack(side=tk.RIGHT, fill=tk.Y)



    ventana_previa.referencias_imagenes = []  



    for idx, elemento in enumerate(elementos_visuales):

        fila = tk.Frame(marco_scroll, bg="white", bd=1, relief=tk.SOLID)

        fila.pack(fill=tk.X, padx=5, pady=5, ipadx=5, ipady=5)

        

        # Implementación del Combobox de acciones

        var_accion = tk.StringVar(value="Incrustar")

        variables_seleccion.append((var_accion, elemento))

        

        opciones = ["Incrustar", "OCR", "Descartar"]

        combo_accion = ttk.Combobox(fila, textvariable=var_accion, values=opciones, state="readonly", width=12)

        combo_accion.pack(side=tk.LEFT, padx=10)

        

        info_texto = f"Página: {elemento['pagina'] + 1}\nTipo: {elemento['tipo']}"

        tk.Label(fila, text=info_texto, bg="white", justify=tk.LEFT).pack(side=tk.LEFT, padx=10)

        

        try:

            img_tk = tk.PhotoImage(data=base64.b64decode(elemento["base64_thumb"]))

            ventana_previa.referencias_imagenes.append(img_tk)

            tk.Label(fila, image=img_tk, bg="white").pack(side=tk.RIGHT, padx=10)

        except Exception:

            tk.Label(fila, text="[Miniatura no disponible]", bg="white").pack(side=tk.RIGHT, padx=10)



    def confirmar_seleccion():

        elementos_configurados = []

        for var, elem in variables_seleccion:

            accion_seleccionada = var.get()

            if accion_seleccionada != "Descartar":

                elem["accion"] = accion_seleccionada

                elementos_configurados.append(elem)

                

        generar_markdown_final(var_entrada.get(), var_salida.get(), elementos_configurados, ventana_previa)



    tk.Button(ventana_previa, text="Confirmar y Generar Archivo", bg="#4CAF50", fg="white", font=("Arial", 10, "bold"), command=confirmar_seleccion).pack(pady=15, ipadx=10, ipady=5)



    def al_cerrar():

        btn_analizar.config(state=tk.NORMAL, text="Analizar PDF y Seleccionar Elementos")

        ventana_previa.destroy()

        

    ventana_previa.protocol("WM_DELETE_WINDOW", al_cerrar)





# --- Validaciones y Ventana Principal ---



def iniciar_proceso():

    ruta_pdf = var_entrada.get()

    ruta_md = var_salida.get()

    

    if not ruta_pdf or not ruta_md:

        messagebox.showwarning("Advertencia", "Se deben completar ambas rutas.")

        return

        

    escanear_imagenes(ruta_pdf)



def seleccionar_archivo_entrada():

    ruta_pdf = filedialog.askopenfilename(title="Seleccionar archivo PDF", filetypes=[("Archivos PDF", "*.pdf")])

    if ruta_pdf:

        var_entrada.set(ruta_pdf)

        ruta_base, _ = os.path.splitext(ruta_pdf)

        var_salida.set(f"{ruta_base}.md")



def seleccionar_archivo_salida():

    ruta_md = filedialog.asksaveasfilename(title="Guardar como", defaultextension=".md", filetypes=[("Archivos Markdown", "*.md")])

    if ruta_md:

        var_salida.set(ruta_md)





ventana = tk.Tk()

ventana.title("Conversor PDF a MD (Análisis Granular + OCR)")

ventana.geometry("600x200")

ventana.configure(padx=20, pady=20)



var_entrada = tk.StringVar()

var_salida = tk.StringVar()



tk.Label(ventana, text="Archivo PDF Original:").grid(row=0, column=0, sticky="w", pady=5)

tk.Entry(ventana, textvariable=var_entrada, width=50, state="readonly").grid(row=0, column=1, padx=10, pady=5)

tk.Button(ventana, text="Examinar...", width=12, command=seleccionar_archivo_entrada).grid(row=0, column=2, pady=5)



tk.Label(ventana, text="Guardar salida como:").grid(row=1, column=0, sticky="w", pady=5)

tk.Entry(ventana, textvariable=var_salida, width=50).grid(row=1, column=1, padx=10, pady=5)

tk.Button(ventana, text="Examinar...", width=12, command=seleccionar_archivo_salida).grid(row=1, column=2, pady=5)



btn_analizar = tk.Button(ventana, text="Analizar PDF y Seleccionar Elementos", bg="#008CBA", fg="white", font=("Arial", 10, "bold"), command=iniciar_proceso)

btn_analizar.grid(row=2, column=0, columnspan=3, pady=25, ipadx=10, ipady=5)



if __name__ == "__main__":

    ventana.mainloop()
