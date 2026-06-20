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
require_once __DIR__ . '/lib/inventario.php';
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
            'componentes' => $eq ? componentes_de((int)$eq['id']) : [],
            'valores'     => $eq ? valores_de((int)$eq['id']) : [],
            'atrMapa'     => atributos_mapa(),
            'tiposComp'   => $db->query('SELECT nombre FROM tipos_componente WHERE activo=1 ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN),
            'catMarca'    => cat_union(['marca', 'comp_marca']),
            'catModelo'   => cat_union(['modelo', 'comp_modelo']),
        ]));
        break;

    case 'equipos.lote': // carga por lote: un área, varios equipos
        requiere_rol(ROL_TECNICO);
        $db = db();
        pagina('Carga por lote', vista('equipo_lote', [
            'areas'   => $db->query('SELECT id, codigo, descripcion FROM areas WHERE activa=1 ORDER BY codigo')->fetchAll(),
            'tipos'   => $db->query('SELECT * FROM tipos_equipo WHERE activo=1 ORDER BY nombre_es')->fetchAll(),
            'estados' => $db->query('SELECT * FROM estados WHERE activo=1 ORDER BY id')->fetchAll(),
            'atrMapa' => atributos_mapa(),
            'tiposComp' => $db->query('SELECT nombre FROM tipos_componente WHERE activo=1 ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN),
            'catMarca'  => cat_union(['marca', 'comp_marca']),
            'catModelo' => cat_union(['modelo', 'comp_modelo']),
            'flash' => flash(),
        ]));
        break;

    case 'componentes.analizar': // AJAX: parseo de reporte CPU-Z/HWMonitor
        requiere_rol(ROL_TECNICO);
        header('Content-Type: application/json');
        require_once __DIR__ . '/lib/cpuz.php';
        $out = ['componentes' => [], 'avisos' => []];
        if (!empty($_FILES['reporte']['tmp_name']) && is_uploaded_file($_FILES['reporte']['tmp_name'])) {
            if ((int)$_FILES['reporte']['size'] > 2_000_000) {
                $out['avisos'][] = 'Archivo demasiado grande (máx. 2 MB).';
            } else {
                $out = cpuz_parsear((string)file_get_contents($_FILES['reporte']['tmp_name']));
            }
        } else {
            $out['avisos'][] = 'No se recibió ningún archivo.';
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        break;

    // ---------- Agente de hardware (script de los usuarios) ----------
    case 'agente.recibir': // recibe el reporte por HTTP con token (sin login)
        header('Content-Type: text/plain; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); exit("Usar POST.\n");
        }
        $token = $_GET['token'] ?? ($_SERVER['HTTP_X_NOMINATOR_TOKEN'] ?? '');
        if (!hash_equals(AGENTE_TOKEN, (string)$token)) {
            http_response_code(403); exit("Token inválido.\n");
        }
        $cuerpo = (string)file_get_contents('php://input');
        if ($cuerpo === '' && !empty($_FILES['reporte']['tmp_name'])) {
            $cuerpo = (string)file_get_contents($_FILES['reporte']['tmp_name']);
        }
        if (strlen($cuerpo) < 10) { http_response_code(400); exit("Reporte vacío.\n"); }
        if (strlen($cuerpo) > 2_000_000) { http_response_code(413); exit("Reporte demasiado grande.\n"); }

        require_once __DIR__ . '/lib/cpuz.php';
        $p = cpuz_parsear($cuerpo);
        $host = $usr = '';
        if (preg_match('/Computer Name[\t ]+(.+)/i', $cuerpo, $m)) { $host = trim($m[1]); }
        if (preg_match('/(?:^|\n)[\t ]*User[\t ]+(.+)/i', $cuerpo, $m)) { $usr = trim($m[1]); }
        $resumen = implode(' · ', array_map(fn($c) => $c['tipo'] . ' ' . $c['modelo'], $p['componentes']));
        db()->prepare(
            'INSERT INTO reportes_pendientes (origen_host,origen_usuario,origen_ip,n_componentes,resumen,contenido)
             VALUES (?,?,?,?,?,?)'
        )->execute([$host, $usr, $_SERVER['REMOTE_ADDR'] ?? '', count($p['componentes']), $resumen, $cuerpo]);
        echo "OK: reporte recibido (" . count($p['componentes']) . " componentes). ¡Gracias!\n";
        break;

    case 'reportes': // bandeja de reportes pendientes
        requiere_rol(ROL_TECNICO);
        $rows = db()->query('SELECT * FROM reportes_pendientes WHERE procesado=0 ORDER BY recibido DESC')->fetchAll();
        pagina('Reportes de hardware', vista('reportes_list', ['reportes' => $rows, 'flash' => flash()]));
        break;

    case 'reportes.ver':
        requiere_rol(ROL_TECNICO);
        require_once __DIR__ . '/lib/cpuz.php';
        $db = db();
        $st = $db->prepare('SELECT * FROM reportes_pendientes WHERE id=?');
        $st->execute([(int)($_GET['id'] ?? 0)]);
        $rep = $st->fetch();
        if (!$rep) { http_response_code(404); pagina('No encontrado', '<p>Reporte inexistente.</p>'); break; }
        $comp = cpuz_parsear($rep['contenido'])['componentes'];
        pagina('Reporte de ' . ($rep['origen_host'] ?: ('#' . $rep['id'])), vista('reporte_ver', [
            'rep' => $rep, 'comp' => $comp,
            'areas' => $db->query('SELECT id, codigo, descripcion FROM areas WHERE activa=1 ORDER BY codigo')->fetchAll(),
            'tipos' => $db->query('SELECT * FROM tipos_equipo WHERE activo=1 ORDER BY nombre_es')->fetchAll(),
            'estados' => $db->query('SELECT * FROM estados WHERE activo=1 ORDER BY id')->fetchAll(),
            'flash' => flash(),
        ]));
        break;

    case 'reportes.procesar': // crear equipo desde un reporte pendiente
        requiere_rol(ROL_TECNICO);
        csrf_check();
        require_once __DIR__ . '/lib/cpuz.php';
        $db = db();
        $repId = (int)($_POST['reporte_id'] ?? 0);
        $st = $db->prepare('SELECT * FROM reportes_pendientes WHERE id=?');
        $st->execute([$repId]);
        $rep = $st->fetch();
        if (!$rep || $rep['procesado']) { flash('Reporte inexistente o ya procesado.', 'error'); redir('reportes'); }

        $tipoId = (int)($_POST['tipo_id'] ?? 0);
        $areaId = (int)($_POST['area_id'] ?? 0);
        $tipo = $db->query('SELECT * FROM tipos_equipo WHERE id=' . $tipoId)->fetch();
        $area = $areaId ? $db->query('SELECT * FROM areas WHERE id=' . $areaId)->fetch() : null;
        if (!$tipo || !$area) { flash('Elegí área y tipo.', 'error'); redir('reportes.ver&id=' . $repId); }

        $resH = nm_resolver_hostname($db, $tipo, $area, $_POST['hostname'] ?? '', null);
        if ($resH['errores']) { flash(implode(' ', $resH['errores']), 'error'); redir('reportes.ver&id=' . $repId); }

        $campos = [
            'id_patrimonial' => trim($_POST['id_patrimonial'] ?? '') ?: null,
            'hostname'    => $resH['hostname'],
            'tipo_id'     => $tipoId,
            'area_id'     => $areaId,
            'estado_id'   => (int)($_POST['estado_id'] ?? 0) ?: null,
            'titularidad' => 'Municipal',
            'tenencia'    => 'En sede',
            'observaciones' => trim('Alta desde reporte del agente. Origen: '
                . ($rep['origen_host'] ?: '?') . ' / ' . ($rep['origen_usuario'] ?: '?')),
            'correlativo' => $resH['correlativo'],
        ];
        $cols = implode(',', array_keys($campos));
        $ph = implode(',', array_fill(0, count($campos), '?'));
        $db->prepare("INSERT INTO equipos ($cols) VALUES ($ph)")->execute(array_values($campos));
        $id = (int)$db->lastInsertId();

        // Componentes desde el reporte
        $parsed = cpuz_parsear($rep['contenido'])['componentes'];
        $datos = ['comp_tipo' => [], 'comp_marca' => [], 'comp_modelo' => [],
                  'comp_serie' => [], 'comp_velocidad' => [], 'comp_memoria' => [], 'comp_bus' => []];
        foreach ($parsed as $c) {
            $datos['comp_tipo'][] = $c['tipo'];
            $datos['comp_marca'][] = $c['marca'];
            $datos['comp_modelo'][] = $c['modelo'];
            $datos['comp_serie'][] = $c['n_serie'];
            $datos['comp_velocidad'][] = $c['velocidad'];
            $datos['comp_memoria'][] = $c['memoria'];
            $datos['comp_bus'][] = $c['bus'];
        }
        guardar_componentes($db, $id, $datos);
        $db->prepare('UPDATE reportes_pendientes SET procesado=1, equipo_id=? WHERE id=?')->execute([$id, $repId]);
        auditar('reporte', $repId, 'procesado', 'equipo ' . $id);
        flash('Equipo creado desde el reporte' . ($resH['hostname'] ? " como «{$resH['hostname']}»." : '.'));
        redir('equipos.ver&id=' . $id);
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
        // Catálogo, componentes y atributos dinámicos
        cat_add('marca', $campos['marca']);
        cat_add('modelo', $campos['modelo']);
        guardar_componentes($db, $id, $_POST);
        guardar_atributos($db, $id, $tipoId, $_POST['attr'] ?? []);
        redir('equipos.ver&id=' . $id);
        break;

    case 'equipos.lote_guardar':
        requiere_rol(ROL_TECNICO);
        csrf_check();
        $db = db();
        $areaId = (int)($_POST['area_id'] ?? 0);
        $area = $areaId ? $db->query('SELECT * FROM areas WHERE id=' . $areaId)->fetch() : null;
        if (!$area) {
            flash('Elegí un área para la carga por lote.', 'error');
            redir('equipos.lote');
        }
        $filas = $_POST['eq'] ?? [];
        $creados = 0;
        $errores = [];
        foreach ((array)$filas as $idx => $f) {
            $tipoId = (int)($f['tipo_id'] ?? 0);
            if (!$tipoId) {
                continue; // fila sin tipo: se ignora
            }
            $tipo = $db->query('SELECT * FROM tipos_equipo WHERE id=' . $tipoId)->fetch();
            if (!$tipo) {
                continue;
            }
            $res = nm_resolver_hostname($db, $tipo, $area, $f['hostname'] ?? '', null);
            if ($res['errores']) {
                $errores[] = 'Equipo #' . ($idx + 1) . ': ' . implode(' ', $res['errores']);
                continue;
            }
            $campos = [
                'id_patrimonial' => trim($f['id_patrimonial'] ?? '') ?: null,
                'hostname'    => $res['hostname'],
                'tipo_id'     => $tipoId,
                'area_id'     => $areaId,
                'estado_id'   => (int)($f['estado_id'] ?? 0) ?: null,
                'correlativo' => $res['correlativo'],
                'marca'       => trim($f['marca'] ?? ''),
                'modelo'      => trim($f['modelo'] ?? ''),
                'n_serie'     => trim($f['n_serie'] ?? ''),
                'n_parte'     => trim($f['n_parte'] ?? ''),
                'ip'          => trim($f['ip'] ?? ''),
                'titularidad' => $f['titularidad'] ?? 'Municipal',
                'tenencia'    => $f['tenencia'] ?? 'En sede',
                'tenedor'     => trim($f['tenedor'] ?? ''),
                'responsable' => trim($f['responsable'] ?? ''),
                'observaciones' => trim($f['observaciones'] ?? ''),
            ];
            $cols = implode(',', array_keys($campos));
            $ph = implode(',', array_fill(0, count($campos), '?'));
            $db->prepare("INSERT INTO equipos ($cols) VALUES ($ph)")->execute(array_values($campos));
            $id = (int)$db->lastInsertId();
            cat_add('marca', $campos['marca']);
            cat_add('modelo', $campos['modelo']);
            guardar_componentes($db, $id, $f);
            guardar_atributos($db, $id, $tipoId, $f['attr'] ?? []);
            auditar('equipo', $id, 'alta (lote)', (string)$campos['hostname']);
            $creados++;
        }
        $msg = ($creados ? "$creados equipo(s) creado(s) en {$area['descripcion']}. " : '')
             . ($errores ? 'No se cargaron: ' . implode(' | ', $errores) : '');
        flash($msg ?: 'No se cargó ningún equipo.', $errores ? 'error' : 'ok');
        redir('equipos&area=' . $areaId);
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
        pagina('Equipo ' . ($eq['hostname'] ?? $eq['id']), vista('equipo_show', [
            'eq' => $eq, 'componentes' => componentes_de($id),
            'atributos' => valores_legibles($id), 'flash' => flash(),
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
        reporte('Ficha de hardware', vista('rep_ficha', [
            'eq' => $eq, 'componentes' => componentes_de($id), 'atributos' => valores_legibles($id),
        ]));
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
