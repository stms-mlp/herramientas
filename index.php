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
        $todas = db()->query('SELECT * FROM areas ORDER BY codigo')->fetchAll();
        // Agrupar por secretaría = raíz del código (último segmento tras '#')
        $titulos = [];
        foreach ($todas as $a) {
            $titulos[$a['codigo']] = $a['descripcion'];
        }
        $grupos = [];
        foreach ($todas as $a) {
            $partes = explode('#', $a['codigo']);
            $raiz = trim((string)end($partes));
            if (!isset($grupos[$raiz])) {
                $grupos[$raiz] = ['codigo' => $raiz, 'titulo' => $titulos[$raiz] ?? $raiz,
                                  'areas' => [], 'duplic' => 0];
            }
            $grupos[$raiz]['areas'][] = $a;
            if ($a['duplicado']) {
                $grupos[$raiz]['duplic']++;
            }
        }
        // INT (Intendencia) primero; luego alfabético por raíz
        uksort($grupos, fn($x, $y) => $x === 'INT' ? -1 : ($y === 'INT' ? 1 : strcmp($x, $y)));
        $sec = trim((string)($_GET['sec'] ?? ''));
        pagina('Reparticiones', vista('areas_list', ['grupos' => $grupos, 'sec' => $sec, 'flash' => flash()]));
        break;

    case 'areas.nueva':
    case 'areas.editar':
        requiere_rol(ROL_ADMIN);
        $db = db();
        $area = null;
        if ($r === 'areas.editar') {
            $st = $db->prepare('SELECT * FROM areas WHERE id=?');
            $st->execute([(int)($_GET['id'] ?? 0)]);
            $area = $st->fetch();
            if (!$area) { http_response_code(404); pagina('No encontrado', '<p>Repartición inexistente.</p>'); break; }
        }
        $padres = $db->query('SELECT codigo, descripcion FROM areas ORDER BY codigo')->fetchAll();
        pagina($area ? 'Editar repartición' : 'Nueva repartición',
            vista('area_form', ['area' => $area, 'padres' => $padres, 'flash' => flash()]));
        break;

    case 'areas.crear':
    case 'areas.actualizar':
        requiere_rol(ROL_ADMIN);
        csrf_check();
        $db = db();
        $editar = ($r === 'areas.actualizar');
        $id = (int)($_POST['id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $desc   = trim($_POST['descripcion'] ?? '');
        if ($codigo === '' || $desc === '') {
            flash('Código y descripción son obligatorios.', 'error');
            redir($editar ? 'areas.editar&id=' . $id : 'areas.nueva');
        }
        $campos = [
            'codigo'       => $codigo,
            'descripcion'  => $desc,
            'estructura'   => trim($_POST['estructura'] ?? ''),
            'codigo_padre' => trim($_POST['codigo_padre'] ?? ''),
            'abreviatura'  => trim($_POST['abreviatura'] ?? ''),
            'ubicacion'    => trim($_POST['ubicacion'] ?? ''),
            'activa'       => isset($_POST['activa']) ? 1 : 0,
        ];
        if ($editar) {
            $set = implode(', ', array_map(fn($c) => "$c=?", array_keys($campos)));
            $args = array_values($campos);
            $args[] = $id;
            $db->prepare("UPDATE areas SET $set WHERE id=?")->execute($args);
            auditar('area', $id, 'modificación', $codigo);
            flash('Repartición actualizada.');
        } else {
            $cols = implode(',', array_keys($campos));
            $ph = implode(',', array_fill(0, count($campos), '?'));
            $db->prepare("INSERT INTO areas ($cols) VALUES ($ph)")->execute(array_values($campos));
            $id = (int)$db->lastInsertId();
            auditar('area', $id, 'alta', $codigo);
            flash('Repartición creada.');
        }
        redir('areas');
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
    case 'equipos.editar':
        requiere_rol(ROL_TECNICO);
        $db = db();
        $eq = null;
        if ($r === 'equipos.editar') {
            $st = $db->prepare('SELECT * FROM equipos WHERE id=?');
            $st->execute([(int)($_GET['id'] ?? 0)]);
            $eq = $st->fetch();
            if (!$eq) { http_response_code(404); pagina('No encontrado', '<p>Equipo inexistente.</p>'); break; }
        }
        // Recupera datos del formulario si hubo error de validación
        $old = $_SESSION['form_old'] ?? null;
        unset($_SESSION['form_old']);
        $areas  = $db->query('SELECT id, codigo, descripcion FROM areas WHERE activa=1 ORDER BY codigo')->fetchAll();
        $tipos  = $db->query('SELECT * FROM tipos_equipo WHERE activo=1 ORDER BY nombre_es')->fetchAll();
        $estados = $db->query('SELECT * FROM estados WHERE activo=1 ORDER BY id')->fetchAll();
        pagina($eq ? 'Editar equipo' : 'Nuevo equipo', vista('equipo_form', [
            'eq' => $eq, 'old' => $old, 'areas' => $areas, 'tipos' => $tipos,
            'estados' => $estados, 'flash' => flash(),
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
    case 'equipos.actualizar':
        requiere_rol(ROL_TECNICO);
        csrf_check();
        $db = db();
        $editar = ($r === 'equipos.actualizar');
        $id = (int)($_POST['id'] ?? 0);
        $tipoId = (int)$_POST['tipo_id'];
        $areaId = (int)$_POST['area_id'];
        $tipo = $db->query('SELECT * FROM tipos_equipo WHERE id=' . $tipoId)->fetch();
        $area = $areaId ? $db->query('SELECT * FROM areas WHERE id=' . $areaId)->fetch() : null;

        if (!$tipo) {
            flash('Seleccioná un tipo de equipo válido.', 'error');
            redir($editar ? 'equipos.editar&id=' . $id : 'equipos.nuevo');
        }

        // Hostname editable: vacío = recomendación automática; cargado = se respeta y valida.
        $res = nm_resolver_hostname($db, $tipo, $area, $_POST['hostname'] ?? '', $editar ? $id : null);
        if ($res['errores']) {
            $_SESSION['form_old'] = $_POST;
            flash(implode(' ', $res['errores']), 'error');
            redir($editar ? 'equipos.editar&id=' . $id : 'equipos.nuevo');
        }
        $hostname = $res['hostname'];

        $campos = [
            'id_patrimonial' => trim($_POST['id_patrimonial'] ?? '') ?: null,
            'hostname'    => $hostname,
            'tipo_id'     => $tipoId,
            'area_id'     => $areaId ?: null,
            'estado_id'   => (int)($_POST['estado_id'] ?? 0) ?: null,
            'marca'       => trim($_POST['marca'] ?? ''),
            'modelo'      => trim($_POST['modelo'] ?? ''),
            'n_serie'     => trim($_POST['n_serie'] ?? ''),
            'n_parte'     => trim($_POST['n_parte'] ?? ''),
            'ip'          => trim($_POST['ip'] ?? ''),
            'titularidad' => $_POST['titularidad'] ?? 'Municipal',
            'tenencia'    => $_POST['tenencia'] ?? 'En sede',
            'tenedor'     => trim($_POST['tenedor'] ?? ''),
            'responsable' => trim($_POST['responsable'] ?? ''),
            'observaciones' => trim($_POST['observaciones'] ?? ''),
            'notas_tecnicas' => trim($_POST['notas_tecnicas'] ?? ''),
        ];

        if ($editar) {
            $set = implode(', ', array_map(fn($c) => "$c=?", array_keys($campos)));
            $args = array_values($campos);
            $args[] = $id;
            $db->prepare("UPDATE equipos SET $set, actualizado=datetime('now','localtime') WHERE id=?")
               ->execute($args);
            auditar('equipo', $id, 'modificación', (string)$hostname);
            flash('Equipo actualizado.');
        } else {
            $campos['correlativo'] = $res['correlativo'];
            $cols = implode(',', array_keys($campos));
            $ph = implode(',', array_fill(0, count($campos), '?'));
            $db->prepare("INSERT INTO equipos ($cols) VALUES ($ph)")->execute(array_values($campos));
            $id = (int)$db->lastInsertId();
            auditar('equipo', $id, 'alta', $hostname ?? ('patrimonial ' . ($campos['id_patrimonial'] ?? '')));
            flash('Equipo creado' . ($hostname ? " como «{$hostname}»." : '.'));
        }
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
