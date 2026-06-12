"""Conversor PDF a Markdown — interfaz gráfica.

Permite encolar varios PDFs, los procesa en un hilo trabajador (el OCR corre
en paralelo dentro de cada conversión) y propone automáticamente la acción
para cada elemento visual (Incrustar / OCR / Descartar). La lógica de
conversión vive en `conversor.py`.
"""

import os
import queue
import threading
import traceback
import tkinter as tk
from tkinter import filedialog, messagebox, ttk

import conversor


class Aplicacion:

    def __init__(self):
        self.ventana = tk.Tk()
        self.ventana.title("Conversor PDF a MD (Cola + Recomendaciones + OCR)")
        self.ventana.geometry("720x560")
        self.ventana.configure(padx=15, pady=15)

        self.cola_trabajos = queue.Queue()
        self.cola_eventos = queue.Queue()
        self.trabajos_pendientes = 0

        self._construir_interfaz()

        self.hilo_trabajador = threading.Thread(target=self._trabajador, daemon=True)
        self.hilo_trabajador.start()
        self.ventana.after(100, self._procesar_eventos)

        if not conversor.TESSERACT_DISPONIBLE:
            self._registrar("Aviso: Tesseract no está disponible; el OCR quedará deshabilitado.")

    # --- Construcción de la interfaz ---

    def _construir_interfaz(self):
        marco_cola = ttk.LabelFrame(self.ventana, text="Cola de conversión", padding=8)
        marco_cola.pack(fill=tk.BOTH, expand=True)

        marco_lista = tk.Frame(marco_cola)
        marco_lista.pack(fill=tk.BOTH, expand=True)
        self.lista_pdfs = tk.Listbox(marco_lista, selectmode=tk.EXTENDED, height=7)
        scroll_lista = ttk.Scrollbar(marco_lista, orient=tk.VERTICAL,
                                     command=self.lista_pdfs.yview)
        self.lista_pdfs.configure(yscrollcommand=scroll_lista.set)
        self.lista_pdfs.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scroll_lista.pack(side=tk.RIGHT, fill=tk.Y)

        marco_botones = tk.Frame(marco_cola)
        marco_botones.pack(fill=tk.X, pady=(8, 0))
        ttk.Button(marco_botones, text="Agregar PDFs...",
                   command=self._agregar_pdfs).pack(side=tk.LEFT, padx=(0, 5))
        ttk.Button(marco_botones, text="Quitar selección",
                   command=self._quitar_seleccion).pack(side=tk.LEFT, padx=5)
        ttk.Button(marco_botones, text="Vaciar lista",
                   command=lambda: self.lista_pdfs.delete(0, tk.END)).pack(side=tk.LEFT, padx=5)

        marco_opciones = ttk.LabelFrame(self.ventana, text="Opciones", padding=8)
        marco_opciones.pack(fill=tk.X, pady=10)

        self.var_modo_imagenes = tk.StringVar(value="carpeta")
        tk.Label(marco_opciones, text="Imágenes incrustadas:").grid(row=0, column=0, sticky="w")
        ttk.Radiobutton(marco_opciones, text="Carpeta junto al .md (recomendado, archivo liviano)",
                        variable=self.var_modo_imagenes, value="carpeta").grid(row=0, column=1, sticky="w")
        ttk.Radiobutton(marco_opciones, text="Base64 dentro del .md (autocontenido, pesado)",
                        variable=self.var_modo_imagenes, value="base64").grid(row=1, column=1, sticky="w")

        self.var_automatico = tk.BooleanVar(value=False)
        ttk.Checkbutton(
            marco_opciones,
            text="Modo automático (aplicar recomendaciones sin revisar elemento por elemento)",
            variable=self.var_automatico,
        ).grid(row=2, column=0, columnspan=2, sticky="w", pady=(6, 0))

        tk.Label(marco_opciones, text="Idioma OCR:").grid(row=3, column=0, sticky="w", pady=(6, 0))
        self.var_idioma = tk.StringVar(value="spa")
        ttk.Entry(marco_opciones, textvariable=self.var_idioma, width=10).grid(
            row=3, column=1, sticky="w", pady=(6, 0))

        self.btn_procesar = tk.Button(
            self.ventana, text="Procesar cola", bg="#008CBA", fg="white",
            font=("Arial", 10, "bold"), command=self._procesar_cola,
        )
        self.btn_procesar.pack(pady=(0, 10), ipadx=10, ipady=4)

        self.barra_progreso = ttk.Progressbar(self.ventana, maximum=1.0)
        self.barra_progreso.pack(fill=tk.X)
        self.etiqueta_estado = tk.Label(self.ventana, text="Listo.", anchor="w")
        self.etiqueta_estado.pack(fill=tk.X, pady=(4, 8))

        marco_registro = ttk.LabelFrame(self.ventana, text="Registro", padding=4)
        marco_registro.pack(fill=tk.BOTH, expand=True)
        self.texto_registro = tk.Text(marco_registro, height=6, state=tk.DISABLED, wrap="word")
        scroll_registro = ttk.Scrollbar(marco_registro, orient=tk.VERTICAL,
                                        command=self.texto_registro.yview)
        self.texto_registro.configure(yscrollcommand=scroll_registro.set)
        self.texto_registro.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scroll_registro.pack(side=tk.RIGHT, fill=tk.Y)

    # --- Acciones de la ventana principal ---

    def _agregar_pdfs(self):
        rutas = filedialog.askopenfilenames(
            title="Seleccionar archivos PDF", filetypes=[("Archivos PDF", "*.pdf")])
        existentes = set(self.lista_pdfs.get(0, tk.END))
        for ruta in rutas:
            if ruta not in existentes:
                self.lista_pdfs.insert(tk.END, ruta)

    def _quitar_seleccion(self):
        for indice in reversed(self.lista_pdfs.curselection()):
            self.lista_pdfs.delete(indice)

    def _procesar_cola(self):
        rutas = list(self.lista_pdfs.get(0, tk.END))
        if not rutas:
            messagebox.showwarning("Advertencia", "Agregue al menos un archivo PDF a la cola.")
            return
        # Las opciones se capturan aquí: el hilo trabajador no debe tocar widgets.
        opciones = {
            "modo_imagenes": self.var_modo_imagenes.get(),
            "idioma": self.var_idioma.get().strip() or "spa",
        }
        automatico = self.var_automatico.get()
        self.btn_procesar.config(state=tk.DISABLED, text="Procesando...")
        self.trabajos_pendientes = len(rutas)
        for ruta_pdf in rutas:
            self.cola_trabajos.put({
                "ruta_pdf": ruta_pdf,
                "ruta_md": conversor.nombre_salida(ruta_pdf),
                "opciones": opciones,
                "automatico": automatico,
            })

    def _registrar(self, mensaje):
        self.texto_registro.config(state=tk.NORMAL)
        self.texto_registro.insert(tk.END, mensaje + "\n")
        self.texto_registro.see(tk.END)
        self.texto_registro.config(state=tk.DISABLED)

    # --- Hilo trabajador (sin acceso a widgets) ---

    def _trabajador(self):
        while True:
            trabajo = self.cola_trabajos.get()
            nombre = os.path.basename(trabajo["ruta_pdf"])
            try:
                def progreso(texto, fraccion, nombre=nombre):
                    self.cola_eventos.put(("estado", f"{nombre}: {texto}", fraccion))

                elementos = conversor.analizar_pdf(
                    trabajo["ruta_pdf"], idioma=trabajo["opciones"]["idioma"],
                    progreso=progreso)

                if trabajo["automatico"]:
                    for elemento in elementos:
                        elemento["accion"] = elemento["recomendacion"]
                    seleccionados = [e for e in elementos
                                     if e["accion"] != conversor.ACCION_DESCARTAR]
                else:
                    respuesta = queue.Queue()
                    self.cola_eventos.put(("revision", trabajo, elementos, respuesta))
                    seleccionados = respuesta.get()
                    if seleccionados is None:
                        self.cola_eventos.put(("omitido", trabajo["ruta_pdf"]))
                        continue

                conversor.generar_markdown(
                    trabajo["ruta_pdf"], trabajo["ruta_md"], seleccionados,
                    trabajo["opciones"], progreso=progreso)
                self.cola_eventos.put(("hecho", trabajo["ruta_md"]))
            except Exception:
                self.cola_eventos.put(("error", trabajo["ruta_pdf"], traceback.format_exc(limit=3)))
            finally:
                self.cola_trabajos.task_done()

    # --- Eventos del trabajador hacia la interfaz ---

    def _procesar_eventos(self):
        while True:
            try:
                evento = self.cola_eventos.get_nowait()
            except queue.Empty:
                break
            tipo, *datos = evento
            if tipo == "estado":
                texto, fraccion = datos
                self.etiqueta_estado.config(text=texto)
                self.barra_progreso["value"] = fraccion
            elif tipo == "revision":
                trabajo, elementos, respuesta = datos
                self._ventana_revision(trabajo, elementos, respuesta)
            elif tipo == "hecho":
                self._registrar(f"✔ Generado: {datos[0]}")
                self._trabajo_terminado()
            elif tipo == "omitido":
                self._registrar(f"– Omitido por el usuario: {datos[0]}")
                self._trabajo_terminado()
            elif tipo == "error":
                ruta, detalle = datos
                self._registrar(f"✘ Error en {ruta}:\n{detalle}")
                self._trabajo_terminado()
        self.ventana.after(100, self._procesar_eventos)

    def _trabajo_terminado(self):
        self.trabajos_pendientes -= 1
        if self.trabajos_pendientes <= 0:
            self.barra_progreso["value"] = 0
            self.etiqueta_estado.config(text="Listo.")
            self.btn_procesar.config(state=tk.NORMAL, text="Procesar cola")
            messagebox.showinfo("Cola finalizada", "Se procesaron todos los archivos de la cola.")

    # --- Ventana de revisión de elementos ---

    def _ventana_revision(self, trabajo, elementos, respuesta):
        nombre = os.path.basename(trabajo["ruta_pdf"])
        if not elementos:
            if messagebox.askyesno(
                    "Sin elementos visuales",
                    f"No se detectaron imágenes ni gráficos en {nombre}.\n\n"
                    "¿Desea generar el archivo Markdown de todas formas?"):
                respuesta.put([])
            else:
                respuesta.put(None)
            return

        ventana_previa = tk.Toplevel(self.ventana)
        ventana_previa.title(f"Elementos visuales — {nombre}")
        ventana_previa.geometry("700x650")
        ventana_previa.configure(padx=10, pady=10)
        ventana_previa.referencias_imagenes = []

        tk.Label(ventana_previa,
                 text="Cada elemento trae la acción recomendada; ajústela si lo necesita:",
                 font=("Arial", 9, "bold")).pack(pady=(0, 5))

        variables_seleccion = []

        marco_masivo = tk.Frame(ventana_previa)
        marco_masivo.pack(fill=tk.X, pady=5)

        def aplicar_masivo(accion):
            for variable, elemento in variables_seleccion:
                variable.set(elemento["recomendacion"] if accion is None else accion)

        tk.Button(marco_masivo, text="Usar recomendaciones", bg="#dcedc8",
                  command=lambda: aplicar_masivo(None)).pack(side=tk.LEFT, padx=(10, 5))
        for accion in (conversor.ACCION_INCRUSTAR, conversor.ACCION_OCR, conversor.ACCION_DESCARTAR):
            tk.Button(marco_masivo, text=f"{accion} todos", bg="#e0e0e0",
                      command=lambda a=accion: aplicar_masivo(a)).pack(side=tk.LEFT, padx=5)

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

        for elemento in elementos:
            fila = tk.Frame(marco_scroll, bg="white", bd=1, relief=tk.SOLID)
            fila.pack(fill=tk.X, padx=5, pady=5, ipadx=5, ipady=5)

            var_accion = tk.StringVar(value=elemento["recomendacion"])
            variables_seleccion.append((var_accion, elemento))
            ttk.Combobox(fila, textvariable=var_accion, state="readonly", width=12,
                         values=[conversor.ACCION_INCRUSTAR, conversor.ACCION_OCR,
                                 conversor.ACCION_DESCARTAR]).pack(side=tk.LEFT, padx=10)

            info = (f"Página: {elemento['pagina'] + 1}   |   {elemento['tipo']}\n"
                    f"Recomendación: {elemento['recomendacion']} — {elemento['razon']}")
            tk.Label(fila, text=info, bg="white", justify=tk.LEFT,
                     wraplength=320).pack(side=tk.LEFT, padx=10)

            try:
                miniatura = tk.PhotoImage(data=elemento["thumb_png"])
                ventana_previa.referencias_imagenes.append(miniatura)
                tk.Label(fila, image=miniatura, bg="white").pack(side=tk.RIGHT, padx=10)
            except Exception:
                tk.Label(fila, text="[Miniatura no disponible]",
                         bg="white").pack(side=tk.RIGHT, padx=10)

        def confirmar():
            seleccionados = []
            for variable, elemento in variables_seleccion:
                accion = variable.get()
                if accion != conversor.ACCION_DESCARTAR:
                    elemento["accion"] = accion
                    seleccionados.append(elemento)
            ventana_previa.destroy()
            respuesta.put(seleccionados)

        def omitir():
            ventana_previa.destroy()
            respuesta.put(None)

        tk.Button(ventana_previa, text="Confirmar y generar archivo", bg="#4CAF50", fg="white",
                  font=("Arial", 10, "bold"), command=confirmar).pack(pady=15, ipadx=10, ipady=5)
        ventana_previa.protocol("WM_DELETE_WINDOW", omitir)

    def ejecutar(self):
        self.ventana.mainloop()


if __name__ == "__main__":
    Aplicacion().ejecutar()
