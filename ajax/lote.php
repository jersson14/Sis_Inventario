<?php
require_once "../config/seguridad.php";
requiereLogin();                                  // 401 JSON si no hay sesion; valida CSRF en POST
requierePermiso(array('inventario', 'almacen'));
require_once "../modelos/Lote.php";
require_once "../modelos/Inventario.php";
require_once "../modelos/Stock.php";

$op = isset($_GET["op"]) ? (string)$_GET["op"] : "";

// Todo este modulo depende del rubro: con otro rubro no hay lotes que mostrar
if (!Lote::activo() && $op !== '') {
	responderJson(array('ok' => false, 'message' => 'Tu tipo de negocio no usa control de vencimientos. Actívalo en Configuración → Empresa.'));
}

switch ($op) {
	case 'resumen':
		$dias = enteroSeguro(isset($_GET['dias']) ? $_GET['dias'] : 0);
		$r = Lote::resumen($dias > 0 ? $dias : Lote::diasAlerta());
		$r['ok'] = true;
		$r['vencidos_valor_fmt'] = formatearMoneda($r['vencidos_valor']);
		$r['por_vencer_valor_fmt'] = formatearMoneda($r['por_vencer_valor']);
		responderJson($r);
		break;

	case 'listar':
		$estado = isset($_GET['estado']) ? strtoupper(trim((string)$_GET['estado'])) : '';
		if (!in_array($estado, array('', 'VENCIDO', 'POR_VENCER', 'VIGENTE', 'SIN_FECHA'), true)) {
			$estado = '';
		}
		$dias = enteroSeguro(isset($_GET['dias']) ? $_GET['dias'] : 0);
		if ($dias <= 0) {
			$dias = Lote::diasAlerta();
		}
		$puedeDarBaja = usuarioTienePermiso('inventario') || usuarioTienePermiso('almacen');
		$data = array();
		$multiAlm = Stock::multiAlmacen();
		$idTransito = Stock::transito();
		foreach (Lote::listar($estado, $dias) as $l) {
			$diasRest = $l['dias'] === null ? null : (int)$l['dias'];
			if ($diasRest === null) {
				$badge = '<span class="label bg-gray">Sin fecha</span>';
			} elseif ($diasRest < 0) {
				$badge = '<span class="label bg-red">Vencido hace ' . abs($diasRest) . ' día(s)</span>';
			} elseif ($diasRest === 0) {
				$badge = '<span class="label bg-red">Vence hoy</span>';
			} elseif ($diasRest <= $dias) {
				$badge = '<span class="label bg-yellow">Vence en ' . $diasRest . ' día(s)</span>';
			} else {
				$badge = '<span class="label bg-green">Vigente · ' . $diasRest . ' días</span>';
			}
			$valor = round((float)$l['stock'] * (float)$l['costo'], 2);
			$origen = $l['num_comprobante'] !== null
				? e($l['serie_comprobante'] . '-' . $l['num_comprobante']) . ($l['proveedor'] ? '<br><small class="text-soft">' . e($l['proveedor']) . '</small>' : '')
				: '<small class="text-soft">Ajuste de entrada</small>';
			$acciones = '';
			// Lo que va en camino se da de baja al recibir la transferencia
			if ($puedeDarBaja && (int)$l['idalmacen'] !== $idTransito) {
				$acciones = '<button class="btn btn-danger btn-xs" type="button" title="Dar de baja el lote" onclick="darBaja(' . (int)$l['idlote'] . ',' . json_encode(html_entity_decode($l['articulo'], ENT_QUOTES, 'UTF-8')) . ',' . round((float)$l['stock'], 3) . ')"><i class="fa fa-trash"></i></button>';
			}
			$data[] = array(
				"0" => $acciones,
				"1" => e($l['articulo']) . '<br><small class="text-soft">' . e($l['codigo']) . '</small>',
				"2" => ($l['codigo_lote'] !== null && $l['codigo_lote'] !== '' ? e($l['codigo_lote']) : '<span class="text-soft">—</span>')
					. ($multiAlm && $l['almacen'] !== '' ? '<br><small class="text-soft"><i class="fa fa-building-o"></i> ' . e(html_entity_decode($l['almacen'], ENT_QUOTES, 'UTF-8')) . '</small>' : ''),
				"3" => $l['fecha_vencimiento'] ? '<span data-order="' . e($l['fecha_vencimiento']) . '">' . date('d/m/Y', strtotime($l['fecha_vencimiento'])) . '</span>' : '<span class="text-soft" data-order="9999-12-31">—</span>',
				"4" => $badge,
				"5" => formatearCantidad($l['stock']) . ' ' . e($l['unidad']) . '<br><small class="text-soft">de ' . formatearCantidad($l['cantidad_inicial']) . '</small>',
				"6" => formatearMoneda($valor),
				"7" => $origen
			);
		}
		responderJson(array("sEcho" => 1, "iTotalRecords" => count($data), "iTotalDisplayRecords" => count($data), "aaData" => $data));
		break;

	// Baja de un lote completo: ajuste de SALIDA con motivo VENCIMIENTO sobre ese lote
	case 'darBaja':
		$idlote = enteroSeguro(isset($_POST['idlote']) ? $_POST['idlote'] : 0);
		$lote = dbRow("SELECT l.idlote, l.idarticulo, l.stock, l.codigo_lote, l.fecha_vencimiento, l.idalmacen FROM lote l WHERE l.idlote=? AND l.condicion=1", array($idlote));
		if (!$lote || (float)$lote['stock'] <= 0) {
			responderJson(array('ok' => false, 'message' => 'El lote no existe o ya no tiene stock.'));
		}
		$motivo = isset($_POST['motivo']) ? strtoupper(trim((string)$_POST['motivo'])) : 'VENCIMIENTO';
		if (!in_array($motivo, array('VENCIMIENTO', 'MERMA', 'DEVOLUCION_PROVEEDOR', 'DONACION'), true)) {
			$motivo = 'VENCIMIENTO';
		}
		$obs = 'Baja de lote ' . ($lote['codigo_lote'] ? $lote['codigo_lote'] . ' ' : '#' . $lote['idlote'] . ' ')
			. ($lote['fecha_vencimiento'] ? '(vence ' . date('d/m/Y', strtotime($lote['fecha_vencimiento'])) . ')' : '(sin fecha)');
		$extra = isset($_POST['observacion']) ? trim(limpiarCadena($_POST['observacion'])) : '';
		if ($extra !== '') {
			$obs .= ' · ' . $extra;
		}
		$inventario = new Inventario();
		$r = $inventario->registrar((int)$lote['idarticulo'], (int)$_SESSION['idusuario'], 'SALIDA', $motivo, (float)$lote['stock'], 0, $obs, $idlote, '', '', 0, (int)$lote['idalmacen']);
		if (!empty($r['ok'])) {
			registrarAuditoria('inventario', 'baja_lote', $obs . ' · ' . formatearCantidad($lote['stock']) . ' ' . (isset($r['articulo']) ? $r['articulo'] : ''));
		}
		responderJson($r);
		break;

	default:
		responderJson(array('ok' => false, 'message' => 'Operación no válida.'), 400);
}
