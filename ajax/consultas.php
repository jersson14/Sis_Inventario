<?php
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('consultac', 'consultav', 'reportes', 'escritorio'));
require_once "../modelos/Consultas.php";

$consulta = new Consultas();

/** Envuelve el array de filas en el contrato de DataTables. */
function respuestaDataTable(array $data)
{
	echo json_encode(array(
		"sEcho" => 1,
		"iTotalRecords" => count($data),
		"iTotalDisplayRecords" => count($data),
		"aaData" => $data
	), JSON_UNESCAPED_UNICODE);
}

/** Etiqueta de estado de un comprobante. */
function badgeEstadoComprobante($estado)
{
	return ($estado === 'Aceptado')
		? '<span class="label bg-green">Aceptado</span>'
		: '<span class="label bg-red">Anulado</span>';
}

$op = isset($_GET["op"]) ? (string)$_GET["op"] : '';

// Rango de fechas por defecto (mes actual)
$fecha_inicio = fechaSegura(isset($_REQUEST["fecha_inicio"]) ? $_REQUEST["fecha_inicio"] : '', date("Y-m-01"));
$fecha_fin = fechaSegura(isset($_REQUEST["fecha_fin"]) ? $_REQUEST["fecha_fin"] : '', date("Y-m-d"));
if ($fecha_inicio > $fecha_fin) {
	$tmp = $fecha_inicio;
	$fecha_inicio = $fecha_fin;
	$fecha_fin = $tmp;
}

