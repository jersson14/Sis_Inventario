<?php
/**
 * Toma de inventario (conteo fisico con lector de barras).
 * Contar: permiso inventario o almacen. Aplicar o anular: inventario (o administrador).
 */
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('inventario', 'almacen'));
require_once "../modelos/Conteo.php";

$conteoModel = new Conteo();
$op = isset($_GET['op']) ? (string)$_GET['op'] : '';

/** Conteo abierto pedido por la pantalla (o error JSON). */
function conteoAbiertoPedido($conteoModel)
{
	$id = enteroSeguro(isset($_REQUEST['idconteo']) ? $_REQUEST['idconteo'] : 0);
	$c = $id > 0 ? $conteoModel->obtener($id) : null;
	if (!$c) {
		responderJson(array('ok' => false, 'message' => 'El conteo no existe.'));
	}
	if ($c['estado'] !== 'ABIERTO') {
		responderJson(array('ok' => false, 'message' => 'El conteo ya fue ' . strtolower($c['estado']) . '.', 'cerrado' => true));
	}
	return $c;
}

function puedeAplicarConteo()
{
	return usuarioTienePermiso('inventario') || usuarioTienePermiso('acceso');
}

function conteoParaJs($c)
{
	return array(
		'idconteo' => (int)$c['idconteo'],
		'nombre' => html_entity_decode($c['nombre'], ENT_QUOTES, 'UTF-8'),
		'categoria' => html_entity_decode((string)$c['categoria'], ENT_QUOTES, 'UTF-8'),
		'idcategoria' => (int)$c['idcategoria'],
		'por_lote' => Conteo::porLote($c),
		'estado' => $c['estado'],
		'fecha_inicio' => date('d/m/Y H:i', strtotime($c['fecha_inicio'])),
		'usuario' => $c['usuario'],
		'observacion' => html_entity_decode((string)$c['observacion'], ENT_QUOTES, 'UTF-8'),
		'almacen' => Stock::multiAlmacen() ? html_entity_decode((string)$c['almacen'], ENT_QUOTES, 'UTF-8') : ''
	);
}

