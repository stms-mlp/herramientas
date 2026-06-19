<?php
/**
 * Nominator — Autenticación, sesión y control de roles.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/** ¿El usuario actual tiene al menos el rol indicado? */
function puede(string $rol_minimo): bool
{
    $u = usuario_actual();
    return $u !== null && rol_nivel($u['rol']) >= rol_nivel($rol_minimo);
}

/** Exige sesión iniciada. */
function requiere_login(): void
{
    if (!usuario_actual()) {
        redir('login');
    }
}

/** Exige un rol mínimo; si no, corta. */
function requiere_rol(string $rol_minimo): void
{
    requiere_login();
    if (!puede($rol_minimo)) {
        http_response_code(403);
        exit('No tenés permisos para esta acción.');
    }
}

/** Intenta autenticar. Devuelve true si fue exitoso. */
function login(string $usuario, string $clave): bool
{
    $st = db()->prepare('SELECT * FROM usuarios WHERE usuario=? AND activo=1');
    $st->execute([$usuario]);
    $u = $st->fetch();
    if ($u && password_verify($clave, $u['hash_clave'])) {
        $_SESSION['usuario'] = [
            'id' => (int)$u['id'],
            'usuario' => $u['usuario'],
            'nombre' => $u['nombre'],
            'rol' => $u['rol'],
        ];
        session_regenerate_id(true);
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}
