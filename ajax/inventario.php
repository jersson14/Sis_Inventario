<?php
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('inventario', 'almacen'));
require_once "../modelos/Inventario.php";

$inventario = new Inventario();
$op = isset($_GET['op']) ? $_GET['op'] : '';

switch ($op) {
	case 'selectArticulo':
		$rs = $inventario->articulosActivos();
		if ($rs) {
			while ($reg = $rs->fetch_object()) {
				echo '<option value="' . (int)$reg->idarticulo . '" data-stock="' . (int)round((float)$reg->stock) . '" data-unidad="' . e($reg->unidad) . '" data-costo="' . number_format((float)$reg->precio_compra, 2, '.', '') . '">'
					. e($reg->nombre) . ($reg->codigo !== '' ? ' (' . e($reg->codigo) . ')' : '') . '</option>';
			}
		}
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
		$info['stock'] = (int)round((float)$info['stock']);
		$info['stock_minimo'] = (int)round((float)$info['stock_minimo']);
		responderJson($info);
		break;

	case 'registrar':
		$idarticulo = enteroSeguro(isset($_POST['idarticulo']) ? $_POST['idarticulo'] : 0);
		$tipo = isset($_POST['tipo']) ? limpiarCadena($_POST['tipo']) : '';
		$motivo = isset($_POST['motivo']) ? limpiarCadena($_POST['motivo']) : '';
		$cantidad = enteroSeguro(isset($_POST['cantidad']) ? $_POST['cantidad'] : 0);
		$costo = decimalSeguro(isset($_POST['costo_unitario']) ? $_POST['costo_unitario'] : 0);
		$observacion = isset($_POST['observacion']) ? limpiarCadena($_POST['observacion']) : '';

		$r = $inventario->registrar($idarticulo, (int)$_SESSION['idusuario'], $tipo, $motivo, $cantidad, $costo, $observacion);
		if (!empty($r['ok'])) {
			registrarAuditoria('inventario', 'ajuste_' . strtolower($tipo), $motivo . ' x' . $cantidad . ' ' . (isset($r['articulo']) ? $r['articulo'] : ('#' . $idarticulo)));
		}
		responderJson($r);
		break;

	case 'listar':
		$fi = fechaSegura(isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '', '');
		$ff = fechaSegura(isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '', '');
		$tipo = isset($_GET['tipo']) ? strtoupper(trim($_GET['tipo'])) : '';
		$idart = enteroSeguro(isset($_GET['idarticulo']) ? $_GET['idarticulo'] : 0);
		$rs = $inventario->listar($fi, $ff, $tipo, $idart);
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
					'2' => e($reg->articulo) . ($reg->codigo !== '' ? ' <small class="text-soft">' . e($reg->codigo) . '</small>' : ''),
					'3' => e($motivoTxt),
					'4' => number_format((float)$reg->cantidad, 0) . ' ' . e($reg->unidad),
					'5' => number_format((float)$reg->stock_anterior, 0) . ' → <strong>' . number_format((float)$reg->stock_nuevo, 0) . '</strong>',
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
