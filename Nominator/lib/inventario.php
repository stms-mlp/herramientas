<?php
/**
 * Nominator — Helpers de inventario: catálogo de marcas/modelos, componentes
 * y atributos dinámicos por tipo de equipo.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// ----------------- Catálogo de valores reutilizables -----------------

function cat_add(string $campo, ?string $valor): void
{
    $valor = trim((string)$valor);
    if ($valor === '') {
        return;
    }
    db()->prepare('INSERT OR IGNORE INTO catalogo (campo, valor) VALUES (?, ?)')
        ->execute([$campo, $valor]);
}

/** Lista de valores únicos del catálogo para uno o varios campos. */
function cat_union(array $campos): array
{
    $in = implode(',', array_fill(0, count($campos), '?'));
    $st = db()->prepare("SELECT DISTINCT valor FROM catalogo WHERE campo IN ($in) ORDER BY valor COLLATE NOCASE");
    $st->execute($campos);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

// ----------------- Componentes -----------------

function componentes_de(int $equipoId): array
{
    $st = db()->prepare('SELECT * FROM componentes WHERE equipo_id=? ORDER BY id');
    $st->execute([$equipoId]);
    return $st->fetchAll();
}

/** Tipo corto de disco a partir del bus ("Fixed, SSD NVMe" -> "NVMe"). */
function _tipo_disco(string $bus): string
{
    if (stripos($bus, 'NVMe') !== false) { return 'NVMe'; }
    if (stripos($bus, 'SSD') !== false)  { return 'SSD'; }
    if (stripos($bus, 'HDD') !== false || stripos($bus, 'ATA') !== false) { return 'HDD'; }
    return 'Disco';
}

/**
 * Resumen de hardware en una línea, con las partes INDIVIDUALIZADAS
 * (cada módulo de RAM y cada disco por separado, no totalizados).
 * Ej.: "Ryzen 7 5700X · 8 GB DDR4 · 8 GB DDR4 · SSD 894 GB · NVMe 477 GB · RTX 3050".
 */
function resumen_hardware(int $equipoId): string
{
    $comps = componentes_de($equipoId);
    if (!$comps) {
        return '';
    }
    $cpu = []; $ram = []; $disco = []; $gpu = [];
    foreach ($comps as $c) {
        switch ($c['tipo']) {
            case 'CPU':
                $cpu[] = trim(preg_replace('/\b\d+-Core Processor\b/i', '', $c['modelo']));
                break;
            case 'RAM':
                $ram[] = trim(($c['memoria'] ?: 'RAM') . ($c['bus'] ? " {$c['bus']}" : ''));
                break;
            case 'Disco':
                $disco[] = trim(_tipo_disco($c['bus']) . ' ' . $c['memoria']);
                break;
            case 'GPU':
                $gpu[] = $c['modelo'];
                break;
        }
    }
    return implode(' · ', array_filter(array_merge($cpu, $ram, $disco, $gpu)));
}

/**
 * Guarda los componentes de un equipo desde arrays paralelos del POST.
 * Reemplaza los existentes. Espera claves comp_tipo[], comp_marca[], etc.
 */
function guardar_componentes(PDO $db, int $equipoId, array $datos): void
{
    $db->prepare('DELETE FROM componentes WHERE equipo_id=?')->execute([$equipoId]);
    $tipos = $datos['comp_tipo'] ?? [];
    if (!is_array($tipos)) {
        return;
    }
    $ins = $db->prepare(
        'INSERT INTO componentes (equipo_id,tipo,marca,modelo,n_serie,velocidad,memoria,bus)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    foreach ($tipos as $i => $tipo) {
        $fila = [
            'tipo'      => trim((string)$tipo),
            'marca'     => trim((string)($datos['comp_marca'][$i] ?? '')),
            'modelo'    => trim((string)($datos['comp_modelo'][$i] ?? '')),
            'n_serie'   => trim((string)($datos['comp_serie'][$i] ?? '')),
            'velocidad' => trim((string)($datos['comp_velocidad'][$i] ?? '')),
            'memoria'   => trim((string)($datos['comp_memoria'][$i] ?? '')),
            'bus'       => trim((string)($datos['comp_bus'][$i] ?? '')),
        ];
        if (implode('', $fila) === '') {
            continue; // fila vacía
        }
        $ins->execute(array_merge([$equipoId], array_values($fila)));
        cat_add('comp_marca', $fila['marca']);
        cat_add('comp_modelo', $fila['modelo']);
    }
}

// ----------------- Atributos dinámicos por tipo -----------------

/** Atributos definidos para un tipo de equipo. */
function atributos_de_tipo(int $tipoId): array
{
    $st = db()->prepare('SELECT * FROM atributos_tipo WHERE tipo_id=? ORDER BY orden, id');
    $st->execute([$tipoId]);
    return $st->fetchAll();
}

/** Mapa tipo_id => lista de atributos (para embeber como JSON en el formulario). */
function atributos_mapa(): array
{
    $rows = db()->query('SELECT * FROM atributos_tipo ORDER BY tipo_id, orden, id')->fetchAll();
    $mapa = [];
    foreach ($rows as $a) {
        $mapa[(int)$a['tipo_id']][] = [
            'id'        => (int)$a['id'],
            'nombre'    => $a['nombre'],
            'tipo_dato' => $a['tipo_dato'],
            'opciones'  => $a['opciones'],
            'obligatorio' => (int)$a['obligatorio'],
        ];
    }
    return $mapa;
}

/** Valores de atributos cargados para un equipo (id_atributo => valor). */
function valores_de(int $equipoId): array
{
    $st = db()->prepare('SELECT atributo_id, valor FROM valores_atributo WHERE equipo_id=?');
    $st->execute([$equipoId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[(int)$r['atributo_id']] = $r['valor'];
    }
    return $out;
}

/** Valores con nombre, para mostrar en la ficha (lista de [nombre, valor]). */
function valores_legibles(int $equipoId): array
{
    $st = db()->prepare(
        'SELECT a.nombre, v.valor FROM valores_atributo v
         JOIN atributos_tipo a ON a.id=v.atributo_id
         WHERE v.equipo_id=? AND TRIM(COALESCE(v.valor,\'\'))<>\'\'
         ORDER BY a.orden, a.id'
    );
    $st->execute([$equipoId]);
    return $st->fetchAll();
}

/**
 * Guarda los valores de atributos de un equipo. $valores = [atributo_id => valor].
 * Sólo conserva atributos que pertenecen al tipo del equipo.
 */
function guardar_atributos(PDO $db, int $equipoId, int $tipoId, array $valores): void
{
    $db->prepare('DELETE FROM valores_atributo WHERE equipo_id=?')->execute([$equipoId]);
    $validos = [];
    foreach (atributos_de_tipo($tipoId) as $a) {
        $validos[(int)$a['id']] = true;
    }
    $ins = $db->prepare('INSERT INTO valores_atributo (equipo_id, atributo_id, valor) VALUES (?,?,?)');
    foreach ($valores as $aid => $val) {
        $aid = (int)$aid;
        $val = trim((string)$val);
        if ($val === '' || !isset($validos[$aid])) {
            continue;
        }
        $ins->execute([$equipoId, $aid, $val]);
    }
}
