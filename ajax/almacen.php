<?php
/**
 * Almacenes, stock por almacen y transferencias.
 * Ver: permiso almacenes, inventario o almacen. Crear almacenes, transferir,
 * recibir y cambiar de almacen: permiso almacenes (el administrador lo tiene).
 */
require_once "../config/seguridad.php";
requiereLogin();
require_once "../modelos/Stock.php";
require_once "../modelos/Transferencia.php";

$op = isset($_GET['op']) ? (string)$_GET['op'] : '';
$puedeGestionar = usuarioTienePermiso('almacenes');

// Cambiar el almacen de trabajo lo puede hacer quien gestiona almacenes
if ($op !== 'cambiarActual' && $op !== 'actual') {
	requierePermiso(array('almacenes', 'inventario', 'almacen'));
}
$soloGestion = array('guardarAlmacen', 'estadoAlmacen', 'hacerPrincipal', 'enviar', 'recibir', 'anularTransferencia', 'cambiarActual');
if (in_array($op, $soloGestion, true) && !$puedeGestionar) {
	responderJson(array('ok' => false, 'message' => 'Necesitas el permiso "Almacenes".'), 403);
}

switch ($op) {
	// Almacen en que trabaja la sesion y los disponibles
	case 'actual':
		$lista = array();
		foreach (Stock::almacenes() as $a) {
			$lista[] = array('idalmacen' => (int)$a['idalmacen'], 'nombre' => html_entity_decode($a['nombre'], ENT_QUOTES, 'UTF-8'), 'principal' => (int)$a['principal'] === 1);
		}
		responderJson(array('ok' => true, 'idalmacen' => Stock::almacenActual(), 'almacenes' => $lista, 'puede_cambiar' => $puedeGestionar, 'multi' => count($lista) > 1));
		break;

	case 'cambiarActual':
		$id = Stock::almacenValido(enteroSeguro(isset($_POST['idalmacen']) ? $_POST['idalmacen'] : 0));
		if (!$id) {
			responderJson(array('ok' => false, 'message' => 'Almacén no válido.'));
		}
		$_SESSION['idalmacen'] = $id;
		responderJson(array('ok' => true, 'message' => 'Ahora trabajas en ' . Stock::nombre($id) . '.', 'idalmacen' => $id));
		break;

	case 'listarAlmacenes':
		$filas = dbAll(
			"SELECT al.idalmacen, al.nombre, al.direccion, al.responsable, al.principal, al.condicion,
				(SELECT COUNT(DISTINCT s.idarticulo) FROM stock_almacen s WHERE s.idalmacen=al.idalmacen AND s.stock>0) AS articulos,
				(SELECT IFNULL(SUM(s.stock * a.precio_compra),0) FROM stock_almacen s INNER JOIN articulo a ON a.idarticulo=s.idarticulo WHERE s.idalmacen=al.idalmacen AND s.stock>0) AS valor,
				(SELECT COUNT(*) FROM usuario u WHERE u.idalmacen=al.idalmacen AND u.condicion=1) AS usuarios
			 FROM almacen al WHERE al.tipo='NORMAL' ORDER BY al.principal DESC, al.condicion DESC, al.nombre"
		);
		foreach ($filas as &$f) {
			$f['nombre'] = html_entity_decode($f['nombre'], ENT_QUOTES, 'UTF-8');
			$f['direccion'] = html_entity_decode((string)$f['direccion'], ENT_QUOTES, 'UTF-8');
			$f['responsable'] = html_entity_decode((string)$f['responsable'], ENT_QUOTES, 'UTF-8');
			$f['valor'] = round((float)$f['valor'], 2);
		}
		unset($f);
		$transito = dbRow(
			"SELECT COUNT(*) AS pendientes, IFNULL(SUM(d.cantidad*d.costo_unitario),0) AS valor FROM transferencia t INNER JOIN detalle_transferencia d ON d.idtransferencia=t.idtransferencia WHERE t.estado='ENVIADA'"
		);
		responderJson(array('ok' => true, 'almacenes' => $filas, 'puede_gestionar' => $puedeGestionar,
			'transito' => array('pendientes' => (int)dbValue("SELECT COUNT(*) FROM transferencia WHERE estado='ENVIADA'", array(), 0), 'valor' => round((float)$transito['valor'], 2))));
		break;

	case 'guardarAlmacen':
		$id = enteroSeguro(isset($_POST['idalmacen']) ? $_POST['idalmacen'] : 0);
		$nombre = mb_substr(limpiarCadena(isset($_POST['nombre']) ? $_POST['nombre'] : ''), 0, 60, 'UTF-8');
		$direccion = mb_substr(limpiarCadena(isset($_POST['direccion']) ? $_POST['direccion'] : ''), 0, 150, 'UTF-8');
		$responsable = mb_substr(limpiarCadena(isset($_POST['responsable']) ? $_POST['responsable'] : ''), 0, 80, 'UTF-8');
		if (mb_strlen($nombre, 'UTF-8') < 2) {
			responderJson(array('ok' => false, 'message' => 'Escribe el nombre del almacén.'));
		}
		if ((int)dbValue("SELECT COUNT(*) FROM almacen WHERE nombre=? AND idalmacen<>?", array($nombre, $id), 0) > 0) {
			responderJson(array('ok' => false, 'message' => 'Ya existe un almacén con ese nombre.'));
		}
		if ($id > 0) {
			$ok = dbExec("UPDATE almacen SET nombre=?, direccion=?, responsable=? WHERE idalmacen=? AND tipo='NORMAL'", array($nombre, $direccion !== '' ? $direccion : null, $responsable !== '' ? $responsable : null, $id));
		} else {
			$ok = dbInsert("INSERT INTO almacen (nombre,direccion,responsable,principal,tipo,condicion) VALUES (?,?,?,0,'NORMAL',1)", array($nombre, $direccion !== '' ? $direccion : null, $responsable !== '' ? $responsable : null)) > 0;
		}
		if ($ok) {
			registrarAuditoria('almacenes', $id > 0 ? 'editar' : 'crear', 'Almacén ' . $nombre);
		}
		responderJson(array('ok' => (bool)$ok, 'message' => $ok ? 'Almacén guardado.' : 'No se pudo guardar el almacén.'));
		break;

	case 'estadoAlmacen':
		$id = enteroSeguro(isset($_POST['idalmacen']) ? $_POST['idalmacen'] : 0);
		$activar = !empty($_POST['activar']);
		$al = dbRow("SELECT * FROM almacen WHERE idalmacen=? AND tipo='NORMAL'", array($id));
		if (!$al) {
			responderJson(array('ok' => false, 'message' => 'El almacén no existe.'));
		}
		if (!$activar) {
			if ((int)$al['principal'] === 1) {
				responderJson(array('ok' => false, 'message' => 'El almacén principal no se desactiva: marca otro como principal primero.'));
			}
			$conStock = (float)dbValue("SELECT IFNULL(SUM(stock),0) FROM stock_almacen WHERE idalmacen=? AND stock>0.0005", array($id), 0);
			if ($conStock > 0) {
				responderJson(array('ok' => false, 'message' => 'El almacén todavía tiene mercadería (' . formatearCantidad($conStock) . ' unidades): transfiérela antes de desactivarlo.'));
			}
			if ((int)dbValue("SELECT COUNT(*) FROM transferencia WHERE estado='ENVIADA' AND (idorigen=? OR iddestino=?)", array($id, $id), 0) > 0) {
				responderJson(array('ok' => false, 'message' => 'Tiene transferencias en camino: recíbelas o anúlalas primero.'));
			}
		}
		dbExec("UPDATE almacen SET condicion=? WHERE idalmacen=?", array($activar ? 1 : 0, $id));
		if (!$activar) {
			dbExec("UPDATE usuario SET idalmacen=(SELECT idalmacen FROM almacen WHERE principal=1 LIMIT 1) WHERE idalmacen=?", array($id));
		}
		registrarAuditoria('almacenes', $activar ? 'activar' : 'desactivar', 'Almacén ' . $al['nombre']);
		responderJson(array('ok' => true, 'message' => $activar ? 'Almacén activado.' : 'Almacén desactivado.'));
		break;

	case 'hacerPrincipal':
		$id = Stock::almacenValido(enteroSeguro(isset($_POST['idalmacen']) ? $_POST['idalmacen'] : 0));
		if (!$id) {
			responderJson(array('ok' => false, 'message' => 'Almacén no válido.'));
		}
		dbTransaccion(function () use ($id) {
			return dbExec("UPDATE almacen SET principal=0 WHERE tipo='NORMAL'") && dbExec("UPDATE almacen SET principal=1 WHERE idalmacen=?", array($id));
		});
		registrarAuditoria('almacenes', 'principal', 'Almacén principal: ' . Stock::nombre($id));
		responderJson(array('ok' => true, 'message' => Stock::nombre($id) . ' es ahora el almacén principal.'));
		break;

	// Stock de cada articulo por almacen (reporte)
	case 'stockPorAlmacen':
		$almacenes = Stock::almacenes();
		$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
		$soloCon = !empty($_GET['solo_con_stock']);
		$params = array();
		$where = "a.condicion=1";
		if ($q !== '') {
			$where .= " AND (a.nombre LIKE ? OR a.codigo LIKE ?)";
			$params[] = '%' . $q . '%';
			$params[] = $q . '%';
		}
		if ($soloCon) {
			$where .= " AND a.stock>0";
		}
		$filas = dbAll(
			"SELECT a.idarticulo, a.nombre, IFNULL(a.codigo,'') AS codigo, a.stock, a.precio_compra, IFNULL(u.abreviatura,'und') AS unidad
			 FROM articulo a LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad WHERE $where ORDER BY a.nombre LIMIT 2000",
			$params
		);
		$porAlm = array();
		foreach (dbAll("SELECT idalmacen, idarticulo, SUM(stock) AS stock FROM stock_almacen GROUP BY idalmacen, idarticulo") as $r) {
			$porAlm[(int)$r['idarticulo']][(int)$r['idalmacen']] = round((float)$r['stock'], 3);
		}
		$transito = Stock::transito();
		$data = array();
		foreach ($filas as $f) {
			$id = (int)$f['idarticulo'];
			$fila = array(
				'idarticulo' => $id,
				'nombre' => html_entity_decode($f['nombre'], ENT_QUOTES, 'UTF-8'),
				'codigo' => $f['codigo'],
				'unidad' => $f['unidad'],
				'total' => round((float)$f['stock'], 3),
				'transito' => isset($porAlm[$id][$transito]) ? $porAlm[$id][$transito] : 0,
				'costo' => round((float)$f['precio_compra'], 2),
				'almacenes' => array()
			);
			foreach ($almacenes as $al) {
				$fila['almacenes'][(int)$al['idalmacen']] = isset($porAlm[$id][(int)$al['idalmacen']]) ? $porAlm[$id][(int)$al['idalmacen']] : 0;
			}
			$data[] = $fila;
		}
		$cols = array();
		foreach ($almacenes as $al) {
			$cols[] = array('idalmacen' => (int)$al['idalmacen'], 'nombre' => html_entity_decode($al['nombre'], ENT_QUOTES, 'UTF-8'));
		}
		responderJson(array('ok' => true, 'almacenes' => $cols, 'filas' => $data));
		break;

	// Ficha para agregar un articulo a la transferencia: stock en el origen por talla y lote
	case 'fichaTransferencia':
		require_once "../modelos/Inventario.php";
		require_once "../modelos/Lote.php";
		$origen = Stock::almacenValido(enteroSeguro(isset($_POST['idorigen']) ? $_POST['idorigen'] : 0));
		$codigo = isset($_POST['codigo']) ? trim((string)$_POST['codigo']) : '';
		$idart = enteroSeguro(isset($_POST['idarticulo']) ? $_POST['idarticulo'] : 0);
		$lectura = array('idvariante' => 0, 'idlote' => 0, 'factor' => 1, 'presentacion' => '');
		if ($idart <= 0 && $codigo !== '') {
			$res = Inventario::resolverCodigo($codigo);
			if (!$res) {
				// Por nombre: los que coinciden
				$sug = dbAll("SELECT idarticulo, nombre, IFNULL(codigo,'') AS codigo FROM articulo WHERE condicion=1 AND (nombre LIKE ? OR codigo LIKE ?) ORDER BY nombre LIMIT 10", array('%' . $codigo . '%', $codigo . '%'));
				foreach ($sug as &$sg) { $sg['nombre'] = html_entity_decode($sg['nombre'], ENT_QUOTES, 'UTF-8'); }
				unset($sg);
				responderJson(array('ok' => false, 'message' => 'Código no registrado: ' . $codigo, 'sugerencias' => $sug));
			}
			$idart = (int)$res['idarticulo'];
			$lectura = $res;
		}
		$a = dbRow("SELECT a.idarticulo, a.nombre, IFNULL(a.codigo,'') AS codigo, IFNULL(u.abreviatura,'und') AS unidad, IFNULL(u.permite_fraccion,0) AS permite_fraccion FROM articulo a LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad WHERE a.idarticulo=? AND a.condicion=1", array($idart));
		if (!$a || !$origen) {
			responderJson(array('ok' => false, 'message' => 'El artículo no existe o está desactivado.'));
		}
		$variantes = array();
		foreach (Variante::deArticulo($idart) as $v) {
			$variantes[] = array('idvariante' => (int)$v['idvariante'], 'etiqueta' => html_entity_decode(Variante::etiqueta($v['talla'], $v['color']), ENT_QUOTES, 'UTF-8'), 'stock' => Stock::enAlmacen($origen, $idart, (int)$v['idvariante']));
		}
		$lotes = array();
		if (Lote::activo() && !$variantes) {
			foreach (Lote::deArticulo($idart, $origen) as $l) {
				$lotes[] = array('idlote' => (int)$l['idlote'], 'codigo_lote' => (string)$l['codigo_lote'], 'fecha_vencimiento' => $l['fecha_vencimiento'], 'dias' => $l['dias'] === null ? null : (int)$l['dias'], 'stock' => round((float)$l['stock'], 3));
			}
		}
		$fr = articulosPermitenFraccion(array($idart));
		responderJson(array('ok' => true, 'lectura' => $lectura, 'ficha' => array(
			'idarticulo' => $idart, 'nombre' => html_entity_decode($a['nombre'], ENT_QUOTES, 'UTF-8'), 'codigo' => $a['codigo'], 'unidad' => $a['unidad'],
			'fraccion' => !empty($fr[$idart]), 'stock' => Stock::enAlmacen($origen, $idart), 'variantes' => $variantes, 'lotes' => $lotes
		)));
		break;

	case 'enviar':
		$ids = (isset($_POST['idarticulo']) && is_array($_POST['idarticulo'])) ? array_values($_POST['idarticulo']) : array();
		$vars = (isset($_POST['idvariante']) && is_array($_POST['idvariante'])) ? array_values($_POST['idvariante']) : array();
		$lotesP = (isset($_POST['idlote']) && is_array($_POST['idlote'])) ? array_values($_POST['idlote']) : array();
		$cants = (isset($_POST['cantidad']) && is_array($_POST['cantidad'])) ? array_values($_POST['cantidad']) : array();
		$lineas = array();
		foreach ($ids as $i => $idart) {
			$lineas[] = array('idarticulo' => enteroSeguro($idart), 'idvariante' => enteroSeguro(isset($vars[$i]) ? $vars[$i] : 0), 'idlote' => enteroSeguro(isset($lotesP[$i]) ? $lotesP[$i] : 0), 'cantidad' => isset($cants[$i]) ? (string)$cants[$i] : '0');
		}
		$r = (new Transferencia())->enviar(
			enteroSeguro(isset($_POST['idorigen']) ? $_POST['idorigen'] : 0),
			enteroSeguro(isset($_POST['iddestino']) ? $_POST['iddestino'] : 0),
			$lineas,
			isset($_POST['observacion']) ? limpiarCadena($_POST['observacion']) : '',
			(int)$_SESSION['idusuario'],
			!empty($_POST['recibir_ya'])
		);
		if (!empty($r['ok'])) {
			registrarAuditoria('almacenes', 'transferir', $r['message']);
		}
		responderJson($r);
		break;

	case 'recibir':
		$id = enteroSeguro(isset($_POST['idtransferencia']) ? $_POST['idtransferencia'] : 0);
		$t = dbRow("SELECT iddestino FROM transferencia WHERE idtransferencia=?", array($id));
		$recibidas = array();
		if (isset($_POST['recibida']) && is_array($_POST['recibida'])) {
			foreach ($_POST['recibida'] as $iddet => $cant) {
				$recibidas[(int)$iddet] = (float)str_replace(',', '.', (string)$cant);
			}
		}
		$r = (new Transferencia())->recibir($id, $recibidas, (int)$_SESSION['idusuario'], isset($_POST['observacion']) ? limpiarCadena($_POST['observacion']) : '');
		if (!empty($r['ok'])) {
			registrarAuditoria('almacenes', 'recibir', $r['message']);
		}
		responderJson($r);
		break;

	case 'anularTransferencia':
		$id = enteroSeguro(isset($_POST['idtransferencia']) ? $_POST['idtransferencia'] : 0);
		$r = (new Transferencia())->anular($id, (int)$_SESSION['idusuario']);
		if (!empty($r['ok'])) {
			registrarAuditoria('almacenes', 'anular_transferencia', $r['message']);
		}
		responderJson($r);
		break;

	case 'verTransferencia':
		$t = (new Transferencia())->obtener(enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0));
		if (!$t) {
			responderJson(array('ok' => false, 'message' => 'La transferencia no existe.'));
		}
		foreach ($t['detalle'] as &$d) {
			$d['articulo'] = html_entity_decode($d['articulo'], ENT_QUOTES, 'UTF-8');
		}
		unset($d);
		$t['ok'] = true;
		$t['puede_gestionar'] = $puedeGestionar;
		responderJson($t);
		break;

	case 'listarTransferencias':
		$filas = (new Transferencia())->listar(
			fechaSegura(isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '', ''),
			fechaSegura(isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '', ''),
			isset($_GET['estado']) ? strtoupper((string)$_GET['estado']) : ''
		);
		$estados = array('ENVIADA' => 'label-warning', 'RECIBIDA' => 'label-success', 'ANULADA' => 'label-default');
		$data = array();
		foreach ($filas as $r) {
			$id = (int)$r['idtransferencia'];
			$acc = '<button type="button" class="btn btn-default btn-xs" onclick="verTransferencia(' . $id . ')" title="Ver el detalle' . ($r['estado'] === 'ENVIADA' ? ', recibir o anular' : '') . '"><i class="fa fa-eye"></i> ' . ($r['estado'] === 'ENVIADA' && $puedeGestionar ? 'Recibir' : 'Ver') . '</button>';
			$data[] = array(
				'0' => $acc,
				'1' => '<span data-orden="' . e($r['fecha_hora']) . '">' . e(date('d/m/Y H:i', strtotime($r['fecha_hora']))) . '</span>',
				'2' => '<strong>#' . $id . '</strong>',
				'3' => e(html_entity_decode($r['origen'], ENT_QUOTES, 'UTF-8')) . ' <i class="fa fa-long-arrow-right"></i> ' . e(html_entity_decode($r['destino'], ENT_QUOTES, 'UTF-8')),
				'4' => (int)$r['productos'] . ' prod. · ' . formatearCantidad($r['unidades']) . ' und',
				'5' => formatearMoneda((float)$r['valor']),
				'6' => '<span class="label ' . (isset($estados[$r['estado']]) ? $estados[$r['estado']] : 'label-default') . '">' . e($r['estado']) . '</span>',
				'7' => e($r['usuario']) . ($r['recibio'] !== '' ? '<br><small class="text-soft">recibió ' . e($r['recibio']) . '</small>' : '')
			);
		}
		echo json_encode(array('sEcho' => 1, 'iTotalRecords' => count($data), 'iTotalDisplayRecords' => count($data), 'aaData' => $data), JSON_UNESCAPED_UNICODE);
		break;

	default:
		responderJson(array('ok' => false, 'message' => 'Operación no válida.'), 400);
}
