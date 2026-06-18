"""Lógica de conversión de PDF a Markdown.

Módulo independiente de la interfaz gráfica. También puede usarse por línea
de comandos (modo automático, aplica las recomendaciones sin preguntar):

    python conversor.py documento.pdf [otro.pdf ...] [--imagenes carpeta|base64] [--idioma spa]
"""

import argparse
import base64
import hashlib
import io
import os
import shutil
import statistics
from concurrent.futures import ThreadPoolExecutor, as_completed
from urllib.parse import quote

import fitz  # PyMuPDF
from PIL import Image

try:
    import pytesseract
    from pytesseract import Output
except ImportError:
    pytesseract = None

# --- Configuración general ---

MAX_DIM_IMAGEN = 1536       # Lado máximo de las imágenes conservadas
CALIDAD_WEBP = 80
ANCHO_MINIATURA = 200       # Para la previsualización en la GUI
LADO_MINIMO_ELEMENTO = 40   # Por debajo se recomienda descartar
REPETICIONES_DESCARTE = 3   # Imagen repetida en N páginas => probable logo
HILOS_OCR = min(8, max(2, (os.cpu_count() or 4)))

ACCION_INCRUSTAR = "Incrustar"
ACCION_OCR = "OCR"
ACCION_DESCARTAR = "Descartar"


def configurar_tesseract():
    """Localiza el ejecutable de Tesseract (PATH o ruta típica de Windows)."""
    if pytesseract is None:
        return False
    ruta = shutil.which("tesseract")
    if not ruta:
        ruta_windows = r"C:\Program Files\Tesseract-OCR\tesseract.exe"
        if os.path.exists(ruta_windows):
            ruta = ruta_windows
    if not ruta:
        return False
    pytesseract.pytesseract.tesseract_cmd = ruta
    return True


TESSERACT_DISPONIBLE = configurar_tesseract()


# --- Utilidades de imagen ---

def _aplanar_rgb(img):
    """Elimina transparencia y canales ocultos dejando la imagen en RGB."""
    if img.mode in ("RGBA", "P", "LA"):
        img = img.convert("RGBA")
        fondo = Image.new("RGB", img.size, (255, 255, 255))
        fondo.paste(img, mask=img.split()[3])
        return fondo
    if img.mode != "RGB":
        return img.convert("RGB")
    return img


def optimizar_imagen(img, max_dim=MAX_DIM_IMAGEN, calidad=CALIDAD_WEBP):
    """Devuelve (bytes, formato) de la imagen comprimida, preferentemente WebP."""
    img = _aplanar_rgb(img)
    if img.width > max_dim or img.height > max_dim:
        img = img.copy()
        img.thumbnail((max_dim, max_dim), Image.Resampling.LANCZOS)
    buffer = io.BytesIO()
    try:
        img.save(buffer, format="WEBP", quality=calidad, method=4)
        return buffer.getvalue(), "webp"
    except Exception:
        buffer = io.BytesIO()
        img.save(buffer, format="JPEG", quality=85, optimize=True)
        return buffer.getvalue(), "jpeg"


def _miniatura_png(img):
    img = _aplanar_rgb(img).copy()
    img.thumbnail((ANCHO_MINIATURA, ANCHO_MINIATURA), Image.Resampling.LANCZOS)
    buffer = io.BytesIO()
    img.save(buffer, format="PNG")
    return buffer.getvalue()


def _abrir_elemento(elemento):
    return Image.open(io.BytesIO(elemento["imagen"]))


# --- Recomendación automática de acción (OCR / Incrustar / Descartar) ---

def _aspecto_imagen(img):
    """Estadísticas baratas: proporción de tonos extremos y colores únicos.

    Una imagen de texto o diagrama es bimodal (fondo claro + tinta) y tiene
    pocos colores; una fotografía concentra tonos medios y miles de colores.
    """
    gris = img.convert("L").copy()
    gris.thumbnail((256, 256))
    hist = gris.histogram()
    total = max(1, sum(hist))
    extremos = (sum(hist[:64]) + sum(hist[192:])) / total
    rgb = img.convert("RGB").copy()
    rgb.thumbnail((128, 128))
    colores = rgb.getcolors(maxcolors=rgb.width * rgb.height)
    n_colores = len(colores) if colores else rgb.width * rgb.height
    return extremos, n_colores


