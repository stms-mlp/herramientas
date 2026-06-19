<?php
/**
 * Nominator — Utilidades comunes (escape, CSRF, flash, permisos, máscara).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Escape HTML. */
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirección interna. */
function redir(string $ruta): never
{
    header('Location: ?r=' . $ruta);
    exit;
}

/** Mensaje flash (se muestra una vez). */
function flash(?string $msg = null, string $tipo = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Token CSRF para formularios. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    $tok = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$tok)) {
        http_response_code(400);
        exit('Token CSRF inválido.');
    }
}

/**
 * Nombre descriptivo del equipo a partir de marca/modelo.
 * Para equipos genéricos/armados (sin marca ni modelo) devuelve un rótulo
 * claro; el detalle real se ve en los componentes.
 */
function nombre_equipo(array $e): string
{
    $mm = trim(((string)($e['marca'] ?? '')) . ' ' . ((string)($e['modelo'] ?? '')));
    return $mm !== '' ? $mm : 'Genérico (armado)';
}

/** Enmascara un valor sensible (para rol lectura). */
function enmascarar(?string $v): string
{
    return ($v === null || $v === '') ? '' : '••••••';
}

/** Registra una acción en la auditoría. */
function auditar(string $entidad, ?int $id, string $accion, string $detalle = ''): void
{
    $db = db();
    $db->prepare('INSERT INTO auditoria (usuario, entidad, entidad_id, accion, detalle) VALUES (?,?,?,?,?)')
       ->execute([$_SESSION['usuario']['usuario'] ?? 'sistema', $entidad, $id, $accion, $detalle]);
}
