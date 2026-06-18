# Nominator — Sistema de Nomenclatura e Inventario de Equipos

> Herramienta para **nombrar de forma unívoca** y **mantener el inventario** del
> equipamiento informático y de hardware de la organización (Municipalidad de
> Lago Puelo), generando códigos a partir de la estructura de reparticiones y
> registrando datos técnicos, componentes, insumos, accesos remotos y relaciones
> entre equipos.

Sustituye al inventario anterior en HTML (`Inventario23/`). **Es un sistema
nuevo: se aplican buenas prácticas y NO se arrastran estructuras del sistema
viejo que no aporten o puedan mejorarse.** El material viejo sirve sólo como
referencia y eventual fuente de algunos datos.

---

## 1. Objetivo

1. **Nomenclar**: dado un equipo y el área a la que pertenece, generar un nombre
   de red (hostname) único, válido y legible.
2. **Inventariar**: guardar datos técnicos del equipo y de sus **componentes**.
3. **Relacionar**: vincular equipos entre sí (un desktop tiene tal monitor y se
   conecta a tal impresora).
4. **Trazar**: registrar mudanzas y cambios, sin perder la identidad ni la
   historia del equipo.
5. **Listar por repartición** y emitir un **resumen de hardware por equipo**
   legible.

---

## 2. Stack técnico y despliegue

- **Lenguaje:** PHP plano (sin framework pesado), con **PDO + SQLite**.
- **Sin dependencias / sin Composer:** se sube por FTP a **hosting compartido
  (Donweb)** y funciona sin configuración especial.
- **Ruteo por query-string** (`index.php?r=...`) para no depender de
  `mod_rewrite`.
- Base de datos en archivo `datos/nominator.sqlite`, protegido del acceso web
  por `.htaccess`.
- **Login requerido** (§9). Lógica de negocio aislada en `lib/` por si más
  adelante se migra a un microframework.

---

## 3. Nomenclatura (regla central)

Cada equipo tiene **dos identificadores** con propósitos distintos:

| Identificador | Para qué sirve | ¿Cambia en mudanza? | Ejemplo |
|---|---|---|---|
| **ID patrimonial** | Identificador **externo**, cargado a mano desde otro sistema patrimonial. **Texto libre.** A esto se "cuelgan" componentes, relaciones, accesos e historial. **Estable.** | **No** | `MLP-0042` |
| **Hostname** (NetBIOS/DNS) | Nombre real del equipo en Windows/red. | **Sí** (refleja el área actual) | `SGYA-DA-DK001` |

### 3.1 Reglas del hostname

- Sólo caracteres válidos **NetBIOS y DNS**: `A–Z`, `0–9` y guion medio `-`.
  - Sin acentos, sin espacios, sin `#`, `.`, `_` ni símbolos. Mayúsculas.
  - Sin guion al inicio/fin ni guiones consecutivos.
- **Largo máximo 15 caracteres** (límite NetBIOS; DNS admite 63, manda el menor).
- **Formato:** `{repartición-invertida}-{TIPO}{NNN}`
  - *Repartición invertida*: el código del organigrama se invierte y se
    reemplaza `#` por `-`. Ej.: `DA#SGYA` → `SGYA-DA` (padre primero,
    dependencia después).
  - *TIPO*: código de 2 letras del tipo de equipo (ver §4).
  - *NNN*: correlativo de 3 dígitos **por área y por tipo**.
  - Ejemplo: `SGYA-DA-DK001` = Sec. de Gobierno y Administración → Dirección de
    Administración → Desktop 001.

### 3.2 Manejo del límite de 15 caracteres

Algunas reparticiones largas se pasan (ej. `SGYA-DEESOE-DK001` = 17). El sistema:

1. Si el nombre completo entra en 15 → lo usa.
2. Si no → cae a **sólo la dependencia hoja** (`DEESOE-DK001`) y **avisa**.
3. Si aún excede → trunca de forma controlada y avisa.
4. Verifica unicidad global del hostname; ante colisión, ajusta el correlativo.

> **Nota de datos:** el organigrama trae un código duplicado real (`DEPC#SGYA`
> figura como "Prensa y Ceremonial" **y** "Protección Civil"). El sistema debe
> **detectarlo y marcarlo** al importar para corregir uno (ej. `DEPCIV#SGYA`),
> ya que generarían hostnames colisionantes.