switch ($op) {
	// Conteo abierto (si hay) con su resumen
	case 'estado':
		$c = Conteo::abierto();
		responderJson(array(
			'ok' => true,
			'conteo' => $c ? conteoParaJs($c) : null,
			'resumen' => $c ? $conteoModel->resumen($c) : null,
			'puede_aplicar' => puedeAplicarConteo(),
			'usa_lotes' => Lote::activo()
		));
		break;

	case 'crear':
		$r = $conteoModel->crear(
			isset($_POST['nombre']) ? limpiarCadena($_POST['nombre']) : '',
			enteroSeguro(isset($_POST['idcategoria']) ? $_POST['idcategoria'] : 0),
			!empty($_POST['por_lote']),
			isset($_POST['observacion']) ? limpiarCadena($_POST['observacion']) : '',
			(int)$_SESSION['idusuario'],
			// Almacen a contar: el elegido (con permiso de almacenes) o el de la sesion
			(usuarioTienePermiso('almacenes') && Stock::almacenValido(enteroSeguro(isset($_POST['idalmacen']) ? $_POST['idalmacen'] : 0)))
				? enteroSeguro($_POST['idalmacen']) : Stock::almacenActual()
		);
		if (!empty($r['ok'])) {
			registrarAuditoria('inventario', 'conteo_iniciar', 'Conteo #' . $r['idconteo'] . ' ' . (isset($_POST['nombre']) ? limpiarCadena($_POST['nombre']) : ''));
		}
		responderJson($r);
		break;

	// Codigo escaneado: articulo, talla/color, caja o lote dentro del alcance
	case 'codigo':
		$c = conteoAbiertoPedido($conteoModel);
		$codigo = isset($_POST['codigo']) ? trim((string)$_POST['codigo']) : '';
		$res = Inventario::resolverCodigo($codigo);
		if (!$res) {
			responderJson(array('ok' => false, 'message' => 'Código no registrado: ' . $codigo, 'no_encontrado' => true, 'sugerencias' => $conteoModel->buscar($c, $codigo)));
		}
		$ficha = $conteoModel->fichaArticulo($c, $res['idarticulo']);
		if (!$ficha) {
			responderJson(array('ok' => false, 'message' => 'Ese artículo no es de la categoría que estás contando (' . html_entity_decode($c['categoria'], ENT_QUOTES, 'UTF-8') . ').'));
		}
		responderJson(array('ok' => true, 'ficha' => $ficha, 'lectura' => $res));
		break;

	// Elegido de la lista de busqueda
	case 'ficha':
		$c = conteoAbiertoPedido($conteoModel);
		$ficha = $conteoModel->fichaArticulo($c, enteroSeguro(isset($_GET['idarticulo']) ? $_GET['idarticulo'] : 0));
		responderJson($ficha ? array('ok' => true, 'ficha' => $ficha) : array('ok' => false, 'message' => 'El artículo no está en el alcance del conteo.'));
		break;

	case 'buscar':
		$c = conteoAbiertoPedido($conteoModel);
		responderJson(array('ok' => true, 'items' => $conteoModel->buscar($c, isset($_GET['q']) ? (string)$_GET['q'] : '')));
		break;

	case 'registrar':
		$c = conteoAbiertoPedido($conteoModel);
		$r = $conteoModel->registrar($c['idconteo'], array(
			'idarticulo' => enteroSeguro(isset($_POST['idarticulo']) ? $_POST['idarticulo'] : 0),
			'idvariante' => enteroSeguro(isset($_POST['idvariante']) ? $_POST['idvariante'] : 0),
			'idlote' => enteroSeguro(isset($_POST['idlote']) ? $_POST['idlote'] : 0),
			'lote_codigo' => isset($_POST['lote_codigo']) ? limpiarCadena($_POST['lote_codigo']) : '',
			'lote_vencimiento' => isset($_POST['lote_vencimiento']) ? trim((string)$_POST['lote_vencimiento']) : '',
			'cantidad' => isset($_POST['cantidad']) ? $_POST['cantidad'] : 0,
			'modo' => isset($_POST['modo']) ? (string)$_POST['modo'] : 'sumar'
		), (int)$_SESSION['idusuario']);
		responderJson($r);
		break;

	case 'eliminarLinea':
		$c = conteoAbiertoPedido($conteoModel);
		responderJson($conteoModel->eliminarLinea($c['idconteo'], enteroSeguro(isset($_POST['iddetalle']) ? $_POST['iddetalle'] : 0)));
		break;

	case 'lineas':
		$id = enteroSeguro(isset($_GET['idconteo']) ? $_GET['idconteo'] : 0);
		$c = $conteoModel->obtener($id);
		if (!$c) {
			responderJson(array('ok' => false, 'message' => 'El conteo no existe.'));
		}
		responderJson(array('ok' => true, 'conteo' => conteoParaJs($c), 'lineas' => $conteoModel->lineas($id), 'resumen' => $c['estado'] === 'ABIERTO' ? $conteoModel->resumen($c) : null));
		break;

	case 'pendientes':
		$c = conteoAbiertoPedido($conteoModel);
		responderJson(array('ok' => true, 'items' => $conteoModel->pendientes($c)));
		break;

	// Vista previa de los ajustes antes de aplicar
	case 'previsualizar':
		$c = conteoAbiertoPedido($conteoModel);
		$movs = $conteoModel->plan($c, !empty($_GET['cero']));
		$ent = 0.0;
		$sal = 0.0;
		foreach ($movs as &$m) {
			if ($m['valor'] > 0) {
				$ent += $m['valor'];
			} else {
				$sal += -$m['valor'];
			}
			unset($m['lote']);
		}
		unset($m);
		responderJson(array('ok' => true, 'movimientos' => $movs, 'valor_sobrante' => round($ent, 2), 'valor_faltante' => round($sal, 2), 'puede_aplicar' => puedeAplicarConteo()));
		break;

	case 'aplicar':
		if (!puedeAplicarConteo()) {
			responderJson(array('ok' => false, 'message' => 'Aplicar un conteo requiere el permiso "Ajustes de inventario".'), 403);
		}
		$c = conteoAbiertoPedido($conteoModel);
		$cero = !empty($_POST['cero']);
		$r = $conteoModel->aplicar($c['idconteo'], (int)$_SESSION['idusuario'], $cero);
		if (!empty($r['ok'])) {
			registrarAuditoria('inventario', 'conteo_aplicar', 'Conteo #' . $c['idconteo'] . ' ' . $r['nombre'] . ': ' . $r['ajustes'] . ' ajustes, sobrante ' . $r['valor_sobrante'] . ', faltante ' . $r['valor_faltante'] . ($cero ? ', no contados en cero' : ''));
		}
		responderJson($r);
		break;

	case 'anular':
		if (!puedeAplicarConteo()) {
			responderJson(array('ok' => false, 'message' => 'Anular un conteo requiere el permiso "Ajustes de inventario".'), 403);
		}
		$c = conteoAbiertoPedido($conteoModel);
		$r = $conteoModel->anular($c['idconteo'], (int)$_SESSION['idusuario']);
		if (!empty($r['ok'])) {
			registrarAuditoria('inventario', 'conteo_anular', 'Conteo #' . $c['idconteo'] . ' ' . $c['nombre']);
		}
		responderJson($r);
		break;

	case 'listar':
		$data = array();
		$estados = array('ABIERTO' => 'label-warning', 'APLICADO' => 'label-success', 'ANULADO' => 'label-default');
		foreach ($conteoModel->listar() as $r) {
			$data[] = array(
				'0' => '<button type="button" class="btn btn-default btn-xs" onclick="verConteo(' . (int)$r['idconteo'] . ')" title="Ver lo contado y las diferencias"><i class="fa fa-eye"></i> Ver</button>'
					. ' <a class="btn btn-default btn-xs" href="../reportes/rptconteo.php?id=' . (int)$r['idconteo'] . '" target="_blank" title="Imprimir el reporte del conteo"><i class="fa fa-print"></i> Reporte</a>',
				'1' => '<span data-orden="' . e($r['fecha_inicio']) . '">' . date('d/m/Y H:i', strtotime($r['fecha_inicio'])) . '</span>',
				'2' => e($r['nombre']) . ((int)$r['por_lote'] === 1 ? ' <small class="label label-info">por lote</small>' : ''),
				'3' => ($r['categoria'] !== '' ? e($r['categoria']) : 'Todo el almacén') . (Stock::multiAlmacen() && !empty($r['almacen']) ? '<br><small class="text-soft"><i class="fa fa-building-o"></i> ' . e(html_entity_decode($r['almacen'], ENT_QUOTES, 'UTF-8')) . '</small>' : ''),
				'4' => '<span class="label ' . (isset($estados[$r['estado']]) ? $estados[$r['estado']] : 'label-default') . '">' . e($r['estado']) . '</span>',
				'5' => (int)$r['lineas'],
				'6' => (int)$r['ajustes'],
				'7' => '<span class="text-success">+' . e(formatearMoneda($r['valor_sobrante'])) . '</span> / <span class="text-danger">-' . e(formatearMoneda($r['valor_faltante'])) . '</span>',
				'8' => e($r['usuario'])
			);
		}
		echo json_encode(array('sEcho' => 1, 'iTotalRecords' => count($data), 'iTotalDisplayRecords' => count($data), 'aaData' => $data), JSON_UNESCAPED_UNICODE);
		break;

	default:
		responderJson(array('ok' => false, 'message' => 'Operación no válida.'), 400);
}
