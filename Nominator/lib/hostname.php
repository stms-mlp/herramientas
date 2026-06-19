<?php
/**
 * Nominator — Motor de generación y validación de hostnames (NetBIOS/DNS).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Normaliza un texto a caracteres válidos NetBIOS/DNS: [A-Z0-9-]. */
function nm_normalizar(string $s): string
{
    $s = trim($s);
    // Reemplazo de acentos y ñ
    $map = [
        'á'=>'A','é'=>'E','í'=>'I','ó'=>'O','ú'=>'U','ü'=>'U','ñ'=>'N',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        'à'=>'A','è'=>'E','ì'=>'I','ò'=>'O','ù'=>'U',
    ];
    $s = strtr($s, $map);
    $s = strtoupper($s);
    $s = preg_replace('/[^A-Z0-9-]/', '-', $s);
    $s = preg_replace('/-+/', '-', $s);
    return trim($s, '-');
}

/**
 * Token de área a partir del código del organigrama.
 * Invierte la jerarquía y reemplaza '#' por '-': "DA#SGYA" -> "SGYA-DA".
 */
function nm_token_area(string $codigo): string
{
    $partes = array_map('trim', explode('#', $codigo));
    $partes = array_reverse($partes);
    $partes = array_filter($partes, fn($p) => $p !== '');
    return nm_normalizar(implode('-', $partes));
}

/** Devuelve sólo la dependencia hoja (último segmento) de un token de área. */
function nm_hoja_area(string $token_area): string
{
    $partes = explode('-', $token_area);
    return end($partes) ?: $token_area;
}

/**
 * Genera el mejor hostname posible respetando el límite de 15 caracteres.
 * Formato: {token_area}-{TIPO}{NNN}. Si excede, cae a la dependencia hoja.
 *
 * @return array{hostname:string, avisos:string[]}
 */
function nm_generar_hostname(string $token_area, string $tipo, int $correlativo): array
{
    $tipo = strtoupper($tipo);
    $num  = str_pad((string)$correlativo, 3, '0', STR_PAD_LEFT);
    $sufijo = '-' . $tipo . $num;
    $avisos = [];

    $hostname = $token_area . $sufijo;

    if (strlen($hostname) > HOST_MAX_NETBIOS) {
        $hoja = nm_hoja_area($token_area);
        $alt  = $hoja . $sufijo;
        $avisos[] = "El nombre completo «{$hostname}» supera los " . HOST_MAX_NETBIOS
            . " caracteres NetBIOS; se usa la dependencia hoja: «{$alt}».";
        $hostname = $alt;
    }

    if (strlen($hostname) > HOST_MAX_NETBIOS) {
        $recorte = substr($hostname, 0, HOST_MAX_NETBIOS);
        $recorte = rtrim($recorte, '-');
        $avisos[] = "Aún excede 15 caracteres; se recorta a «{$recorte}».";
        $hostname = $recorte;
    }

    return ['hostname' => $hostname, 'avisos' => $avisos];
}

/**
 * Valida un hostname contra reglas NetBIOS/DNS.
 *
 * @return string[] Lista de errores (vacía si es válido).
 */
function nm_validar_hostname(string $hostname): array
{
    $err = [];
    if ($hostname === '') {
        return ['El hostname está vacío.'];
    }
    if (!preg_match('/^[A-Z0-9-]+$/', $hostname)) {
        $err[] = 'Sólo se permiten letras A–Z, dígitos 0–9 y guion medio.';
    }
    if ($hostname[0] === '-' || substr($hostname, -1) === '-') {
        $err[] = 'No puede empezar ni terminar con guion.';
    }
    if (str_contains($hostname, '--')) {
        $err[] = 'No puede tener guiones consecutivos.';
    }
    if (strlen($hostname) > HOST_MAX_NETBIOS) {
        $err[] = 'Supera los ' . HOST_MAX_NETBIOS . ' caracteres (límite NetBIOS).';
    }
    if (strlen($hostname) > HOST_MAX_DNS) {
        $err[] = 'Supera los ' . HOST_MAX_DNS . ' caracteres (límite de etiqueta DNS).';
    }
    return $err;
}

/** Próximo correlativo para un área + tipo (por área y por tipo). */
function nm_proximo_correlativo(PDO $db, int $area_id, int $tipo_id): int
{
    $st = $db->prepare('SELECT COALESCE(MAX(correlativo),0)+1 FROM equipos WHERE area_id=? AND tipo_id=?');
    $st->execute([$area_id, $tipo_id]);
    return (int)$st->fetchColumn();
}