---

## 4. Tipos de equipo

- Código de **2 letras** derivado del **nombre en inglés** (la interfaz los
  muestra en español). Editable desde tabla auxiliar (§7).
- Flag **"lleva hostname"**: los equipos de red obtienen nombre NetBIOS/DNS; los
  periféricos (monitor, UPS, etc.) entran al inventario y se asocian a un equipo
  padre, pero no generan hostname.

| Tipo (ES) | Nombre EN | Código | ¿Hostname? |
|---|---|---|---|
| PC de Escritorio | Desktop | `DK` ✅conf. | ✅ |
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

> `DK` confirmado. `VR` y `MB` quedan como valor por defecto (editables en tabla
> auxiliar).

---

## 5. Modelo de datos

Orientado a objetos/componentes: un equipo **contiene** componentes, y cada
componente tiene sus características.

### 5.1 Entidades

- **Area (Repartición)** — importada del organigrama (`areas_iniciales.csv`).
  Campos: `codigo` (ej. `DA#SGYA`), `descripcion`, `estructura`
  (Secretaría/Dirección/Departamento/División), `codigo_padre`, `abreviatura`,
  `ubicacion`, `activa`. **Versionable** (las áreas se crean, cambian y
  desaparecen → §6 y §7).
- **TipoEquipo** — `codigo` (2 letras EN), `nombre_es`, `lleva_hostname`.
- **Estado** — En uso / En depósito / En reparación / De baja. (Auxiliar.)
- **Equipo** — `id_patrimonial` (texto libre, externo, estable), `hostname`,
  `tipo_id`, `area_actual_id`, `estado_id`, `marca`, `modelo`, `n_serie`,
  `n_parte`, `ip`, `fecha_alta`, `observaciones`, **+ gestión:**
  `responsable`, `compra_fecha`/`compra_factura`, `garantia`, **+ titularidad y
  tenencia (§5.3):** `titularidad` (Municipal / Personal), `tenencia`/`ubicacion`
  (En sede / Domicilio de empleado–teletrabajo), `tenedor` (persona que lo tiene).
- **Componente** — pertenece a un `Equipo`. `tipo_componente`
  (CPU / RAM / Disco / GPU / Motherboard / etc.) con atributos:
  **marca, modelo, n/s, velocidad, memoria, bus**. **Importable** desde reportes
  de **CPU-Z** y **HWMonitor** (§5.2).
- **AccesoRemoto** — `equipo_id`, `servicio` (Anydesk / VNC / RDP / TeamViewer…
  desde tabla auxiliar), `identificador` (ej. ID de Anydesk), `nota`.
- **Relacion** — `equipo_a_id`, `equipo_b_id`, `tipo`
  (usa_monitor / conecta_impresora / componente_de / otro).
- **Movimiento** — `equipo_id`, `fecha`, `area_origen_id`, `area_destino_id`,
  `hostname_anterior`, `hostname_nuevo`, `motivo`, `usuario`.
- **Insumo de impresora** — **ligado a la impresora** (no es un registro suelto
  como en el sistema viejo): `equipo_id` (impresora), `tipo` (tóner / unidad de
  imagen / chip / cartucho), `modelo`, `nota`/`stock`. Una impresora declara qué
  insumos usa.

> No se modela una "Red/IP" aparte: la IP es un campo del propio equipo.

### 5.3 Titularidad y tenencia (ubicación física)

Dos ejes independientes, importantes para la declaración de inventario (§8.1):

- **Titularidad** — de quién es el equipo:
  - **Municipal**: propiedad del municipio → se inventaría y declara.
  - **Personal**: del empleado, usado para trabajar pero **NO** es del municipio
    → se registra para trazabilidad/relaciones, pero **se excluye** de la
    declaración patrimonial y se marca claramente como personal.
- **Tenencia / ubicación física** — dónde está físicamente:
  - **En sede** (en el edificio/área).
  - **En domicilio de empleado** (teletrabajo), identificando al `tenedor`.

