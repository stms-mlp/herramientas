<?php
/**
 * Nominator — Front controller (ruteo por query-string: index.php?r=...).
 *
 * Sistema de nomenclatura e inventario de equipos.
 * Departamento de Sistemas — Municipalidad de Lago Puelo.
 */

declare(strict_types=1);

session_start();

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/hostname.php';
require_once __DIR__ . '/lib/view.php';

db(); // inicializa esquema / semillas en el primer acceso

$r = $_GET['r'] ?? 'dashboard';
$post = ($_SERVER['REQUEST_METHOD'] === 'POST');

switch ($r) {

    // ---------- Autenticación ----------
    case 'login':
        if ($post) {
            csrf_check();
            if (login(trim($_POST['usuario'] ?? ''), $_POST['clave'] ?? '')) {
                redir('dashboard');
            }
            flash('Usuario o clave incorrectos.', 'error');
            redir('login');
        }
        echo vista('login', ['flash' => flash()]);
        break;

    case 'logout':
        logout();
        redir('login');
        break;

    // ---------- Dashboard ----------
    case 'dashboard':
        requiere_login();
        $db = db();
        $stats = [
            'equipos' => (int)$db->query('SELECT COUNT(*) FROM equipos')->fetchColumn(),
            'areas'   => (int)$db->query('SELECT COUNT(*) FROM areas WHERE activa=1')->fetchColumn(),
            'tipos'   => (int)$db->query('SELECT COUNT(*) FROM tipos_equipo WHERE activo=1')->fetchColumn(),
            'duplic'  => (int)$db->query('SELECT COUNT(*) FROM areas WHERE duplicado=1')->fetchColumn(),
        ];
        $porArea = $db->query(
            'SELECT a.descripcion, COUNT(e.id) n
             FROM areas a LEFT JOIN equipos e ON e.area_id=a.id
             WHERE a.activa=1 GROUP BY a.id HAVING n>0 ORDER BY n DESC LIMIT 10'
        )->fetchAll();
        pagina('Tablero', vista('dashboard', ['stats' => $stats, 'porArea' => $porArea, 'flash' => flash()]));
        break;

    // ---------- Áreas ----------
    case 'areas':
        requiere_login();
        $areas = db()->query('SELECT * FROM areas ORDER BY codigo')->fetchAll();
        pagina('Reparticiones', vista('areas_list', ['areas' => $areas, 'flash' => flash()]));
        break;

    // ---------- Equipos ----------
    case 'equipos':
        requiere_login();
        $db = db();
        $filtroArea = isset($_GET['area']) ? (int)$_GET['area'] : 0;
        $sql = 'SELECT e.*, t.codigo tcod, t.nombre_es tnom, a.descripcion adesc, a.codigo acod, s.nombre enom
                FROM equipos e
                JOIN tipos_equipo t ON t.id=e.tipo_id
                LEFT JOIN areas a ON a.id=e.area_id
                LEFT JOIN estados s ON s.id=e.estado_id';
        $args = [];
        if ($filtroArea) {
            $sql .= ' WHERE e.area_id=?';
            $args[] = $filtroArea;
        }
        $sql .= ' ORDER BY a.codigo, t.codigo, e.correlativo';
        $st = $db->prepare($sql);
        $st->execute($args);
        $equipos = $st->fetchAll();
        $areas = $db->query('SELECT id, codigo, descripcion FROM areas WHERE activa=1 ORDER BY codigo')->fetchAll();
        pagina('Equipos', vista('equipos_list', [
            'equipos' => $equipos, 'areas' => $areas, 'filtroArea' => $filtroArea, 'flash' => flash(),
        ]));
        break;

    case 'equipos.nuevo':
        requiere_rol(ROL_TECNICO);
        $db = db();
        $areas  = $db->query('SELECT id, codigo, descripcion FROM areas WHERE activa=1 ORDER BY codigo')->fetchAll();
        $tipos  = $db->query('SELECT * FROM tipos_equipo WHERE activo=1 ORDER BY nombre_es')->fetchAll();
        $estados = $db->query('SELECT * FROM estados WHERE activo=1 ORDER BY id')->fetchAll();
        pagina('Nuevo equipo', vista('equipo_form', [
            'areas' => $areas, 'tipos' => $tipos, 'estados' => $estados, 'flash' => flash(),
        ]));
        break;

    case 'equipos.preview': // AJAX: previsualización del hostname
        requiere_login();
        header('Content-Type: application/json');
        $db = db();
        $areaId = (int)($_GET['area'] ?? 0);
        $tipoId = (int)($_GET['tipo'] ?? 0);
        $resp = ['hostname' => '', 'avisos' => [], 'error' => ''];
        $area = $db->query('SELECT * FROM areas WHERE id=' . $areaId)->fetch();
        $tipo = $db->query('SELECT * FROM tipos_equipo WHERE id=' . $tipoId)->fetch();
        if (!$area || !$tipo) {
            $resp['error'] = 'Seleccioná área y tipo.';
        } elseif (!$tipo['lleva_hostname']) {
            $resp['error'] = 'Este tipo (' . $tipo['nombre_es'] . ') no lleva hostname; se asocia a un equipo padre.';
        } else {
            $corr = nm_proximo_correlativo($db, $areaId, $tipoId);
            $gen = nm_generar_hostname(nm_token_area($area['codigo']), $tipo['codigo'], $corr);
            $resp['hostname'] = $gen['hostname'];
            $resp['avisos'] = $gen['avisos'];
            if ($area['duplicado']) {
                $resp['avisos'][] = 'El código de área «' . $area['codigo'] . '» está marcado como duplicado en el organigrama: revisar antes de asignar.';
            }
        }
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        break;

    case 'equipos.crear':
        requiere_rol(ROL_TECNICO);
        csrf_check();
        $db = db();
        $tipoId = (int)$_POST['tipo_id'];
        $areaId = (int)$_POST['area_id'];
        $tipo = $db->query('SELECT * FROM tipos_equipo WHERE id=' . $tipoId)->fetch();
        $area = $db->query('SELECT * FROM areas WHERE id=' . $areaId)->fetch();

        $hostname = null;
        $corr = null;
        if ($tipo && $area && $tipo['lleva_hostname']) {
            $corr = nm_proximo_correlativo($db, $areaId, $tipoId);
            $gen = nm_generar_hostname(nm_token_area($area['codigo']), $tipo['codigo'], $corr);
            $hostname = $gen['hostname'];
        }

        $db->prepare(
            'INSERT INTO equipos
             (id_patrimonial, hostname, tipo_id, area_id, estado_id, correlativo,
              marca, modelo, n_serie, n_parte, ip, titularidad, tenencia, tenedor,
              responsable, observaciones, notas_tecnicas)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            trim($_POST['id_patrimonial'] ?? '') ?: null,
            $hostname, $tipoId, $areaId, (int)$_POST['estado_id'] ?: null, $corr,
            trim($_POST['marca'] ?? ''), trim($_POST['modelo'] ?? ''),
            trim($_POST['n_serie'] ?? ''), trim($_POST['n_parte'] ?? ''),
            trim($_POST['ip'] ?? ''),
            $_POST['titularidad'] ?? 'Municipal', $_POST['tenencia'] ?? 'En sede',
            trim($_POST['tenedor'] ?? ''), trim($_POST['responsable'] ?? ''),
            trim($_POST['observaciones'] ?? ''), trim($_POST['notas_tecnicas'] ?? ''),
        ]);
        $id = (int)$db->lastInsertId();
        auditar('equipo', $id, 'alta', $hostname ?? ('id_patrimonial ' . ($_POST['id_patrimonial'] ?? '')));
        flash('Equipo creado' . ($hostname ? " como «{$hostname}»." : '.'));
        redir('equipos.ver&id=' . $id);
        break;

    case 'equipos.ver':
        requiere_login();
        $db = db();
        $id = (int)($_GET['id'] ?? 0);
        $st = $db->prepare(
            'SELECT e.*, t.codigo tcod, t.nombre_es tnom, a.descripcion adesc, a.codigo acod, s.nombre enom
             FROM equipos e
             JOIN tipos_equipo t ON t.id=e.tipo_id
             LEFT JOIN areas a ON a.id=e.area_id
             LEFT JOIN estados s ON s.id=e.estado_id
             WHERE e.id=?'
        );
        $st->execute([$id]);
        $eq = $st->fetch();
        if (!$eq) {
            http_response_code(404);
            pagina('No encontrado', '<p>Equipo inexistente.</p>');
            break;
        }
        $comp = $db->prepare('SELECT * FROM componentes WHERE equipo_id=?');
        $comp->execute([$id]);
        pagina('Equipo ' . ($eq['hostname'] ?? $eq['id']), vista('equipo_show', [
            'eq' => $eq, 'componentes' => $comp->fetchAll(), 'flash' => flash(),
        ]));
        break;

    // ---------- Reportes ----------
    case 'reporte.ficha':
        requiere_login();
        $db = db();
        $id = (int)($_GET['id'] ?? 0);
        $st = $db->prepare(
            'SELECT e.*, t.nombre_es tnom, a.descripcion adesc, a.codigo acod, s.nombre enom
             FROM equipos e JOIN tipos_equipo t ON t.id=e.tipo_id
             LEFT JOIN areas a ON a.id=e.area_id LEFT JOIN estados s ON s.id=e.estado_id
             WHERE e.id=?'
        );
        $st->execute([$id]);
        $eq = $st->fetch();
        if (!$eq) { http_response_code(404); exit('Equipo inexistente.'); }
        $comp = $db->prepare('SELECT * FROM componentes WHERE equipo_id=?');
        $comp->execute([$id]);
        reporte('Ficha de hardware', vista('rep_ficha', ['eq' => $eq, 'componentes' => $comp->fetchAll()]));
        break;

    case 'reporte.area': // extracto para declaración de inventario
        requiere_login();
        $db = db();
        $areaId = (int)($_GET['area'] ?? 0);
        $area = $db->query('SELECT * FROM areas WHERE id=' . $areaId)->fetch();
        if (!$area) { http_response_code(404); exit('Área inexistente.'); }
        $st = $db->prepare(
            "SELECT e.*, t.nombre_es tnom FROM equipos e JOIN tipos_equipo t ON t.id=e.tipo_id
             WHERE e.area_id=? AND e.titularidad='Municipal'
               AND COALESCE(e.baja_fecha,'')=''
             ORDER BY t.nombre_es, e.correlativo"
        );
        $st->execute([$areaId]);
        reporte('Extracto de inventario', vista('rep_area', ['area' => $area, 'equipos' => $st->fetchAll()]));
        break;

    default:
        http_response_code(404);
        pagina('No encontrado', '<p>Página inexistente.</p>');
}
