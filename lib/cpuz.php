<?php
/**
 * Nominator — Parser "best effort" de reportes CPU-Z (.txt / .html) y HWMonitor.
 * Extrae los componentes principales: CPU, Motherboard, RAM, GPU y discos.
 */

declare(strict_types=1);

/**
 * @return array{componentes:array<int,array<string,string>>, avisos:string[]}
 */
function cpuz_parsear(string $txt): array
{
    $txt = str_replace(["\r\n", "\r"], "\n", $txt);

    // Si es un reporte HTML, lo paso a texto plano.
    if (stripos($txt, '<html') !== false || stripos($txt, '<td') !== false || stripos($txt, '<table') !== false) {
        $txt = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $txt) ?? $txt;
        $txt = preg_replace('#</(tr|p|div|h\d|li)>#i', "\n", $txt) ?? $txt;
        $txt = preg_replace('#</td>#i', "\t", $txt) ?? $txt;
        $txt = strip_tags($txt);
        $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Parseo por secciones (encabezado seguido de línea de guiones).
    $lineas = explode("\n", $txt);
    $secciones = [];
    $sec = '';
    $n = count($lineas);
    for ($i = 0; $i < $n; $i++) {
        $l = rtrim($lineas[$i]);
        $next = trim($lineas[$i + 1] ?? '');
        if (trim($l) !== '' && preg_match('/^[-=]{4,}$/', $next)) {
            $sec = trim($l);
            $secciones[$sec] = $secciones[$sec] ?? [];
            $i++; // saltar la línea de guiones
            continue;
        }
        if ($sec === '') {
            continue;
        }
        if (preg_match('/^\s*(.+?)(?:\t|\s{2,})(.+?)\s*$/', $l, $m)) {
            $secciones[$sec][] = [trim($m[1]), trim($m[2])];
        }
    }

    $buscarSec = function (string $contiene) use ($secciones): ?string {
        foreach ($secciones as $s => $_) {
            if (stripos($s, $contiene) !== false) {
                return $s;
            }
        }
        return null;
    };
    $valor = function (?string $sec, string $keyRegex) use ($secciones): ?string {
        if ($sec === null) {
            return null;
        }
        foreach ($secciones[$sec] ?? [] as [$k, $v]) {
            if (preg_match($keyRegex, $k)) {
                return $v;
            }
        }
        return null;
    };

    $comp = [];
    $base = fn(array $x = []) => array_merge(
        ['tipo' => '', 'marca' => '', 'modelo' => '', 'n_serie' => '', 'velocidad' => '', 'memoria' => '', 'bus' => ''],
        $x
    );

    // --- CPU ---
    $sp = $buscarSec('Processor');
    if ($sp) {
        $name  = $valor($sp, '/^Specification$/') ?? $valor($sp, '/^Name$/');
        $freq  = $valor($sp, '/^Stock frequency$/');
        $cores = $valor($sp, '/Number of cores/');
        if ($name) {
            $marca = stripos($name, 'intel') !== false ? 'Intel'
                   : (stripos($name, 'amd') !== false ? 'AMD' : '');
            $comp[] = $base([
                'tipo' => 'CPU', 'marca' => $marca, 'modelo' => $name,
                'velocidad' => $freq ?: '',
                'bus' => $cores ? ($cores . ' núcleos') : '',
            ]);
        }
    }

    // --- Motherboard ---
    $sm = $buscarSec('Mainboard');
    if ($sm) {
        $man = $valor($sm, '/^Manufacturer$/');
        $mod = $valor($sm, '/^Model$/');
        if ($man || $mod) {
            $comp[] = $base(['tipo' => 'Motherboard', 'marca' => $man ?: '', 'modelo' => $mod ?: '']);
        }
    }

    // --- Memoria (RAM) ---
    $smem = isset($secciones['Memory']) ? 'Memory' : $buscarSec('Memory');
    if ($smem) {
        $type = $valor($smem, '/Memory Type/');
        $size = $valor($smem, '/Memory Size/');
        $chan = $valor($smem, '/Channel/');
        $dram = $valor($smem, '/DRAM Frequency/');
        if ($type || $size) {
            $comp[] = $base([
                'tipo' => 'RAM', 'modelo' => $type ?: 'RAM',
                'memoria' => $size ?: '', 'velocidad' => $dram ?: '', 'bus' => $chan ?: '',
            ]);
        }
    }

    // --- GPU ---
    $sg = $buscarSec('Display Adapters') ?? $buscarSec('Graphic');
    if ($sg) {
        $gname = $valor($sg, '/^Name$/');
        if ($gname) {
            $comp[] = $base(['tipo' => 'GPU', 'modelo' => $gname,
                             'memoria' => $valor($sg, '/Memory size|Memory Size/') ?: '']);
        }
    }

    // --- Discos (Storage) ---
    $ss = $buscarSec('Storage');
    if ($ss) {
        foreach ($secciones[$ss] as [$k, $v]) {
            if (preg_match('/^(Drive|Model|Name)\b/i', $k) && strlen($v) > 3) {
                $comp[] = $base(['tipo' => 'Disco', 'modelo' => $v]);
            }
        }
    }

    $avisos = [];
    if (!$comp) {
        $avisos[] = 'No se reconocieron componentes. ¿Es un reporte de CPU-Z exportado en TXT o HTML?';
    }
    return ['componentes' => $comp, 'avisos' => $avisos];
}
