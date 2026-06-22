# Nominator

Sistema de **nomenclatura e inventario** de equipos de cómputo y hardware.
Departamento de Sistemas — Municipalidad de Lago Puelo.

Genera nombres de red (NetBIOS/DNS) únicos a partir de la repartición y mantiene
el inventario con datos técnicos, componentes, relaciones, mudanzas e historial.
Ver el alcance completo en [`requerimientos.md`](requerimientos.md).

## Stack

- **PHP 8.0+** plano (sin framework) + **PDO/SQLite**. Sin Composer ni dependencias.
  - En **WAMP**: clic izquierdo en el ícono de la bandeja → *PHP* → *Version* → **8.1** o superior.
  - En **Donweb** u otro hosting: elegir PHP **8.0+** en el panel.
- Ruteo por query-string (`index.php?r=...`); no requiere `mod_rewrite`.
- Pensado para **hosting compartido** (Donweb): subir por FTP y funciona.

## Estructura

```
Nominator/
├── index.php           Front controller (rutas)
├── lib/                Lógica: config, db, auth, helpers, hostname, import
├── views/              Plantillas (layout PCB + reportes institucionales)
├── assets/             pcb.css (estética placa de circuito) + escudo.svg
├── datos/              SQLite + CSV del organigrama (protegido por .htaccess)
└── requerimientos.md   Especificación funcional
```

## Puesta en marcha

1. Subir la carpeta `Nominator/` al hosting (o servir local con
   `php -S 127.0.0.1:8000` dentro de la carpeta).
2. Abrir en el navegador: la base **se crea sola** en el primer acceso
   (esquema + datos iniciales + importación del organigrama desde
   `datos/areas_iniciales.csv`).
3. Ingresar con **`admin` / `admin`** y cambiar la clave.
4. Reemplazar `assets/escudo.svg` por el escudo real (o subir `escudo.png` y
   ajustar `ORG_ESCUDO` en `lib/config.php`).

## Nomenclatura

`{repartición-invertida}-{TIPO}{NNN}` → `SGYA-DA-DK001`
(máx. 15 caracteres NetBIOS; si excede, cae a la dependencia hoja y avisa).

## Estado de implementación

**Listo (esta entrega):**
- Esquema completo de base (todas las entidades del spec).
- Login con roles (admin / técnico / lectura).
- Importación del organigrama con detección de códigos duplicados.
- Motor de generación/validación de hostname con previsualización en vivo.
- ABM básico de equipos (alta, listado por repartición, ficha).
- Reportes institucionales: **ficha de hardware** y **extracto por repartición**
  (declaración de inventario), con escudo y leyenda.
- Estética PCB + auditoría de acciones.

**Próximas fases:**
- Componentes + importación CPU-Z/HWMonitor.
- Accesos remotos, redes WiFi (enmascaradas por rol), relaciones, mudanzas.
- Insumos de impresora, adjuntos (incl. acta de entrega), reparaciones.
- Atributos dinámicos por tipo, etiqueta QR, backup/exportación, validaciones.
- ABM de tablas auxiliares y gestión de usuarios.