def _datos_ocr(img, idioma):
    try:
        return pytesseract.image_to_data(img, lang=idioma, output_type=Output.DICT)
    except pytesseract.TesseractError:
        # Idioma no instalado: se reintenta con el idioma por defecto
        return pytesseract.image_to_data(img, output_type=Output.DICT)


def _confianza_ocr(img, idioma):
    """Devuelve (confianza media, cantidad de palabras) según Tesseract."""
    datos = _datos_ocr(img, idioma)
    confianzas = []
    for texto, conf in zip(datos["text"], datos["conf"]):
        try:
            valor = float(conf)
        except (TypeError, ValueError):
            continue
        if valor >= 0 and str(texto).strip():
            confianzas.append(valor)
    if not confianzas:
        return 0.0, 0
    return sum(confianzas) / len(confianzas), len(confianzas)


def recomendar_elemento(elemento, idioma="spa"):
    """Sugiere la acción para un elemento visual y la razón de la sugerencia."""
    img = _abrir_elemento(elemento)
    if img.width < LADO_MINIMO_ELEMENTO or img.height < LADO_MINIMO_ELEMENTO:
        return ACCION_DESCARTAR, "Elemento muy pequeño (probable adorno o línea)"
    if elemento.get("repeticiones", 1) >= REPETICIONES_DESCARTE:
        return ACCION_DESCARTAR, (
            f"Se repite en {elemento['repeticiones']} páginas (probable logo o encabezado)"
        )
    extremos, n_colores = _aspecto_imagen(img)
    if extremos < 0.45 and n_colores > 4000:
        return ACCION_INCRUSTAR, "Apariencia fotográfica (gran variedad de tonos)"
    if not TESSERACT_DISPONIBLE:
        return ACCION_INCRUSTAR, "Tesseract no disponible; se conserva como imagen"
    confianza, palabras = _confianza_ocr(img, idioma)
    if palabras >= 8 and confianza >= 60:
        return ACCION_OCR, f"Texto legible ({palabras} palabras, confianza media {confianza:.0f}%)"
    if palabras >= 25 and confianza >= 45:
        return ACCION_OCR, f"Texto denso con confianza moderada ({confianza:.0f}%)"
    return ACCION_INCRUSTAR, "Sin texto suficientemente legible; se conserva como imagen"


# --- Análisis del PDF (extracción de elementos visuales) ---

def _imagen_de_bloque(bloque):
    try:
        return Image.open(io.BytesIO(bloque["image"]))
    except Exception:
        pix = fitz.Pixmap(bloque["image"])
        if pix.n >= 4:
            pix = fitz.Pixmap(fitz.csRGB, pix)
        return Image.open(io.BytesIO(pix.tobytes("png")))


def _crear_elemento(img, num_pagina, tipo):
    datos, formato = optimizar_imagen(img)
    return {
        "pagina": num_pagina,
        "tipo": tipo,
        "imagen": datos,
        "formato": formato,
        "thumb_png": _miniatura_png(img),
        "hash": hashlib.md5(datos).hexdigest(),
        "repeticiones": 1,
        "recomendacion": ACCION_INCRUSTAR,
        "razon": "",
    }


def _extraer_imagenes(pagina, num_pagina):
    elementos = []
    for bloque in pagina.get_text("dict")["blocks"]:
        if bloque.get("type") != 1:
            continue
        try:
            img = _imagen_de_bloque(bloque)
            elementos.append(_crear_elemento(img, num_pagina, "Imagen incrustada"))
        except Exception:
            continue
    return elementos


def _fusionar_rectangulos(rectangulos, margen=15):
    """Agrupa rectángulos cercanos para tratar cada gráfico vectorial como una unidad."""
    cambio = True
    while cambio:
        cambio = False
        nuevos = []
        while rectangulos:
            r1 = rectangulos.pop(0)
            r1_exp = fitz.Rect(r1.x0 - margen, r1.y0 - margen, r1.x1 + margen, r1.y1 + margen)
            fusionado = False
            for i, r2 in enumerate(nuevos):
                if r1_exp.intersects(r2):
                    nuevos[i] |= r1
                    fusionado = True
                    cambio = True
                    break
            if not fusionado:
                nuevos.append(r1)
        rectangulos = nuevos
    return rectangulos


