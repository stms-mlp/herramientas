<?php
/**
 * Nominator — Tablas auxiliares: CRUD genérico para catálogos simples
 * (nombre + activo). Las tablas complejas (Modelos, Parámetros, Tipos) tienen
 * sus propias pantallas.
 *
 * El "whitelist" de aux_config() evita inyección: tabla y columna nunca vienen
 * del usuario, siempre de esta lista cerrada.
 */

declare(strict_types=1);

/** Catálogos editables con CRUD genérico (clave => definición). */
function aux_config(): array
{
    return [
        'estados'          => ['tabla' => 'estados',           'col' => 'nombre', 'label' => 'Estados',                  'grupo' => 'Equipos'],
        'tipos_componente' => ['tabla' => 'tipos_componente',  'col' => 'nombre', 'label' => 'Tipos de componente',      'grupo' => 'Equipos'],
        'servicios'        => ['tabla' => 'servicios_remotos', 'col' => 'nombre', 'label' => 'Servicios de acceso remoto', 'grupo' => 'Acceso remoto'],
        'marcas'           => ['tabla' => 'marcas',            'col' => 'nombre', 'label' => 'Marcas',                   'grupo' => 'Catálogo'],
    ];
}

function aux_def(string $key): ?array
{
    return aux_config()[$key] ?? null;
}

/** Filas de un catálogo (id, nombre, activo). */
function aux_items(array $def): array
{
    return db()->query(
        "SELECT id, {$def['col']} AS nombre, activo FROM {$def['tabla']} ORDER BY activo DESC, {$def['col']} COLLATE NOCASE"
    )->fetchAll();
}

/** Inserta o actualiza una fila. Devuelve [ok, mensaje]. */
function aux_guardar(array $def, int $id, string $nombre, int $activo): array
{
    $nombre = trim($nombre);
    if ($nombre === '') {
        return [false, 'El nombre es obligatorio.'];
    }
    try {
        if ($id) {
            db()->prepare("UPDATE {$def['tabla']} SET {$def['col']}=?, activo=? WHERE id=?")
                ->execute([$nombre, $activo, $id]);
            auditar($def['tabla'], $id, 'modificación', $nombre);
            return [true, 'Actualizado.'];
        }
        db()->prepare("INSERT INTO {$def['tabla']} ({$def['col']}, activo) VALUES (?,?)")
            ->execute([$nombre, $activo]);
        auditar($def['tabla'], (int)db()->lastInsertId(), 'alta', $nombre);
        return [true, 'Creado.'];
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            return [false, "Ya existe «{$nombre}»."];
        }
        return [false, 'No se pudo guardar.'];
    }
}

/** Borra una fila; si está referenciada, sugiere desactivar. */
function aux_borrar(array $def, int $id): array
{
    try {
        db()->prepare("DELETE FROM {$def['tabla']} WHERE id=?")->execute([$id]);
        auditar($def['tabla'], $id, 'baja', '');
        return [true, 'Eliminado.'];
    } catch (PDOException $e) {
        return [false, 'No se puede eliminar: está en uso. Desactivalo en su lugar.'];
    }
}