> Un equipo puede ser **municipal pero estar fuera de sede** (teletrabajo): sigue
> siendo del municipio y se declara, aunque no esté en el edificio. Y un equipo
> **personal en sede** se usa pero no se declara como municipal.

### 5.2 Importación de características (CPU-Z / HWMonitor)

- El sistema **recibe y procesa** un reporte de **CPU-Z** (`.txt`/`.html`) o
  **HWMonitor** y extrae, por componente, los datos clave: **marca, modelo, n/s,
  velocidad, memoria y bus** (procesador, RAM, placa, discos, GPU…).
- Objetivo: cargar specs sin tipeo manual y de forma consistente, para alimentar
  el **resumen de hardware** (§8.1).

---

## 6. Mudanzas y cambios

- El **ID patrimonial es estable**: mover un equipo **no** rompe sus
  componentes, relaciones, accesos ni historia.
- Al mudar un equipo de área, el sistema:
  1. Registra un **Movimiento** (origen → destino, fecha, motivo, usuario).
  2. Opcionalmente **regenera el hostname** según la nueva área (guardando el
     anterior), o lo conserva.
- Como las áreas mismas cambian (se renombran, aparecen, desaparecen), **todo
  valor que define nombres vive en tablas auxiliares editables** (§7), nunca
  hardcodeado.

---

## 7. Tablas auxiliares (editables desde la interfaz)

- **Áreas / Reparticiones** (ABM; reflejan cambios del organigrama).
- **Tipos de equipo** (código, nombre, flag hostname).
- **Estados**.
- **Servicios de acceso remoto** (Anydesk, VNC, RDP…).
- **Tipos de componente**.

---

## 8. Funcionalidades (alcance)

- ABM de equipos con **previsualización en vivo del hostname** y validación
  NetBIOS/DNS (caracteres y longitud).
- Selección del **área** desde el organigrama para generar el código.
- ABM de componentes + **importación CPU-Z/HWMonitor**.
- **Accesos remotos** por equipo (Anydesk/VNC/…).
- **Relaciones** entre equipos (monitor↔desktop, desktop→impresora).
- **Mudanzas** con historial.
- **Insumos** ligados a cada impresora.
- **Listado por repartición** (vista principal del inventario, agrupable por
  área) + filtros por tipo, estado e IP.
- ABM de **tablas auxiliares**.

### 8.1 Resumen de hardware y extracto por repartición

- **Por equipo:** ficha de **resumen de hardware** (imprimible/exportable),
  equivalente a la del inventario viejo pero **estructurada y legible**: en lugar
  del string apelmazado `Athlon 3000G + Prime A320M-K + (1) DDR4 8GB + SSD 220GB
  HDD 1TB`, una ficha clara con identificación (hostname, ID patrimonial, área,
  estado, titularidad y tenencia), componentes (CPU, placa, RAM, discos, GPU…),
  accesos remotos y equipos asociados.
- **Por repartición (extracto / declaración de inventario):** cuando un área
  solicita el listado de los equipos con que cuenta, el sistema **emite un
  extracto entregable** que el área usa en su **declaración de inventario**
  patrimonial.
  - Incluye los equipos **municipales** del área (en sede y en teletrabajo).
  - **Excluye** los equipos **personales** (o los lista aparte, claramente
    marcados como no municipales), para que no se declaren como propios del
    municipio.

---

## 9. Usuarios y acceso

- **Login requerido** para usar el sistema (no acceso libre).
- Manejo simple de usuarios/sesión adecuado a hosting compartido (a definir
  alcance de roles).

---

## 10. Decisiones tomadas

1. Código `DK` (Desktop) **confirmado**; resto editable en tabla auxiliar.
2. **ID patrimonial:** texto libre, cargado a mano desde otro sistema (formato
   tipo `MLP-0042`).
3. **CPU-Z/HWMonitor:** levantar marca, modelo, n/s, velocidad, memoria, bus.
4. **Campos de gestión:** responsable, fecha/factura de compra, garantía.
5. **Login:** requerido.

---

## 11. Material de referencia (`Inventario23/`)

`Areas.html`, `Planilla Inv..html`, `Red.html`, `Toners.html` y las hojas por
área. Se usan sólo como referencia/fuente eventual de datos; **no** se replican
sus estructuras.