def _extraer_vectores(pagina, num_pagina, bboxes_tablas=()):
    elementos = []
    # Se incluyen también los trazos de área cero (líneas sueltas): al
    # fusionarlos forman el rectángulo del gráfico completo.
    rectangulos = [d["rect"] for d in pagina.get_drawings()
                   if d["rect"].is_valid and not d["rect"].is_infinite]
    for rect in _fusionar_rectangulos(rectangulos):
        if rect.width <= 30 or rect.height <= 30:
            continue
        if _solapa_con_tablas(rect, bboxes_tablas, umbral=0.5):
            # Son los bordes de una tabla ya extraída como Markdown
            continue
        try:
            pix = pagina.get_pixmap(clip=rect, dpi=150)
            img = Image.open(io.BytesIO(pix.tobytes("png")))
            elementos.append(_crear_elemento(img, num_pagina, "Gráfico vectorial"))
        except Exception:
            continue
    return elementos


def _marcar_repetidas(elementos):
    conteo = {}
    for elemento in elementos:
        conteo[elemento["hash"]] = conteo.get(elemento["hash"], 0) + 1
    for elemento in elementos:
        elemento["repeticiones"] = conteo[elemento["hash"]]


def analizar_pdf(ruta_pdf, idioma="spa", progreso=None):
    """Extrae los elementos visuales del PDF y calcula la acción recomendada.

    `progreso` es un callback opcional (texto, fraccion) para informar avance.
    Las recomendaciones se evalúan en paralelo (Tesseract corre como
    subproceso, por lo que los hilos escalan bien).
    """
    def avisar(texto, fraccion):
        if progreso:
            progreso(texto, fraccion)

    elementos = []
    doc = fitz.open(ruta_pdf)
    try:
        total = len(doc)
        for num_pagina, pagina in enumerate(doc):
            avisar(f"Analizando página {num_pagina + 1}/{total}", 0.5 * num_pagina / total)
            elementos.extend(_extraer_imagenes(pagina, num_pagina))
            bboxes_tablas = [bbox for bbox, _ in _buscar_tablas(pagina)]
            elementos.extend(_extraer_vectores(pagina, num_pagina, bboxes_tablas))
    finally:
        doc.close()

    _marcar_repetidas(elementos)

    if elementos:
        with ThreadPoolExecutor(max_workers=HILOS_OCR) as pool:
            futuros = {pool.submit(recomendar_elemento, e, idioma): e for e in elementos}
            for i, futuro in enumerate(as_completed(futuros)):
                elemento = futuros[futuro]
                try:
                    elemento["recomendacion"], elemento["razon"] = futuro.result()
                except Exception:
                    elemento["recomendacion"], elemento["razon"] = (
                        ACCION_INCRUSTAR, "No se pudo evaluar; se conserva como imagen"
                    )
                avisar(f"Evaluando elementos ({i + 1}/{len(elementos)})",
                       0.5 + 0.5 * (i + 1) / len(elementos))
    return elementos


# --- Tablas ---

def tabla_a_markdown(tabla):
    """Convierte una lista de filas (listas de celdas) a una tabla Markdown."""
    if not tabla or not tabla[0]:
        return ""

    tabla_limpia = []
    for fila in tabla:
        tabla_limpia.append([
            str(celda).replace("\n", " ").replace("|", "\\|").strip() if celda else ""
            for celda in fila
        ])

    num_cols = max(len(fila) for fila in tabla_limpia)
    tabla_limpia = [fila + [""] * (num_cols - len(fila)) for fila in tabla_limpia]
    cols_validas = [i for i in range(num_cols)
                    if any(fila[i] != "" for fila in tabla_limpia)]
    if not cols_validas:
        return ""

    tabla_filtrada = [[fila[i] for i in cols_validas] for fila in tabla_limpia]
    encabezados = [celda if celda != "" else " " for celda in tabla_filtrada[0]]

    md = "\n| " + " | ".join(encabezados) + " |\n"
    md += "| " + " | ".join(["---"] * len(encabezados)) + " |\n"
    for fila in tabla_filtrada[1:]:
        md += "| " + " | ".join(fila) + " |\n"
    return md + "\n"


