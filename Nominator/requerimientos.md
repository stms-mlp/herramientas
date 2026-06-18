# Nominator — Sistema de Nomenclatura e Inventario de Equipos

> Herramienta para **nombrar de forma unívoca** y **mantener el inventario** del
> equipamiento informático y de hardware de la organización (Municipalidad de
> Lago Puelo), generando códigos a partir de la estructura de reparticiones y
> registrando datos técnicos, componentes, insumos, accesos remotos y relaciones
> entre equipos.

Reemplaza al inventario anterior en HTML (`Inventario23/`), que usaba códigos
del tipo `00-E00-05` (área de 2 dígitos + **una letra en español** por tipo) y
hojas estáticas difíciles de mantener cuando las áreas cambian.

---

## 1. Objetivo

1. **Nomenclar**: dado un equipo y el área a la que pertenece, generar un nombre
   de red (hostname) único, válido y legible.
2. **Inventariar**: guardar datos técnicos del equipo y de sus **componentes**.
3. **Relacionar**: vincular equipos entre sí (un desktop tiene tal monitor y se
   conecta a tal impresora).
4. **Trazar**: registrar mudanzas y cambios, sin perder la identidad ni la
   historia del equipo.

---

## 2. Stack técnico y despliegue

- **Lenguaje:** PHP plano (sin framework pesado), con **PDO + SQLite**.
- **Sin dependencias / sin Composer:** se sube por FTP a **hosting compartido
  (Donweb)** y funciona sin configuración especial.
- **Ruteo por query-string** (`index.php?r=...`) para no depender de
  `mod_rewrite`.
- Base de datos en archivo `datos/nominator.sqlite`, protegido del acceso web
  por `.htaccess`.
- La lógica de negocio queda aislada en `lib/` para poder migrar a un framework
  (Slim/Leaf) más adelante si hiciera falta.

---

## 3. Nomenclatura (regla central)

Cada equipo tiene **dos identificadores** con propósitos distintos:

| Identificador | Para qué sirve | ¿Cambia en mudanza? | Ejemplo |
|---|---|---|---|
| **ID patrimonial** (interno) | Clave **permanente** del equipo. A esto se "cuelgan" componentes, relaciones, accesos e historial. | **No** | `#0042` |
| **Hostname** (NetBIOS/DNS) | Nombre real del equipo en Windows/red. | **Sí** (refleja el área actual) | `SGYA-DA-DK001` |

### 3.1 Reglas del hostname

- Sólo caracteres válidos **NetBIOS y DNS**: `A–Z`, `0–9` y guion medio `-`.
  - Sin acentos, sin espacios, sin `#`, `.`, `_` ni símbolos. Mayúsculas.
  - Sin guion al inicio/fin ni guiones consecutivos.
- **Largo máximo 15 caracteres** (límite NetBIOS; DNS admite 63, manda el menor).
- **Formato:** `{repartición-invertida}-{TIPO}{NNN}`
  - *Repartición invertida*: el código del organigrama se invierte y se
    reemplaza `#` por `-`. Ej.: `DA#SGYA` → `SGYA-DA`. (Padre primero, dependencia
    después.)
  - *TIPO*: código de 2 letras del tipo de equipo (ver §4).
  - *NNN*: correlativo de 3 dígitos **por área y por tipo** (reinicia en cada
    área/tipo).
  - Ejemplo completo: `SGYA-DA-DK001` = Secretaría de Gobierno y Administración →
    Dirección de Administración → Desktop 001.

### 3.2 Manejo del límite de 15 caracteres

Algunas reparticiones largas se pasan (ej. `SGYA-DEESOE-DK001` = 17). El sistema:

1. Si el nombre completo entra en 15 → lo usa.
2. Si no entra → cae automáticamente a **sólo la dependencia hoja**
   (`DEESOE-DK001`) y **avisa** al usuario.
