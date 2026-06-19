<?php
/**
 * Nominator — Renderizado de vistas con layout.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Renderiza una vista (devuelve HTML como string). */
function vista(string $archivo, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include BASE_DIR . '/views/' . $archivo . '.php';
    return (string)ob_get_clean();
}

/** Envuelve el contenido en el layout principal (con menú y estética PCB). */
function pagina(string $titulo, string $contenido): void
{
    echo vista('layout', ['titulo' => $titulo, 'contenido' => $contenido]);
}

/** Layout de reporte (con cabecera institucional, sin menú; para imprimir). */
function reporte(string $titulo, string $contenido): void
{
    echo vista('reporte', ['titulo' => $titulo, 'contenido' => $contenido]);
}
