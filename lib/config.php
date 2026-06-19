<?php
/**
 * Nominator — Configuración general.
 *
 * Sistema de nomenclatura e inventario de equipos.
 * Departamento de Sistemas — Municipalidad de Lago Puelo.
 */

declare(strict_types=1);

// --- Identidad institucional (para reportes y cabeceras) ---
const ORG_NOMBRE      = 'Municipalidad de Lago Puelo';
const ORG_DEPTO       = 'Departamento de Sistemas';
const ORG_PREFIJO     = 'MLP';            // prefijo de la organización
const ORG_ESCUDO      = 'assets/escudo.svg'; // placeholder; reemplazar por el escudo real

// --- Rutas ---
const BASE_DIR  = __DIR__ . '/..';
const DATOS_DIR = BASE_DIR . '/datos';
const DB_PATH   = DATOS_DIR . '/nominator.sqlite';
const CSV_AREAS = DATOS_DIR . '/areas_iniciales.csv';

// --- Reglas de nomenclatura ---
const HOST_MAX_NETBIOS = 15;   // límite duro NetBIOS
const HOST_MAX_DNS     = 63;   // límite de etiqueta DNS

// --- Roles ---
const ROL_ADMIN   = 'admin';
const ROL_TECNICO = 'tecnico';
const ROL_LECTURA = 'lectura';

/** Jerarquía de roles: mayor número = más permisos. */
function rol_nivel(string $rol): int
{
    return match ($rol) {
        ROL_ADMIN   => 3,
        ROL_TECNICO => 2,
        ROL_LECTURA => 1,
        default     => 0,
    };
}

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');
