<?php
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('inventario', 'almacen'));
require_once "../modelos/Inventario.php";

$inventario = new Inventario();
// Los ajustes se hacen en el almacen donde trabaja la sesion
$almacenAjuste = Stock::almacenActual();
$op = isset($_GET['op']) ? $_GET['op'] : '';

switch ($op) {
	case 'selectArticulo':
		$rs = $inventario->articulosActivos($almacenAjuste);
		if ($rs) {
			while ($reg = $rs->fetch_object()) {
				echo '<option value="' . (int)$reg->idarticulo . '" data-stock="' . round((float)$reg->stock, 3) . '" data-unidad="' . e($reg->unidad) . '" data-fraccion="' . ((negocioTiene('fracciones') && (int)$reg->permite_fraccion === 1) ? '1' : '0') . '" data-variantes="' . (int)$reg->variantes . '" data-costo="' . number_format((float)$reg->precio_compra, 2, '.', '') . '">'
					. e($reg->nombre) . ($reg->codigo !== '' ? ' (' . e($reg->codigo) . ')' : '') . '</option>';
			}
		}
		break;

	// Codigo escaneado en el ajuste: articulo, caja (presentacion), talla/color o lote
	case 'buscarCodigo':
		$codigo = isset($_POST['codigo']) ? trim((string)$_POST['codigo']) : '';
		$res = Inventario::resolverCodigo($codigo);
		responderJson($res
			? array('ok' => true) + $res
			: array('ok' => false, 'message' => 'No hay ningún artículo, caja, talla o lote con el código ' . $codigo . '.'));
		break;

	case 'motivos':
		responderJson(Inventario::motivos());
		break;

	case 'infoArticulo':
		$id = enteroSeguro(isset($_GET['idarticulo']) ? $_GET['idarticulo'] : 0);
		$info = $inventario->infoArticulo($id);
		if (!$info) {
			responderJson(array('ok' => false, 'message' => 'Artículo no encontrado.'));
		}
		$info['ok'] = true;
		$info['stock'] = round((float)$info['stock'], 3);
		$info['stock_minimo'] = round((float)$info['stock_minimo'], 3);
		responderJson($info);
		break;

	case 'registrar':
		$idarticulo = enteroSeguro(isset($_POST['idarticulo']) ? $_POST['idarticulo'] : 0);
		$tipo = isset($_POST['tipo']) ? limpiarCadena($_POST['tipo']) : '';
		$motivo = isset($_POST['motivo']) ? limpiarCadena($_POST['motivo']) : '';
		$cantidad = isset($_POST['cantidad']) ? $_POST['cantidad'] : 0; // el modelo la normaliza segun la unidad
		$costo = decimalSeguro(isset($_POST['costo_unitario']) ? $_POST['costo_unitario'] : 0);
		$observacion = isset($_POST['observacion']) ? limpiarCadena($_POST['observacion']) : '';

		$r = $inventario->registrar(
			$idarticulo, (int)$_SESSION['idusuario'], $tipo, $motivo, $cantidad, $costo, $observacion,
			enteroSeguro(isset($_POST['idlote']) ? $_POST['idlote'] : 0),
			isset($_POST['lote_codigo']) ? limpiarCadena($_POST['lote_codigo']) : '',
			isset($_POST['lote_vencimiento']) ? trim((string)$_POST['lote_vencimiento']) : '',
			enteroSeguro(isset($_POST['idvariante']) ? $_POST['idvariante'] : 0),
			$almacenAjuste
		);
		if (!empty($r['ok'])) {
			registrarAuditoria('inventario', 'ajuste_' . strtolower($tipo), $motivo . ' x' . $cantidad . ' ' . (isset($r['articulo']) ? $r['articulo'] : ('#' . $idarticulo)));
		}
		responderJson($r);
		break;

	// Tallas y colores de un articulo (el ajuste se hace sobre una combinacion)
	case 'variantesArticulo':
		require_once "../modelos/Variante.php";
		$lista = array();
		foreach (Variante::deArticulo(enteroSeguro(isset($_GET['idarticulo']) ? $_GET['idarticulo'] : 0)) as $v) {
			$lista[] = array('idvariante' => (int)$v['idvariante'], 'etiqueta' => html_entity_decode(Variante::etiqueta($v['talla'], $v['color']), ENT_QUOTES, 'UTF-8'), 'stock' => Stock::enAlmacen($almacenAjuste, (int)$v['idarticulo'], (int)$v['idvariante']));
		}
		responderJson(array('ok' => true, 'variantes' => $lista));
		break;

	// Lotes con stock de un articulo (para elegir de cual sale una baja)
	case 'lotesArticulo':
		require_once "../modelos/Lote.php";
		$lista = array();
		if (Lote::activo()) {
			foreach (Lote::deArticulo(enteroSeguro(isset($_GET['idarticulo']) ? $_GET['idarticulo'] : 0), $almacenAjuste) as $l) {
				$lista[] = array(
					'idlote' => (int)$l['idlote'],
					'codigo_lote' => (string)$l['codigo_lote'],
					'fecha_vencimiento' => $l['fecha_vencimiento'],
					'dias' => $l['dias'] === null ? null : (int)$l['dias'],
					'stock' => round((float)$l['stock'], 3)
				);
			}
		}
		responderJson(array('ok' => true, 'lotes' => $lista));
		break;

	case 'listar':
		$fi = fechaSegura(isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '', '');
		$ff = fechaSegura(isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '', '');
		$tipo = isset($_GET['tipo']) ? strtoupper(trim($_GET['tipo'])) : '';
		$idart = enteroSeguro(isset($_GET['idarticulo']) ? $_GET['idarticulo'] : 0);
		$rs = $inventario->listar($fi, $ff, $tipo, $idart);
		$multiAlm = Stock::multiAlmacen();
		$data = array();
		if ($rs) {
			$motivos = Inventario::motivos();
			while ($reg = $rs->fetch_object()) {
				$badge = $reg->tipo === 'ENTRADA'
					? '<span class="label bg-green"><i class="fa fa-arrow-down"></i> ENTRADA</span>'
					: '<span class="label bg-red"><i class="fa fa-arrow-up"></i> SALIDA</span>';
				$motivoTxt = isset($motivos[$reg->motivo]) ? $motivos[$reg->motivo] : $reg->motivo;
				$data[] = array(
					'0' => date('d/m/Y H:i', strtotime($reg->fecha_hora)),
					'1' => $badge,
					'2' => e($reg->articulo) . ($reg->codigo !== '' ? ' <small class="text-soft">' . e($reg->codigo) . '</small>' : '') . ($multiAlm && $reg->almacen !== '' ? '<br><small class="text-soft"><i class="fa fa-building-o"></i> ' . e($reg->almacen) . '</small>' : ''),
					'3' => e($motivoTxt),
					'4' => formatearCantidad($reg->cantidad) . ' ' . e($reg->unidad),
					'5' => formatearCantidad($reg->stock_anterior) . ' → <strong>' . formatearCantidad($reg->stock_nuevo) . '</strong>',
					'6' => formatearMoneda((float)$reg->costo_unitario * (float)$reg->cantidad),
					'7' => e($reg->usuario),
					'8' => e($reg->observacion)
				);
			}
		}
		echo json_encode(array('sEcho' => 1, 'iTotalRecords' => count($data), 'iTotalDisplayRecords' => count($data), 'aaData' => $data), JSON_UNESCAPED_UNICODE);
		break;

	case 'resumen':
		$fi = fechaSegura(isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '', '');
		$ff = fechaSegura(isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '', '');
		$r = $inventario->resumen($fi, $ff);
		responderJson($r ? $r : array('ajustes' => 0, 'entradas' => 0, 'salidas' => 0, 'valor_entradas' => 0, 'valor_salidas' => 0));
		break;

	default:
		responderJson(array('ok' => false, 'message' => 'Operación no válida.'), 400);
}