3. Si aún así excede → trunca de forma controlada y avisa.
4. Verifica unicidad global del hostname; ante colisión, ajusta el correlativo.

> **Nota de datos:** en el organigrama hay un código duplicado real
> (`DEPC#SGYA` aparece como "Prensa y Ceremonial" **y** "Protección Civil"). El
> sistema debe **detectar y marcar** estos duplicados al importar para que se
> corrija uno (ej. renombrar a `DEPCIV#SGYA`), porque generarían hostnames
> colisionantes.

---

## 4. Tipos de equipo

- Código de **2 letras** derivado del **nombre en inglés** (aunque la interfaz
  los muestre en español). Editable desde tabla auxiliar (§7).
- Flag **"lleva hostname"**: los equipos de red obtienen nombre NetBIOS/DNS; los
  periféricos (monitor, UPS, etc.) entran al inventario y se asocian a un equipo
  padre, pero no generan hostname.

| Tipo (ES) | Nombre EN | Código | ¿Hostname? |
|---|---|---|---|
| PC de Escritorio | Desktop | `DK` | ✅ |
| Notebook | Notebook | `NB` | ✅ |
| Server | Server | `SV` | ✅ |
| Router | Router | `RT` | ✅ |
| Switch | Switch | `SW` | ✅ |
| DVR | DVR | `DV` | ✅ |
| Impresora | Printer | `PR` | ✅ |
| Monitor | Display | `DY` | ❌ |
| UPS | UPS | `UP` | ❌ |
| Estabilizador | Voltage Regulator | `VR` | ❌ |
| Celular | Mobile | `MB` | ❌ |

> Códigos a confirmar/ajustar: `DK` (Desktop), `VR` (Estabilizador) y `MB`
> (Celular). Al ser tabla auxiliar, se cambian sin tocar código.

---

## 5. Modelo de datos

Orientado a objetos/componentes: un equipo **contiene** componentes, y cada
componente tiene sus propias características.

### 5.1 Entidades

- **Area (Repartición)** — importada del organigrama (CSV `areas_iniciales.csv`).
  Campos: `codigo` (ej. `DA#SGYA`), `descripcion`, `estructura`
  (Secretaría/Dirección/Departamento/División), `codigo_padre`, `abreviatura`,
  `ubicacion`, `activa`. **Versionable** (las áreas se crean, cambian y
  desaparecen → §6 y §7).
- **TipoEquipo** — `codigo` (2 letras EN), `nombre_es`, `lleva_hostname`.
- **Estado** — En uso / En depósito / En reparación / De baja. (Auxiliar.)
- **Equipo** — `id_patrimonial` (permanente), `hostname`, `tipo_id`,
  `area_actual_id`, `estado_id`, `marca`, `modelo`, `n_serie`, `n_parte`,
  `fecha_alta`, `observaciones`.
- **Componente** — pertenece a un `Equipo`. `tipo_componente`
  (CPU / RAM / Disco / GPU / Motherboard / etc.), y atributos clave→valor con las
  **características más importantes** (no todas). **Importable** desde reportes de
  **CPU-Z** y **HWMonitor** (§5.2).
- **AccesoRemoto** — `equipo_id`, `servicio` (Anydesk / VNC / RDP / TeamViewer…
  desde tabla auxiliar), `identificador` (ej. ID de Anydesk), `nota`. Según el
  servicio se cargan los datos correspondientes.
- **Relacion** — `equipo_a_id`, `equipo_b_id`, `tipo`
  (usa_monitor / conecta_impresora / componente_de / otro).
- **Movimiento** — `equipo_id`, `fecha`, `area_origen_id`, `area_destino_id`,
  `hostname_anterior`, `hostname_nuevo`, `motivo`, `usuario`.
- **Insumo / Consumible** — para impresoras: `tipo` (tóner / unidad de imagen /
  chip / cartucho), `modelo`, `stock`, y **compatibilidad** con modelos de
  impresora/equipos. (Migra la lógica de `Toners.html`.)
