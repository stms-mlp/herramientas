<?php
/**
 * Nominator — Conexión a SQLite, migración del esquema y semillas iniciales.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(DATOS_DIR)) {
        mkdir(DATOS_DIR, 0775, true);
    }

    $nuevo = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    migrar($pdo);
    if ($nuevo) {
        sembrar($pdo);
    }
    asegurar_atributos($pdo);
    return $pdo;
}

/** Crea las tablas si no existen (idempotente). */
function migrar(PDO $db): void
{
    $db->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS usuarios (
        id          INTEGER PRIMARY KEY,
        usuario     TEXT UNIQUE NOT NULL,
        hash_clave  TEXT NOT NULL,
        nombre      TEXT,
        rol         TEXT NOT NULL DEFAULT 'lectura',
        activo      INTEGER NOT NULL DEFAULT 1,
        creado      TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );

    CREATE TABLE IF NOT EXISTS areas (
        id           INTEGER PRIMARY KEY,
        codigo       TEXT NOT NULL,
        descripcion  TEXT NOT NULL,
        estructura   TEXT,
        codigo_padre TEXT,
        abreviatura  TEXT,
        ubicacion    TEXT,
        activa       INTEGER NOT NULL DEFAULT 1,
        duplicado    INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS tipos_equipo (
        id            INTEGER PRIMARY KEY,
        codigo        TEXT UNIQUE NOT NULL,
        nombre_es     TEXT NOT NULL,
        nombre_en     TEXT,
        lleva_hostname INTEGER NOT NULL DEFAULT 1,
        activo        INTEGER NOT NULL DEFAULT 1
    );

    CREATE TABLE IF NOT EXISTS estados (
        id     INTEGER PRIMARY KEY,
        nombre TEXT UNIQUE NOT NULL,
        es_baja INTEGER NOT NULL DEFAULT 0,
        activo INTEGER NOT NULL DEFAULT 1
    );

    CREATE TABLE IF NOT EXISTS equipos (
        id            INTEGER PRIMARY KEY,
        id_patrimonial TEXT,
        hostname      TEXT UNIQUE,
        tipo_id       INTEGER NOT NULL REFERENCES tipos_equipo(id),
        area_id       INTEGER REFERENCES areas(id),
        estado_id     INTEGER REFERENCES estados(id),
        correlativo   INTEGER,
        marca         TEXT,
        modelo        TEXT,
        n_serie       TEXT,
        n_parte       TEXT,
        ip            TEXT,
        -- titularidad y tenencia
        titularidad   TEXT NOT NULL DEFAULT 'Municipal',  -- Municipal | Personal
        tenencia      TEXT NOT NULL DEFAULT 'En sede',     -- En sede | Domicilio de empleado
        tenedor       TEXT,
        -- gestión
        responsable   TEXT,
        compra_fecha  TEXT,
        compra_factura TEXT,
        garantia      TEXT,
        -- notas
        observaciones TEXT,
        notas_tecnicas TEXT,
        -- credenciales (referencia KeePass, nunca la clave)
        keepass_uuid  TEXT,
        keepass_ref   TEXT,
        keepass_usuario TEXT,
        -- baja
        baja_fecha    TEXT,
        baja_motivo   TEXT,
        baja_destino  TEXT,
        fecha_alta    TEXT NOT NULL DEFAULT (date('now','localtime')),
        creado        TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        actualizado   TEXT
    );

    CREATE TABLE IF NOT EXISTS atributos_tipo (
        id          INTEGER PRIMARY KEY,
        tipo_id     INTEGER NOT NULL REFERENCES tipos_equipo(id) ON DELETE CASCADE,
        nombre      TEXT NOT NULL,
        tipo_dato   TEXT NOT NULL DEFAULT 'texto', -- texto|numero|booleano|fecha|lista
        opciones    TEXT,
        obligatorio INTEGER NOT NULL DEFAULT 0,
        sensible    INTEGER NOT NULL DEFAULT 0,
        orden       INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS valores_atributo (
        id          INTEGER PRIMARY KEY,
        equipo_id   INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        atributo_id INTEGER NOT NULL REFERENCES atributos_tipo(id) ON DELETE CASCADE,
        valor       TEXT
    );

    CREATE TABLE IF NOT EXISTS tipos_componente (
        id     INTEGER PRIMARY KEY,
        nombre TEXT UNIQUE NOT NULL,
        activo INTEGER NOT NULL DEFAULT 1
    );

    -- Catálogo de valores reutilizables (marcas, modelos, etc.)
    CREATE TABLE IF NOT EXISTS catalogo (
        id    INTEGER PRIMARY KEY,
        campo TEXT NOT NULL,   -- marca | modelo | comp_marca | comp_modelo
        valor TEXT NOT NULL,
        UNIQUE(campo, valor)
    );

    -- Reportes de hardware enviados por el agente de los usuarios (bandeja)
    CREATE TABLE IF NOT EXISTS reportes_pendientes (
        id            INTEGER PRIMARY KEY,
        recibido      TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        origen_host   TEXT,
        origen_usuario TEXT,
        origen_ip     TEXT,
        n_componentes INTEGER DEFAULT 0,
        resumen       TEXT,
        contenido     TEXT NOT NULL,
        procesado     INTEGER NOT NULL DEFAULT 0,
        equipo_id     INTEGER
    );

    CREATE TABLE IF NOT EXISTS componentes (
        id          INTEGER PRIMARY KEY,
        equipo_id   INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        tipo        TEXT NOT NULL,   -- CPU/RAM/Disco/GPU/Motherboard...
        marca       TEXT,
        modelo      TEXT,
        n_serie     TEXT,
        velocidad   TEXT,
        memoria     TEXT,
        bus         TEXT
    );

    CREATE TABLE IF NOT EXISTS servicios_remotos (
        id     INTEGER PRIMARY KEY,
        nombre TEXT UNIQUE NOT NULL,
        activo INTEGER NOT NULL DEFAULT 1
    );

    CREATE TABLE IF NOT EXISTS accesos_remotos (
        id           INTEGER PRIMARY KEY,
        equipo_id    INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        servicio     TEXT NOT NULL,
        identificador TEXT,
        nota         TEXT
    );

    CREATE TABLE IF NOT EXISTS redes_wifi (
        id        INTEGER PRIMARY KEY,
        equipo_id INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        ssid      TEXT NOT NULL,
        usuario   TEXT,
        clave     TEXT,
        banda     TEXT,
        oculta    INTEGER NOT NULL DEFAULT 0,
        nota      TEXT
    );

    CREATE TABLE IF NOT EXISTS relaciones (
        id          INTEGER PRIMARY KEY,
        equipo_a_id INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        equipo_b_id INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        tipo        TEXT NOT NULL   -- usa_monitor|conecta_impresora|componente_de|otro
    );

    CREATE TABLE IF NOT EXISTS movimientos (
        id              INTEGER PRIMARY KEY,
        equipo_id       INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        fecha           TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        area_origen_id  INTEGER REFERENCES areas(id),
        area_destino_id INTEGER REFERENCES areas(id),
        hostname_anterior TEXT,
        hostname_nuevo  TEXT,
        motivo          TEXT,
        usuario         TEXT
    );

    CREATE TABLE IF NOT EXISTS insumos (
        id        INTEGER PRIMARY KEY,
        equipo_id INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        tipo      TEXT NOT NULL,   -- toner|unidad_imagen|chip|cartucho
        modelo    TEXT,
        stock     TEXT,
        nota      TEXT
    );

    CREATE TABLE IF NOT EXISTS adjuntos (
        id          INTEGER PRIMARY KEY,
        equipo_id   INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        tipo        TEXT NOT NULL,  -- foto|factura|remito|acta_entrega|otro
        archivo     TEXT NOT NULL,
        descripcion TEXT,
        fecha       TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );

    CREATE TABLE IF NOT EXISTS reparaciones (
        id          INTEGER PRIMARY KEY,
        equipo_id   INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        fecha       TEXT NOT NULL DEFAULT (date('now','localtime')),
        falla       TEXT,
        diagnostico TEXT,
        solucion    TEXT,
        proveedor   TEXT,
        costo       TEXT,
        estado      TEXT
    );

    CREATE TABLE IF NOT EXISTS historial_responsable (
        id          INTEGER PRIMARY KEY,
        equipo_id   INTEGER NOT NULL REFERENCES equipos(id) ON DELETE CASCADE,
        responsable TEXT,
        desde       TEXT,
        hasta       TEXT,
        motivo      TEXT
    );

    CREATE TABLE IF NOT EXISTS auditoria (
        id         INTEGER PRIMARY KEY,
        fecha      TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        usuario    TEXT,
        entidad    TEXT,
        entidad_id INTEGER,
        accion     TEXT,
        detalle    TEXT
    );
    SQL);
}

/** Carga datos iniciales: tipos, estados, servicios, usuario admin y áreas. */
function sembrar(PDO $db): void
{
    // Tipos de equipo (código de 2 letras del nombre en inglés)
    $tipos = [
        ['DK', 'PC de Escritorio', 'Desktop', 1],
        ['NB', 'Notebook', 'Notebook', 1],
        ['SV', 'Server', 'Server', 1],
        ['RT', 'Router', 'Router', 1],
        ['SW', 'Switch', 'Switch', 1],
        ['DV', 'DVR', 'DVR', 1],
        ['PR', 'Impresora', 'Printer', 1],
        ['DY', 'Monitor', 'Display', 0],
        ['UP', 'UPS', 'UPS', 0],
        ['VR', 'Estabilizador', 'Voltage Regulator', 0],
        ['MB', 'Celular', 'Mobile', 0],
    ];
    $st = $db->prepare('INSERT INTO tipos_equipo (codigo,nombre_es,nombre_en,lleva_hostname) VALUES (?,?,?,?)');
    foreach ($tipos as $t) {
        $st->execute($t);
    }

    $estados = [['En uso', 0], ['En depósito', 0], ['En reparación', 0], ['De baja', 1]];
    $st = $db->prepare('INSERT INTO estados (nombre, es_baja) VALUES (?,?)');
    foreach ($estados as $e) {
        $st->execute($e);
    }

    $st = $db->prepare('INSERT INTO servicios_remotos (nombre) VALUES (?)');
    foreach (['Anydesk', 'VNC', 'RDP', 'TeamViewer'] as $s) {
        $st->execute([$s]);
    }

    $st = $db->prepare('INSERT INTO tipos_componente (nombre) VALUES (?)');
    foreach (['CPU', 'Motherboard', 'RAM', 'Disco', 'GPU', 'Fuente'] as $c) {
        $st->execute([$c]);
    }

    // Usuario admin por defecto (CAMBIAR la clave en el primer ingreso)
    $db->prepare('INSERT INTO usuarios (usuario, hash_clave, nombre, rol) VALUES (?,?,?,?)')
       ->execute(['admin', password_hash('admin', PASSWORD_DEFAULT), 'Administrador', ROL_ADMIN]);

    // Áreas desde el organigrama, si está el CSV
    if (file_exists(CSV_AREAS)) {
        require_once __DIR__ . '/areas_import.php';
        importar_areas($db, CSV_AREAS);
    }
}

/**
 * Carga atributos sugeridos por tipo (editables luego). Idempotente: sólo
 * agrega los defaults a un tipo que todavía no tiene atributos definidos.
 */
function asegurar_atributos(PDO $db): void
{
    // [nombre, tipo_dato, opciones]
    $defaults = [
        'DK' => [['Sistema operativo', 'texto', '']],
        'NB' => [['Sistema operativo', 'texto', ''], ['Pantalla', 'texto', ''],
                 ['Batería', 'texto', ''], ['MAC WiFi', 'texto', '']],
        'SV' => [['Sistema operativo', 'texto', ''], ['Roles/Servicios', 'texto', ''],
                 ['RAID', 'texto', '']],
        'RT' => [['Firmware', 'texto', ''], ['Puertos LAN', 'numero', ''],
                 ['Usuario admin', 'texto', '']],
        'SW' => [['Puertos', 'numero', ''], ['Administrable', 'booleano', ''],
                 ['PoE', 'booleano', ''], ['VLANs', 'texto', '']],
        'DV' => [['Usuario', 'texto', ''], ['Cantidad de cámaras', 'numero', ''],
                 ['Almacenamiento', 'texto', ''], ['Días de grabación', 'numero', ''],
                 ['Marca de cámaras', 'texto', '']],
        'PR' => [['Tecnología', 'lista', 'Láser,Inyección,Sólida'], ['Color', 'booleano', ''],
                 ['Conectividad', 'lista', 'USB,Red,WiFi,USB+Red'], ['Dúplex', 'booleano', ''],
                 ['Contador de páginas', 'numero', ''], ['Modelo de tóner', 'texto', '']],
        'DY' => [['Pulgadas', 'texto', ''], ['Resolución', 'texto', ''], ['Panel', 'texto', '']],
        'UP' => [['Potencia (VA)', 'numero', ''], ['Tomas', 'numero', ''], ['Autonomía', 'texto', '']],
        'VR' => [['Potencia', 'texto', ''], ['Tomas', 'numero', '']],
        'MB' => [['IMEI', 'texto', ''], ['Línea/Número', 'texto', ''], ['Sistema operativo', 'texto', '']],
    ];
    $tipos = $db->query('SELECT id, codigo FROM tipos_equipo')->fetchAll();
    $cuenta = $db->prepare('SELECT COUNT(*) FROM atributos_tipo WHERE tipo_id=?');
    $ins = $db->prepare(
        'INSERT INTO atributos_tipo (tipo_id, nombre, tipo_dato, opciones, orden) VALUES (?,?,?,?,?)'
    );
    foreach ($tipos as $t) {
        if (empty($defaults[$t['codigo']])) {
            continue;
        }
        $cuenta->execute([$t['id']]);
        if ((int)$cuenta->fetchColumn() > 0) {
            continue; // ya tiene atributos: no piso
        }
        foreach ($defaults[$t['codigo']] as $o => [$nombre, $dato, $opc]) {
            $ins->execute([$t['id'], $nombre, $dato, $opc ?: null, $o]);
        }
    }
}
