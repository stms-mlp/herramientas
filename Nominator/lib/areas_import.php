<?php
/**
 * Nominator — Importación del organigrama (CSV de reparticiones).
 *
 * Formato esperado (export del organigrama de la Municipalidad):
 *   Cód. de Repartición, Descripción, Estructura, Repartición Padre, Jurisdicción, ...
 *
 * Detecta y marca códigos duplicados (ej. el conocido DEPC#SGYA).
 */

declare(strict_types=1);

/**
 * @return array{insertadas:int, duplicados:string[]}
 */
function importar_areas(PDO $db, string $ruta_csv): array
{
    $fh = fopen($ruta_csv, 'r');
    if ($fh === false) {
        throw new RuntimeException("No se pudo abrir el CSV: {$ruta_csv}");
    }

    $insert = $db->prepare(
        'INSERT INTO areas (codigo, descripcion, estructura, codigo_padre, activa)
         VALUES (?,?,?,?,1)'
    );

    $vistos = [];      // codigo => cantidad
    $duplicados = [];
    $insertadas = 0;
    $primera = true;

    while (($fila = fgetcsv($fh)) !== false) {
        // Saltar encabezado
        if ($primera) {
            $primera = false;
            continue;
        }
        $codigo = trim((string)($fila[0] ?? ''));
        $desc   = trim((string)($fila[1] ?? ''));
        if ($codigo === '' || $desc === '') {
            continue; // filas vacías o de relleno
        }
        $estructura = trim((string)($fila[2] ?? ''));
        $padre      = trim((string)($fila[3] ?? ''));

        $insert->execute([$codigo, $desc, $estructura, $padre]);
        $insertadas++;

        $vistos[$codigo] = ($vistos[$codigo] ?? 0) + 1;
        if ($vistos[$codigo] === 2) {
            $duplicados[] = $codigo;
        }
    }
    fclose($fh);

    // Marcar duplicados para que se corrijan (generarían hostnames colisionantes)
    if ($duplicados) {
        $upd = $db->prepare('UPDATE areas SET duplicado=1 WHERE codigo=?');
        foreach ($duplicados as $cod) {
            $upd->execute([$cod]);
        }
    }

    return ['insertadas' => $insertadas, 'duplicados' => $duplicados];
}