def _tabla_valida(filas, minimo_filas=2, minimo_relleno=0.3):
    if len(filas) < minimo_filas:
        return False
    num_cols = max(len(f) for f in filas)
    if num_cols < 2:
        return False
    celdas = [c for f in filas for c in f]
    no_vacias = sum(1 for c in celdas if c and str(c).strip())
    return celdas and no_vacias / len(celdas) >= minimo_relleno


def _buscar_tablas(pagina):
    """Detecta tablas con PyMuPDF probando estrategias en cascada.

    Primero por líneas (tablas con bordes); si no hay resultados, por
    alineación de texto, con una validación más estricta para evitar
    falsos positivos.
    """
    if not hasattr(pagina, "find_tables"):
        return []
    resultado = []
    for estrategia, minimo_filas, minimo_relleno in (("lines", 2, 0.3), ("text", 3, 0.6)):
        try:
            tablas = pagina.find_tables(strategy=estrategia)
        except Exception:
            continue
        for tabla in tablas.tables:
            filas = tabla.extract()
            if _tabla_valida(filas, minimo_filas, minimo_relleno):
                resultado.append((fitz.Rect(tabla.bbox), filas))
        if resultado:
            break
    return resultado


def _solapa_con_tablas(rect, bboxes_tablas, umbral=0.3):
    """Indica si un bloque de texto está mayormente dentro de alguna tabla."""
    area = rect.get_area()
    if area <= 0:
        return False
    for bbox in bboxes_tablas:
        if (rect & bbox).get_area() / area > umbral:
            return True
    return False


def _contenido_pagina(pagina):
    """Texto y tablas de una página, en orden de lectura y sin duplicados."""
    items = []
    tablas = _buscar_tablas(pagina)
    bboxes_tablas = [bbox for bbox, _ in tablas]
    for bbox, filas in tablas:
        md = tabla_a_markdown(filas)
        if md:
            items.append((bbox.y0, md))

    for x0, y0, x1, y1, texto, _, tipo in pagina.get_text("blocks"):
        if tipo != 0:
            continue
        if _solapa_con_tablas(fitz.Rect(x0, y0, x1, y1), bboxes_tablas):
            continue
        lineas = [linea.strip() for linea in texto.split("\n") if linea.strip()]
        if lineas:
            items.append((y0, "\n".join(lineas) + "\n\n"))

    items.sort(key=lambda item: item[0])
    return [contenido for _, contenido in items]


# --- OCR con reconstrucción de tablas ---

def _lineas_ocr(img, idioma):
    """Agrupa las palabras detectadas por Tesseract en líneas ordenadas."""
    datos = _datos_ocr(img, idioma)
    lineas = {}
    for i, texto in enumerate(datos["text"]):
        texto = str(texto).strip()
        try:
            conf = float(datos["conf"][i])
        except (TypeError, ValueError):
            continue
        if not texto or conf < 0:
            continue
        clave = (datos["block_num"][i], datos["par_num"][i], datos["line_num"][i])
        izquierda = datos["left"][i]
        linea = lineas.setdefault(clave, {"top": datos["top"][i], "palabras": []})
        linea["top"] = min(linea["top"], datos["top"][i])
        linea["palabras"].append(
            (izquierda, izquierda + datos["width"][i], texto, datos["height"][i])
        )
    orden = sorted(lineas.values(), key=lambda linea: linea["top"])
    for linea in orden:
        linea["palabras"].sort()
    return [linea["palabras"] for linea in orden]


