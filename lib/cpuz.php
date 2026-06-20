<?php
/**
 * Nominator — Parser "best effort" de reportes CPU-Z (.txt / .html) y HWMonitor.
 *
 * Extrae los componentes relevantes (CPU, Motherboard, RAM por módulo, discos y
 * GPU) usando los bloques DMI y de secciones del reporte. Ignora lo que no
 * reconoce (SMART, P-States, USB, sensores, caches, etc.).
 */

declare(strict_types=1);

/** Pasa a UTF-8 (los reportes de CPU-Z suelen venir en Windows-1252). */
function cpuz_utf8(string $raw): string
{
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }
    return str_replace(["\r\n", "\r"], "\n", $raw);
}

/** Marca a partir de un nombre/fabricante (primer token, normalizado). */
function cpuz_marca(string $nombre): string
{
    $nombre = trim($nombre);
    if ($nombre === '' || strcasecmp($nombre, 'Unknown') === 0) {
        return '';
    }
    $mapa = [
        'AuthenticAMD' => 'AMD', 'GenuineIntel' => 'Intel', 'WDC' => 'Western Digital',
    ];
    $primero = strtok($nombre, ' ');
    if (isset($mapa[$primero])) {
        return $mapa[$primero];
    }
    return ctype_upper($primero) ? ucfirst(strtolower($primero)) : $primero;
}

/**
 * @return array{componentes:array<int,array<string,string>>, avisos:string[]}
 */