- **Red/IP** (opcional) — `equipo_id`, `ip`, `segmento`, `nota`. (Migra
  `Red.html`.)

### 5.2 Importación de características (CPU-Z / HWMonitor)

- El sistema **recibe y procesa** un archivo de **CPU-Z** (reporte `.txt`/`.html`)
  o **HWMonitor** y extrae las características más relevantes para poblar los
  **Componentes** del equipo (procesador y su modelo/núcleos/frecuencia, RAM
  total y tipo, placa madre, discos, GPU, etc.).
- Objetivo: cargar specs sin tipeo manual y de forma consistente.

---

## 6. Mudanzas y cambios

- El **ID patrimonial es estable**: mover un equipo **no** rompe sus
  componentes, relaciones, accesos ni historia.
- Al mudar un equipo de área, el sistema:
  1. Registra un **Movimiento** (origen → destino, fecha, motivo, usuario).
  2. Opcionalmente **regenera el hostname** según la nueva área (guardando el
     anterior), o lo conserva si se prefiere.
- Como las áreas mismas cambian (se renombran, aparecen, desaparecen), **todo
  valor "que define nombres" vive en tablas auxiliares editables** (§7), no
  hardcodeado.

---

## 7. Tablas auxiliares (editables)

Todo lo que pueda cambiar con el tiempo debe ser editable desde la interfaz, sin
tocar código:

- **Áreas / Reparticiones** (alta, baja, modificación; reflejan cambios del
  organigrama).
- **Tipos de equipo** (código, nombre, flag hostname).
- **Estados**.
- **Servicios de acceso remoto** (Anydesk, VNC, RDP…).
- **Tipos de componente**.
- **Insumos y compatibilidades** (tóners, unidades de imagen, chips).

---

## 8. Funcionalidades (alcance)

- ABM de equipos con **previsualización en vivo del hostname** y validación
  NetBIOS/DNS (caracteres y longitud).
- Selección del **área** desde el organigrama para generar el código.
- ABM de componentes + **importación CPU-Z/HWMonitor**.
- **Accesos remotos** por equipo (Anydesk/VNC/…).
- **Relaciones** entre equipos (monitor↔desktop, desktop→impresora).
- **Mudanzas** con historial.
- **Insumos** de impresoras (tóners y compatibilidad, con stock).
- Listados/filtros por área, tipo, estado, IP.
- ABM de todas las **tablas auxiliares**.

---

## 9. Migración del inventario viejo (`Inventario23/`)

Material de referencia disponible (HTML exportado de planillas):

- `Areas.html` — áreas con código de 2 dígitos, abreviatura (4 letras) y
  ubicación.
- `Planilla Inv..html` — equipos: Tipo, Descripción, ID, Uso, IP, Marca+Modelo+
  Componentes+N/S, observaciones. Códigos legacy `00-{letra}00-{n}`.
- `Red.html` — IP, área, hostname, especificaciones.
- `Toners.html` — modelos de tóner ↔ impresoras ↔ stock.

> La importación se evaluará **más adelante**: por ahora sirve de referencia para
> mapear campos y, eventualmente, levantar algunos datos.

---

## 10. Decisiones pendientes / a confirmar

1. Códigos de tipo a confirmar: `DK` (Desktop), `VR` (Estabilizador), `MB`
   (Celular).
2. ¿El **ID patrimonial** es un correlativo simple (`#0042`) o sigue algún
   esquema con prefijo (ej. `MLP-0042`)?
3. ¿Qué características exactas levantar de CPU-Z/HWMonitor (lista mínima)?
4. ¿Campos extra de gestión que se usen siempre? (usuario responsable, fecha de
   compra, N° de factura/orden de compra, garantía).
5. ¿Manejo de **usuarios/login** en la app, o acceso libre dentro del hosting?