def _separar_celdas(palabras):
    """Divide las palabras de una línea en celdas donde hay huecos grandes."""
    alturas = [alto for _, _, _, alto in palabras]
    umbral = max(12, 1.8 * statistics.median(alturas))
    celdas = []
    actual = [palabras[0]]
    for anterior, palabra in zip(palabras, palabras[1:]):
        if palabra[0] - anterior[1] > umbral:
            celdas.append(actual)
            actual = []
        actual.append(palabra)
    celdas.append(actual)
    return [(grupo[0][0], " ".join(p[2] for p in grupo)) for grupo in celdas], umbral


def _alinear_columnas(filas, tolerancia):
    """Asigna cada celda a una columna agrupando sus posiciones horizontales."""
    posiciones = sorted(izq for fila in filas for izq, _ in fila)
    anclas = []
    for pos in posiciones:
        if not anclas or pos - anclas[-1] > tolerancia:
            anclas.append(pos)
    grilla = []
    for fila in filas:
        celdas = [""] * len(anclas)
        for izq, texto in fila:
            indice = min(range(len(anclas)), key=lambda i: abs(anclas[i] - izq))
            celdas[indice] = (celdas[indice] + " " + texto).strip()
        grilla.append(celdas)
    return grilla


def ocr_a_markdown(elemento, idioma="spa"):
    """Aplica OCR a un elemento. Si detecta estructura de columnas devuelve
    una tabla Markdown; en caso contrario, texto plano."""
    if not TESSERACT_DISPONIBLE:
        return "*(Tesseract no está instalado; no se pudo aplicar OCR)*"
    img = _abrir_elemento(elemento)
    lineas = _lineas_ocr(img, idioma)
    if not lineas:
        return ""

    filas, umbrales = [], []
    for palabras in lineas:
        celdas, umbral = _separar_celdas(palabras)
        filas.append(celdas)
        umbrales.append(umbral)

    con_columnas = sum(1 for fila in filas if len(fila) >= 2)
    if len(filas) >= 3 and con_columnas / len(filas) >= 0.6:
        grilla = _alinear_columnas(filas, tolerancia=2 * statistics.median(umbrales))
        md = tabla_a_markdown(grilla)
        if md:
            return md.strip()
    return "\n".join(" ".join(texto for _, texto in fila) for fila in filas)


# --- Generación del Markdown ---

OPCIONES_POR_DEFECTO = {
    "modo_imagenes": "carpeta",   # "carpeta" (archivos externos) o "base64"
    "idioma": "spa",
    "marcar_paginas": True,
}


def _guardar_imagen_externa(elemento, dir_imagenes, archivos_escritos):
    nombre = archivos_escritos.get(elemento["hash"])
    if nombre is None:
        extension = "jpg" if elemento["formato"] == "jpeg" else elemento["formato"]
        nombre = f"imagen_{len(archivos_escritos) + 1:03d}.{extension}"
        os.makedirs(dir_imagenes, exist_ok=True)
        with open(os.path.join(dir_imagenes, nombre), "wb") as salida:
            salida.write(elemento["imagen"])
        archivos_escritos[elemento["hash"]] = nombre
    return nombre


def _md_imagen(elemento, opciones, dir_imagenes, archivos_escritos):
    alt = f"{elemento['tipo']} - página {elemento['pagina'] + 1}"
    if opciones["modo_imagenes"] == "base64":
        codificada = base64.b64encode(elemento["imagen"]).decode("ascii")
        return f"![{alt}](data:image/{elemento['formato']};base64,{codificada})\n\n"
    nombre = _guardar_imagen_externa(elemento, dir_imagenes, archivos_escritos)
    ruta_relativa = f"{os.path.basename(dir_imagenes)}/{nombre}"
    return f"![{alt}]({quote(ruta_relativa, safe='/')})\n\n"