switch ($op) {

	case 'comprasfecha':
		requierePermiso(array('consultac'));
		$rspta = $consulta->comprasfecha($fecha_inicio, $fecha_fin);
		$data = array();
		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$data[] = array(
					"0" => e($reg->fecha),
					"1" => e($reg->usuario),
					"2" => e($reg->proveedor),
					"3" => e($reg->tipo_comprobante),
					"4" => e($reg->serie_comprobante . ' ' . $reg->num_comprobante),
					"5" => formatearMoneda((float)$reg->total_compra),
					"6" => number_format((float)$reg->impuesto, 2) . ' %',
					"7" => badgeEstadoComprobante($reg->estado)
				);
			}
		}
		respuestaDataTable($data);
		break;

	case 'ventasfechacliente':
		requierePermiso(array('consultav'));
		$idcliente = enteroSeguro(isset($_REQUEST["idcliente"]) ? $_REQUEST["idcliente"] : 0);
		$data = array();
		if ($idcliente > 0) {
			$rspta = $consulta->ventasfechacliente($fecha_inicio, $fecha_fin, $idcliente);
			if ($rspta) {
				while ($reg = $rspta->fetch_object()) {
					$data[] = array(
						"0" => e($reg->fecha),
						"1" => e($reg->usuario),
						"2" => e($reg->cliente),
						"3" => e($reg->tipo_comprobante),
						"4" => e($reg->serie_comprobante . ' ' . $reg->num_comprobante),
						"5" => formatearMoneda((float)$reg->total_venta),
						"6" => number_format((float)$reg->impuesto, 2) . ' %',
						"7" => badgeEstadoComprobante($reg->estado)
					);
				}
			}
		}
		respuestaDataTable($data);
		break;

	case 'utilidadperiodo':
		requierePermiso(array('reportes', 'consultac', 'consultav'));
		$rspta = $consulta->utilidadPorPeriodo($fecha_inicio, $fecha_fin);
		$data = array();
		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$utilidad = (float)$reg->venta_total - (float)$reg->costo_estimado;
				$margen = ((float)$reg->venta_total > 0) ? ($utilidad / (float)$reg->venta_total) * 100 : 0;
				$data[] = array(
					"0" => e($reg->codigo),
					"1" => e($reg->articulo),
					"2" => e($reg->categoria),
					"3" => formatearCantidad($reg->cantidad_vendida),
					"4" => number_format((float)$reg->venta_total, 2),
					"5" => number_format((float)$reg->costo_estimado, 2),
					"6" => number_format($utilidad, 2),
					"7" => number_format($margen, 2) . " %"
				);
			}
		}
		respuestaDataTable($data);
		break;

	case 'topproductos':
		requierePermiso(array('reportes', 'consultac', 'consultav'));
		$limite = enteroSeguro(isset($_REQUEST["limite"]) ? $_REQUEST["limite"] : 20, 1, 20);
		if ($limite > 500) {
			$limite = 500;
		}
		$modo = isset($_REQUEST["modo"]) ? strtoupper(trim((string)$_REQUEST["modo"])) : 'MAS';
		if (!in_array($modo, array('MAS', 'MENOS'), true)) {
			$modo = 'MAS';
		}

		$rspta = $consulta->topProductosPeriodo($fecha_inicio, $fecha_fin, $limite, $modo);
		$data = array();
		$rank = 1;
		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$data[] = array(
					"0" => $rank++,
					"1" => e($reg->codigo),
					"2" => e($reg->articulo),
					"3" => e($reg->categoria),
					"4" => formatearCantidad($reg->cantidad) . " " . e($reg->unidad),
					"5" => number_format((float)$reg->total, 2)
				);
			}
		}
		respuestaDataTable($data);
		break;

	case 'stockcritico':
		requierePermiso(array('reportes', 'consultac', 'consultav'));
		$rspta = $consulta->stockCritico();
		$data = array();
		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$badge = '<span class="label bg-green">OK</span>';
				if ($reg->alerta === 'AGOTADO') {
					$badge = '<span class="label bg-red">AGOTADO</span>';
				} elseif ($reg->alerta === 'BAJO MINIMO') {
					$badge = '<span class="label bg-yellow">BAJO MINIMO</span>';
				} elseif ($reg->alerta === 'PROXIMO A AGOTARSE') {
					$badge = '<span class="label bg-orange">PROXIMO A AGOTARSE</span>';
				} elseif ($reg->alerta === 'SIN MOVIMIENTO') {
					$badge = '<span class="label bg-aqua">SIN MOVIMIENTO</span>';
				}

				$data[] = array(
					"0" => e($reg->codigo),
					"1" => e($reg->articulo),
					"2" => e($reg->categoria),
					"3" => formatearCantidad($reg->stock) . " " . e($reg->unidad),
					"4" => formatearCantidad($reg->stock_minimo) . " " . e($reg->unidad),
					"5" => $badge,
					"6" => e($reg->ultimo_mov),
					"7" => (int)$reg->dias_sin_mov
				);
			}
		}
		respuestaDataTable($data);
		break;

	case 'kardexvalorizado':
		requierePermiso(array('reportes', 'consultac', 'consultav'));
		$rspta = $consulta->kardexValorizado($fecha_inicio, $fecha_fin);
		$data = array();
		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$data[] = array(
					"0" => e($reg->codigo),
					"1" => e($reg->articulo),
					"2" => formatearCantidad($reg->entrada) . " " . e($reg->unidad),
					"3" => formatearCantidad($reg->salida) . " " . e($reg->unidad),
					"4" => formatearCantidad($reg->saldo) . " " . e($reg->unidad),
					"5" => number_format((float)$reg->costo_promedio, 2),
					"6" => number_format((float)$reg->valor_stock, 2)
				);
			}
		}
		respuestaDataTable($data);
		break;

	case 'clientesproveedores':
		requierePermiso(array('reportes', 'consultac', 'consultav'));
		$tipo = isset($_REQUEST["tipo"]) ? strtoupper(trim((string)$_REQUEST["tipo"])) : 'TODOS';
		if (!in_array($tipo, array('TODOS', 'CLIENTE', 'PROVEEDOR'), true)) {
			$tipo = 'TODOS';
		}

		$rspta = $consulta->clientesProveedoresPeriodo($fecha_inicio, $fecha_fin, $tipo);
		$data = array();
		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$tipoBadge = ($reg->tipo === 'CLIENTE')
					? '<span class="label bg-green">CLIENTE</span>'
					: '<span class="label bg-aqua">PROVEEDOR</span>';

				$data[] = array(
					"0" => $tipoBadge,
					"1" => e($reg->persona),
					"2" => e($reg->documento),
					"3" => e($reg->telefono),
					"4" => (int)$reg->operaciones,
					"5" => number_format((float)$reg->total, 2),
					"6" => e($reg->ultimo_mov)
				);
			}
		}
		respuestaDataTable($data);
		break;

	// ---------------------------------------------------------------
	// Dashboard (JSON)
	// ---------------------------------------------------------------

	case 'dashboardAlertas':
		requierePermiso(array('escritorio', 'reportes', 'consultac', 'consultav'));
		$res = $consulta->resumenAlertas((int)$_SESSION['idusuario']);
		responderJson($res);
		break;

	case 'dashboardUtilidad':
		requierePermiso(array('escritorio', 'reportes', 'consultac', 'consultav'));
		$res = $consulta->utilidadResumenRango($fecha_inicio, $fecha_fin);
		$res['fecha_inicio'] = $fecha_inicio;
		$res['fecha_fin'] = $fecha_fin;
		responderJson($res);
		break;

	case 'dashboardMediosPago':
		requierePermiso(array('escritorio', 'reportes', 'consultac', 'consultav'));
		$labels = array();
		$valores = array();
		$extra = array();
		foreach ($consulta->ventasPorMedioPago($fecha_inicio, $fecha_fin) as $r) {
			$labels[] = (string)$r['medio_pago'];
			$valores[] = round((float)$r['total'], 2);
			$extra[] = (int)$r['cantidad'];
		}
		responderJson(array('labels' => $labels, 'data' => $valores, 'extra' => $extra));
		break;

	case 'dashboardVendedores':
		requierePermiso(array('escritorio', 'reportes', 'consultac', 'consultav'));
		$limite = enteroSeguro(isset($_REQUEST["limite"]) ? $_REQUEST["limite"] : 8, 1, 8);
		if ($limite > 50) {
			$limite = 50;
		}
		$labels = array();
		$valores = array();
		$extra = array();
		foreach ($consulta->ventasPorVendedor($fecha_inicio, $fecha_fin, $limite) as $r) {
			$labels[] = (string)$r['vendedor'];
			$valores[] = round((float)$r['total'], 2);
			$extra[] = (int)$r['cantidad'];
		}
		responderJson(array('labels' => $labels, 'data' => $valores, 'extra' => $extra));
		break;

	case 'dashboardVentasDia':
		requierePermiso(array('escritorio', 'reportes', 'consultac', 'consultav'));
		$labels = array();
		$valores = array();
		$extra = array();
		foreach ($consulta->ventasPorDiaRango($fecha_inicio, $fecha_fin) as $r) {
			$labels[] = date('d/m', strtotime($r['fecha']));
			$valores[] = round((float)$r['total'], 2);
			$extra[] = (string)$r['fecha'];
		}
		responderJson(array('labels' => $labels, 'data' => $valores, 'extra' => $extra));
		break;

	case 'dashboardVentasHora':
		requierePermiso(array('escritorio', 'reportes', 'consultac', 'consultav'));
		// Siempre 24 posiciones (0..23) para que el grafico sea comparable
		$porHora = array_fill(0, 24, 0.0);
		$cantidades = array_fill(0, 24, 0);
		foreach ($consulta->ventasPorHora($fecha_inicio, $fecha_fin) as $r) {
			$h = (int)$r['hora'];
			if ($h >= 0 && $h <= 23) {
				$porHora[$h] = round((float)$r['total'], 2);
				$cantidades[$h] = (int)$r['cantidad'];
			}
		}
		$labels = array();
		for ($h = 0; $h < 24; $h++) {
			$labels[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
		}
		responderJson(array('labels' => $labels, 'data' => array_values($porHora), 'extra' => array_values($cantidades)));
		break;

	default:
		responderJson(array('ok' => false, 'message' => 'Operacion no valida.'), 400);
		break;
}