function cpuz_parsear(string $raw): array
{
    $raw = cpuz_utf8($raw);

    if (stripos($raw, '<table') !== false || stripos($raw, '<html') !== false) {
        $raw = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $raw) ?? $raw;
        $raw = preg_replace('#</tr>#i', "\n", $raw) ?? $raw;
        $raw = preg_replace('#</t[dh]>#i', "\t", $raw) ?? $raw;
        $raw = strip_tags($raw);
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $lineas = explode("\n", $raw);
    $n = count($lineas);
    $seccion = '';
    $procesadores = []; $baseboard = []; $memdev = []; $drives = []; $display = []; $memgen = [];
    $cur = [];        // bloque actual (por valor, sin referencias)
    $curTipo = '';

    $kv = function (string $linea): ?array {
        // "clave <TAB o 2+ espacios> valor". El separador NO puede ser un espacio
        // simple, para no partir claves como "serial number" o "Bus Type".
        if (preg_match('/^[ \t]+(\S.*?)(?:\t+|[ ]{2,})(\S.*?)\s*$/', $linea, $m)) {
            return [preg_replace('/\s+/', ' ', trim($m[1])), trim($m[2])];
        }
        return null;
    };

    // Vuelca el bloque acumulado a su lista y resetea.
    $flush = function () use (&$cur, &$curTipo, &$procesadores, &$baseboard, &$memdev, &$drives, &$display) {
        if ($curTipo !== '' && $cur) {
            switch ($curTipo) {
                case 'cpu':   $procesadores[] = $cur; break;
                case 'mb':    $baseboard = $cur; break;
                case 'ram':   $memdev[] = $cur; break;
                case 'drive': $drives[] = $cur; break;
                case 'gpu':   $display[] = $cur; break;
            }
        }
        $cur = []; $curTipo = '';
    };

    for ($i = 0; $i < $n; $i++) {
        $linea = rtrim($lineas[$i], "\n");
        $trim = trim($linea);
        $sig = $i + 1 < $n ? trim($lineas[$i + 1]) : '';

        if ($trim !== '' && preg_match('/^[-=]{4,}$/', $sig)) {
            $flush(); $seccion = $trim; $i++; continue;
        }

        if (preg_match('/^Socket \d+/', $trim) && stripos($seccion, 'Processors') !== false) {
            $flush(); $curTipo = 'cpu'; continue;
        }
        if (preg_match('/^DMI Baseboard/i', $trim))      { $flush(); $curTipo = 'mb'; continue; }
        if (preg_match('/^DMI Memory Device/i', $trim))  { $flush(); $curTipo = 'ram'; continue; }
        if (preg_match('/^Drive\b/', $trim) && stripos($seccion, 'Storage') !== false) {
            $flush(); $curTipo = 'drive'; continue;
        }
        if (preg_match('/^Display adapter \d+/i', $trim)) { $flush(); $curTipo = 'gpu'; continue; }
        if (preg_match('/^DMI /i', $trim))                { $flush(); continue; }

        if ($curTipo !== '' && ($p = $kv($linea))) {
            [$k, $v] = $p;
            if (!isset($cur[$k])) { $cur[$k] = $v; }
        } elseif (stripos($seccion, 'Memory') === 0 && ($p = $kv($linea))) {
            [$k, $v] = $p;
            if (in_array($k, ['Memory Type', 'Memory Size'], true) && !isset($memgen[$k])) {
                $memgen[$k] = $v;
            }
        }
    }
    $flush();

    $comp = [];
    $base = fn(array $x) => array_merge(
        ['tipo' => '', 'marca' => '', 'modelo' => '', 'n_serie' => '', 'velocidad' => '', 'memoria' => '', 'bus' => ''],
        $x
    );
    $limpio = fn(?string $v) => ($v === null || strcasecmp($v, 'Unknown') === 0 || strcasecmp($v, 'unknown') === 0) ? '' : $v;

    // --- CPU (elegir el bloque que tenga nombre real) ---
    if ($procesadores) {
        $p = [];
        foreach ($procesadores as $bloque) {
            if (isset($bloque['Name']) || isset($bloque['Specification'])) { $p = $bloque; break; }
        }
        $nombre = $p['Name'] ?? $p['Specification'] ?? '';
        if ($nombre !== '') {
            $cores = $p['Number of cores'] ?? '';
            $threads = $p['Number of threads'] ?? '';
            if (preg_match('/^\d+/', $cores, $mm)) { $cores = $mm[0]; }
            if (preg_match('/^\d+/', $threads, $mm)) { $threads = $mm[0]; }
            $bus = trim(($cores ? "$cores núcleos" : '') . ($threads ? " / $threads hilos" : ''));
            $comp[] = $base([
                'tipo' => 'CPU',
                'marca' => cpuz_marca($p['Manufacturer'] ?? $nombre),
                'modelo' => $nombre,
                'velocidad' => $p['Max Frequency'] ?? $p['Stock frequency'] ?? $p['Core Speed'] ?? '',
                'bus' => $bus,
            ]);
        }
    }

    // --- Motherboard (DMI Baseboard) ---
    if ($baseboard && (($baseboard['model'] ?? '') !== '' || ($baseboard['vendor'] ?? '') !== '')) {
        $comp[] = $base([
            'tipo' => 'Motherboard',
            'marca' => $limpio($baseboard['vendor'] ?? ''),
            'modelo' => $limpio($baseboard['model'] ?? ''),
            'n_serie' => $limpio($baseboard['serial'] ?? ''),
        ]);
    }

    // --- RAM (módulos poblados, dedup por serie) ---
    $vistos = [];
    foreach ($memdev as $m) {
        $size = $m['size'] ?? '';
        if ($size === '' || stripos($size, 'unknown') !== false) { continue; }
        $serie = $limpio($m['serial number'] ?? '');
        if ($serie !== '' && isset($vistos[$serie])) { continue; }
        if ($serie !== '') { $vistos[$serie] = true; }
        $comp[] = $base([
            'tipo' => 'RAM',
            'marca' => $limpio($m['manufacturer'] ?? ''),
            'modelo' => $limpio($m['part number'] ?? ''),
            'n_serie' => $serie,
            'velocidad' => $m['speed'] ?? '',
            'memoria' => $size,
            'bus' => $limpio($m['type'] ?? ''),
        ]);
    }
    // Si no hubo módulos detallados, al menos la memoria general.
    if (!$vistos && $memgen) {
        $comp[] = $base([
            'tipo' => 'RAM',
            'modelo' => $memgen['Memory Type'] ?? 'RAM',
            'memoria' => $memgen['Memory Size'] ?? '',
            'bus' => $memgen['Memory Type'] ?? '',
        ]);
    }

    // --- Discos ---
    foreach ($drives as $d) {
        $nombre = $d['Name'] ?? '';
        if ($nombre === '') { continue; }
        $comp[] = $base([
            'tipo' => 'Disco',
            'marca' => cpuz_marca($nombre),
            'modelo' => $nombre,
            'n_serie' => $limpio($d['Serial'] ?? ''),
            'memoria' => $d['Capacity'] ?? '',
            // "Fixed, SSD" + "SATA (11)" -> "SSD SATA"
            'bus' => trim(preg_replace(['/Fixed,\s*/', '/\s*\(\d+\)/'], '', ($d['Type'] ?? '') . ' ' . ($d['Bus Type'] ?? ''))),
        ]);
    }

    // --- GPU ---
    if ($display) {
        $g = $display[0];
        $nombre = $g['Name'] ?? '';
        if ($nombre !== '') {
            // "Micro-Star International Co., Ltd. (MSI)" -> "MSI"
            $board = $limpio($g['Board Manufacturer'] ?? '');
            if ($board !== '' && preg_match('/\(([^)]+)\)/', $board, $mm)) { $board = $mm[1]; }
            $comp[] = $base([
                'tipo' => 'GPU',
                'marca' => $board ?: cpuz_marca($nombre),
                'modelo' => $nombre,
                'memoria' => trim(($g['Memory size'] ?? '') . ' ' . ($g['Memory type'] ?? '')),
            ]);
        }
    }

    $avisos = [];
    if (!$comp) {
        $avisos[] = 'No se reconocieron componentes. ¿Es un reporte de CPU-Z exportado en TXT o HTML?';
    }
    return ['componentes' => $comp, 'avisos' => $avisos];
}