def generar_markdown(ruta_pdf, ruta_md, elementos, opciones=None, progreso=None):
    """Genera el archivo Markdown combinando texto, tablas y elementos visuales.

    Los elementos deben traer la clave "accion" (Incrustar / OCR); los OCR se
    procesan en paralelo mientras se extrae el texto de las páginas.
    """
    opciones = {**OPCIONES_POR_DEFECTO, **(opciones or {})}

    def avisar(texto, fraccion):
        if progreso:
            progreso(texto, fraccion)

    dir_imagenes = os.path.splitext(ruta_md)[0] + "_archivos"
    archivos_escritos = {}
    partes = []

    doc = fitz.open(ruta_pdf)
    pool = ThreadPoolExecutor(max_workers=HILOS_OCR)
    try:
        futuros_ocr = {
            id(elemento): pool.submit(ocr_a_markdown, elemento, opciones["idioma"])
            for elemento in elementos if elemento.get("accion") == ACCION_OCR
        }

        total = len(doc)
        for num_pagina, pagina in enumerate(doc):
            avisar(f"Generando página {num_pagina + 1}/{total}", num_pagina / total)
            if opciones["marcar_paginas"]:
                partes.append(f"\n<!-- Página {num_pagina + 1} -->\n\n")
            partes.extend(_contenido_pagina(pagina))

            for elemento in elementos:
                if elemento["pagina"] != num_pagina:
                    continue
                accion = elemento.get("accion")
                if accion == ACCION_INCRUSTAR:
                    partes.append(_md_imagen(elemento, opciones, dir_imagenes, archivos_escritos))
                elif accion == ACCION_OCR:
                    try:
                        texto_ocr = futuros_ocr[id(elemento)].result()
                    except Exception as error:
                        texto_ocr = f"*(Error al aplicar OCR: {error})*"
                    if texto_ocr.strip():
                        partes.append(f"**Texto extraído (OCR):**\n\n{texto_ocr.strip()}\n\n")
    finally:
        pool.shutdown(wait=False, cancel_futures=True)
        doc.close()

    with open(ruta_md, "w", encoding="utf-8") as salida:
        salida.write("".join(partes))
    avisar("Archivo generado", 1.0)
    return ruta_md


def convertir_automatico(ruta_pdf, ruta_md=None, opciones=None, progreso=None):
    """Conversión completa aplicando las recomendaciones sin intervención."""
    opciones = {**OPCIONES_POR_DEFECTO, **(opciones or {})}
    if ruta_md is None:
        ruta_md = os.path.splitext(ruta_pdf)[0] + ".md"
    elementos = analizar_pdf(ruta_pdf, idioma=opciones["idioma"], progreso=progreso)
    seleccionados = []
    for elemento in elementos:
        elemento["accion"] = elemento["recomendacion"]
        if elemento["accion"] != ACCION_DESCARTAR:
            seleccionados.append(elemento)
    generar_markdown(ruta_pdf, ruta_md, seleccionados, opciones, progreso)
    return ruta_md, elementos


# --- Interfaz de línea de comandos ---

def main():
    parser = argparse.ArgumentParser(
        description="Convierte PDFs a Markdown aplicando automáticamente las "
                    "recomendaciones de OCR/incrustación."
    )
    parser.add_argument("pdfs", nargs="+", help="Archivos PDF a convertir")
    parser.add_argument("-o", "--salida",
                        help="Ruta del .md de salida (solo con un único PDF)")
    parser.add_argument("--imagenes", choices=["carpeta", "base64"], default="carpeta",
                        help="Guardar imágenes en carpeta externa (por defecto) o en base64")
    parser.add_argument("--idioma", default="spa", help="Idioma de Tesseract (por defecto: spa)")
    argumentos = parser.parse_args()

    if argumentos.salida and len(argumentos.pdfs) > 1:
        parser.error("--salida solo es válido cuando se convierte un único PDF")
    if not TESSERACT_DISPONIBLE:
        print("Aviso: Tesseract no está disponible; no se aplicará OCR.")

    opciones = {"modo_imagenes": argumentos.imagenes, "idioma": argumentos.idioma}
    for ruta_pdf in argumentos.pdfs:
        print(f"Convirtiendo {ruta_pdf} ...")
        ruta_md, elementos = convertir_automatico(
            ruta_pdf, argumentos.salida, opciones,
            progreso=lambda texto, _: print(f"  {texto}", end="\r"),
        )
        print()
        for elemento in elementos:
            print(f"  - Pág. {elemento['pagina'] + 1} | {elemento['tipo']} | "
                  f"{elemento['recomendacion']}: {elemento['razon']}")
        print(f"  Generado: {ruta_md}")


if __name__ == "__main__":
    main()
