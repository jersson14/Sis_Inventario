<?php
require_once "../config/seguridad.php";
requiereLogin();                    // 401 JSON si no hay sesion; valida CSRF en POST
requierePermiso(array('ventas'));   // 403 JSON si no tiene el permiso
require_once "../modelos/Venta.php";

$venta = new Venta();

// Entradas de cabecera (POST)
$idventa           = enteroSeguro(isset($_POST["idventa"]) ? $_POST["idventa"] : 0);
$idcliente         = enteroSeguro(isset($_POST["idcliente"]) ? $_POST["idcliente"] : 0);
$idusuario         = (int)$_SESSION["idusuario"];
$tipo_comprobante  = isset($_POST["tipo_comprobante"]) ? limpiarCadena($_POST["tipo_comprobante"]) : "";
$serie_comprobante = isset($_POST["serie_comprobante"]) ? limpiarCadena($_POST["serie_comprobante"]) : "";
$num_comprobante   = isset($_POST["num_comprobante"]) ? limpiarCadena($_POST["num_comprobante"]) : "";
$fecha_hora        = isset($_POST["fecha_hora"]) ? limpiarCadena($_POST["fecha_hora"]) : "";
$impuesto          = decimalSeguro(isset($_POST["impuesto"]) ? $_POST["impuesto"] : 0);
$tipo_pago         = isset($_POST["tipo_pago"]) ? limpiarCadena($_POST["tipo_pago"]) : "CONTADO";
$medio_pago        = isset($_POST["medio_pago"]) ? limpiarCadena($_POST["medio_pago"]) : "EFECTIVO";
$fecha_vencimiento = fechaSegura(isset($_POST["fecha_vencimiento"]) ? $_POST["fecha_vencimiento"] : "", "");
$observacion       = isset($_POST["observacion"]) ? limpiarCadena($_POST["observacion"]) : "";

$op = isset($_GET["op"]) ? (string)$_GET["op"] : "";

// Un vendedor sin "Consulta ventas" solo ve y consulta sus propias ventas;
// anular exige el permiso "Anular documentos" (el administrador lo tiene).
$verTodasLasVentas = puedeVerTodasLasVentas();
$idVendedorFiltro = $verTodasLasVentas ? 0 : $idusuario;
$puedeAnular = usuarioTienePermiso('anular');
$idConsulta = $idventa > 0 ? $idventa : enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);
if (in_array($op, array('mostrar', 'listarDetalle', 'anular', 'devolvible', 'devolver'), true) && !$verTodasLasVentas && !$venta->esDelUsuario($idConsulta, $idusuario)) {
	responderJson(array("ok"=>false, "message"=>"Solo puedes consultar tus propias ventas"), 403);
}

switch ($op) {
	case 'guardaryeditar':
		if ($idventa <= 0) {
			// Sin administrador, se vende con la caja abierta: asi cada venta al contado
			// entra al cierre de caja del vendedor.
			if (!usuarioTienePermiso('acceso') && (int)dbValue("SELECT COUNT(*) FROM caja_diaria WHERE idusuario=? AND estado='ABIERTA'", array($idusuario), 0) === 0) {
				echo json_encode(array("ok"=>false, "caja_cerrada"=>true, "message"=>"Abre tu caja antes de vender"), JSON_UNESCAPED_UNICODE);
				break;
			}
			$arrIdArticulo  = (isset($_POST["idarticulo"]) && is_array($_POST["idarticulo"])) ? $_POST["idarticulo"] : array();
			$arrCantidad    = (isset($_POST["cantidad"]) && is_array($_POST["cantidad"])) ? $_POST["cantidad"] : array();
			$arrPrecioVenta = (isset($_POST["precio_venta"]) && is_array($_POST["precio_venta"])) ? $_POST["precio_venta"] : array();
			$arrDescuento   = (isset($_POST["descuento"]) && is_array($_POST["descuento"])) ? $_POST["descuento"] : array();
			$arrPresentacion = (isset($_POST["idpresentacion"]) && is_array($_POST["idpresentacion"])) ? $_POST["idpresentacion"] : array();
			$arrVariante     = (isset($_POST["idvariante"]) && is_array($_POST["idvariante"])) ? $_POST["idvariante"] : array();

			// Sin "Cambiar precios y descuentos": precio de lista y sin descuento,
			// salvo lo ya aprobado en la cotizacion que se esta convirtiendo
			if (!usuarioTienePermiso('precios')) {
				require_once "../modelos/Articulo.php";
				$idcotPrecios = enteroSeguro(isset($_POST["idcotizacion"]) ? $_POST["idcotizacion"] : 0);
				$lineasAprobadas = array();
				if ($idcotPrecios > 0) {
					require_once "../modelos/Cotizacion.php";
					$lineasAprobadas = (new Cotizacion())->lineasPrecio($idcotPrecios);
				}
				$msgPrecio = (new Articulo())->validarPreciosDeLista($arrIdArticulo, $arrPresentacion, $arrVariante, $arrCantidad, $arrPrecioVenta, $arrDescuento, $lineasAprobadas);
				if ($msgPrecio !== '') {
					echo json_encode(array("ok"=>false, "message"=>$msgPrecio), JSON_UNESCAPED_UNICODE);
					break;
				}
			}

			// Pagos: varias lineas (pago mixto / adelanto) o el formato anterior de un solo medio
			$pagoVenta = array(
				"num_operacion"=>isset($_POST["num_operacion"]) ? limpiarCadena($_POST["num_operacion"]) : "",
				"monto_recibido"=>isset($_POST["monto_recibido"]) ? (string)$_POST["monto_recibido"] : ""
			);
			if (isset($_POST["pago_medio"]) && is_array($_POST["pago_medio"])) {
				$pagoVenta["lineas"] = array();
				foreach (array_values($_POST["pago_medio"]) as $i => $medioLinea) {
					$pagoVenta["lineas"][] = array(
						"medio"=>(string)$medioLinea,
						"monto"=>isset($_POST["pago_monto"][$i]) ? (string)$_POST["pago_monto"][$i] : "0",
						"recibido"=>isset($_POST["pago_recibido"][$i]) ? (string)$_POST["pago_recibido"][$i] : "",
						"num_operacion"=>isset($_POST["pago_operacion"][$i]) ? limpiarCadena((string)$_POST["pago_operacion"][$i]) : ""
					);
				}
			}
			$rspta = $venta->insertar(
				$idcliente, $idusuario, $tipo_comprobante, $serie_comprobante, $num_comprobante, $fecha_hora, $impuesto,
				$tipo_pago, $medio_pago, $fecha_vencimiento, $observacion,
				$arrIdArticulo, $arrCantidad, $arrPrecioVenta, $arrDescuento, $arrPresentacion, $arrVariante,
				$pagoVenta,
				Stock::almacenActual()
			);
			if (is_array($rspta) && !empty($rspta["ok"])) {
				registrarAuditoria('ventas', 'crear', "Venta " . $rspta["serie_comprobante"] . "-" . $rspta["num_comprobante"] . " total " . number_format((float)$rspta["total"], 2, '.', ''));
				$idcot = enteroSeguro(isset($_POST["idcotizacion"]) ? $_POST["idcotizacion"] : 0);
				if ($idcot > 0) {
					require_once "../modelos/Cotizacion.php";
					$cotModel = new Cotizacion();
					$cotModel->marcarConvertida($idcot, (int)$rspta["idventa"]);
					registrarAuditoria('cotizaciones', 'convertir', "Cotización #" . $idcot . " convertida en venta #" . (int)$rspta["idventa"]);
				}
				echo json_encode(array(
					"ok"=>true,
					"message"=>"Datos registrados correctamente",
					"idventa"=>(int)$rspta["idventa"],
					"tipo_comprobante"=>$rspta["tipo_comprobante"],
					"serie_comprobante"=>$rspta["serie_comprobante"],
					"num_comprobante"=>$rspta["num_comprobante"],
					"total"=>(float)$rspta["total"],
					"alertas"=>isset($rspta["alertas"]) ? $rspta["alertas"] : array(),
					"caja_registrada"=>!empty($rspta["caja_registrada"]),
					"cuenta_cobrar"=>!empty($rspta["cuenta_cobrar"]),
					"vuelto"=>(float)$rspta["vuelto"],
					"adelanto"=>(float)$rspta["adelanto"],
					"saldo_credito"=>(float)$rspta["saldo_credito"]
				), JSON_UNESCAPED_UNICODE);
			} else {
				echo json_encode(array(
					"ok"=>false,
					"message"=>(is_array($rspta) && isset($rspta["message"])) ? $rspta["message"] : "No se pudo registrar los datos"
				), JSON_UNESCAPED_UNICODE);
			}
		} else {
			echo json_encode(array(
				"ok"=>false,
				"message"=>"Edicion de venta no disponible desde este formulario"
			), JSON_UNESCAPED_UNICODE);
		}
		break;

	case 'siguienteCorrelativo':
		$tipo = isset($_GET["tipo_comprobante"]) ? limpiarCadena($_GET["tipo_comprobante"]) : "Boleta";
		$serie = isset($_GET["serie_comprobante"]) ? limpiarCadena($_GET["serie_comprobante"]) : "";
		$rspta = $venta->obtenerSiguienteCorrelativo($tipo, $serie);
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	case 'anular':
		// Sin el permiso "Anular documentos" se anula con usuario y clave de un
		// encargado; queda en auditoria quien pidio, quien autorizo y por que.
		$motivo = mb_substr(limpiarCadena(isset($_POST['motivo']) ? $_POST['motivo'] : ''), 0, 150, 'UTF-8');
		$autorizo = null;
		if (!$puedeAnular) {
			if (mb_strlen($motivo, 'UTF-8') < 4) {
				echo "Escribe el motivo de la anulación.";
				break;
			}
			list($okAut, $msgAut, $autorizo) = autorizacionEncargado(
				isset($_POST['autoriza_login']) ? $_POST['autoriza_login'] : '',
				isset($_POST['autoriza_clave']) ? $_POST['autoriza_clave'] : '',
				'anular'
			);
			if (!$okAut) {
				echo $msgAut;
				break;
			}
		}
		$rspta = $venta->anular($idventa, $idusuario, $motivo, $autorizo ? (int)$autorizo['idusuario'] : null);
		if (!empty($rspta["ok"])) {
			registrarAuditoria('ventas', 'anular', "Venta id " . $idventa . " anulada"
				. ($autorizo ? " con autorización de " . $autorizo['login'] : "")
				. ($motivo !== '' ? " · motivo: " . $motivo : ""));
		}
		echo isset($rspta["message"]) ? $rspta["message"] : "No se pudo anular la venta";
		break;

	// Devolucion parcial o total por items: nota de credito (v2.5)
	case 'devolvible':
		require_once "../modelos/NotaCredito.php";
		$d = (new NotaCredito())->devolvible($idConsulta);
		if (!$d) {
			echo json_encode(array("ok"=>false, "message"=>"La venta no existe"), JSON_UNESCAPED_UNICODE);
			break;
		}
		$d["ok"] = true;
		$d["puede_devolver"] = $puedeAnular;   // sin el permiso, autoriza un encargado
		echo json_encode($d, JSON_UNESCAPED_UNICODE);
		break;

	case 'devolver':
		require_once "../modelos/NotaCredito.php";
		$motivoDev = isset($_POST['motivo']) ? limpiarCadena($_POST['motivo']) : '';
		$autorizoDev = null;
		if (!$puedeAnular) {
			list($okAut, $msgAut, $autorizoDev) = autorizacionEncargado(
				isset($_POST['autoriza_login']) ? $_POST['autoriza_login'] : '',
				isset($_POST['autoriza_clave']) ? $_POST['autoriza_clave'] : '',
				'anular'
			);
			if (!$okAut) {
				echo json_encode(array("ok"=>false, "message"=>$msgAut), JSON_UNESCAPED_UNICODE);
				break;
			}
		}
		$lineasDev = array();
		$idsDet = (isset($_POST['iddetalle_venta']) && is_array($_POST['iddetalle_venta'])) ? array_values($_POST['iddetalle_venta']) : array();
		$cantDev = (isset($_POST['cantidad_dev']) && is_array($_POST['cantidad_dev'])) ? array_values($_POST['cantidad_dev']) : array();
		$reingDev = (isset($_POST['reingresa']) && is_array($_POST['reingresa'])) ? array_values($_POST['reingresa']) : array();
		foreach ($idsDet as $i => $idDet) {
			$lineasDev[] = array(
				'iddetalle_venta' => enteroSeguro($idDet),
				'cantidad' => isset($cantDev[$i]) ? (string)$cantDev[$i] : '0',
				'reingresa' => !isset($reingDev[$i]) || (string)$reingDev[$i] === '1'
			);
		}
		$reintegroDev = isset($_POST['reintegro']) ? strtoupper(limpiarCadena($_POST['reintegro'])) : '';
		$rspta = (new NotaCredito())->emitir($idventa, $idusuario, $lineasDev, $motivoDev, $reintegroDev,
			array('idautoriza' => $autorizoDev ? (int)$autorizoDev['idusuario'] : null, 'sin_caja' => usuarioTienePermiso('acceso')));
		if (!empty($rspta['ok'])) {
			registrarAuditoria('ventas', 'devolucion', 'NC ' . $rspta['numero'] . ' de ' . $rspta['documento'] . ' por ' . number_format($rspta['total'], 2, '.', '')
				. ' · reintegro ' . $reintegroDev . ($autorizoDev ? ' · autorizó ' . $autorizoDev['login'] : '') . ' · motivo: ' . $motivoDev);
		}
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	// Saldo a favor del cliente (notas de credito) para usarlo al cobrar
	case 'saldoFavor':
		require_once "../modelos/NotaCredito.php";
		$lista = (new NotaCredito())->saldosCliente(enteroSeguro(isset($_GET['idcliente']) ? $_GET['idcliente'] : 0));
		$totalFavor = 0.0;
		foreach ($lista as $l) {
			$totalFavor += (float)$l['saldo_favor'];
		}
		echo json_encode(array("ok"=>true, "notas"=>$lista, "total"=>round($totalFavor, 2)), JSON_UNESCAPED_UNICODE);
		break;

	// Listado de notas de credito (el vendedor sin "Consulta ventas" ve las suyas)
	case 'listarNotas':
		require_once "../modelos/NotaCredito.php";
		$filas = (new NotaCredito())->listar(
			fechaSegura(isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '', ''),
			fechaSegura(isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '', ''),
			$idVendedorFiltro
		);
		$reintegros = array('ORIGINAL' => 'Medios de la venta', 'SALDO_A_FAVOR' => 'Saldo a favor', 'EFECTIVO' => 'Efectivo', 'YAPE' => 'Yape', 'PLIN' => 'Plin',
			'TARJETA' => 'Extorno tarjeta', 'TRANSFERENCIA' => 'Transferencia', 'DEPOSITO' => 'Depósito', 'OTRO' => 'Otro');
		$data = array();
		foreach ($filas as $r) {
			$id = (int)$r['idnota'];
			$data[] = array(
				"0"=>'<button class="btn btn-default btn-xs" type="button" title="Ticket de la nota de crédito" onclick="window.open(\'../reportes/exTicketNC.php?id=' . $id . '\',\'_blank\')"><i class="fa fa-print"></i></button> '
					. '<a class="btn btn-info btn-xs" target="_blank" href="../reportes/exNotaCredito.php?id=' . $id . '" title="Nota de crédito en PDF A4"><i class="fa fa-file-pdf-o"></i></a>',
				"1"=>'<span data-orden="' . e($r['fecha_hora']) . '">' . e(date('d/m/Y H:i', strtotime($r['fecha_hora']))) . '</span>',
				"2"=>'<strong>' . e($r['serie'] . '-' . $r['numero']) . '</strong>',
				"3"=>e($r['tipo_comprobante'] . ' ' . $r['serie_comprobante'] . '-' . $r['num_comprobante']),
				"4"=>e($r['cliente']),
				"5"=>($r['tipo_nota'] === '01' ? '<span class="label label-danger">Anulación</span>' : '<span class="label label-warning">Devolución</span>') . ' <small>' . e($r['motivo']) . '</small>',
				"6"=>formatearMoneda((float)$r['total']),
				"7"=>e(isset($reintegros[$r['reintegro']]) ? $reintegros[$r['reintegro']] : $r['reintegro'])
					. ((float)$r['monto_credito'] > 0 ? '<br><small class="text-soft">deuda -' . e(formatearMoneda((float)$r['monto_credito'])) . '</small>' : '')
					. ((float)$r['saldo_favor'] > 0 ? '<br><span class="label label-success">saldo ' . e(formatearMoneda((float)$r['saldo_favor'])) . '</span>' : ''),
				"8"=>e($r['usuario']) . ($r['autorizo'] !== '' ? '<br><small class="text-soft">autorizó ' . e($r['autorizo']) . '</small>' : '')
			);
		}
		echo json_encode(array("sEcho"=>1, "iTotalRecords"=>count($data), "iTotalDisplayRecords"=>count($data), "aaData"=>$data), JSON_UNESCAPED_UNICODE);
		break;

	// Borrado definitivo: reservado al administrador porque no deja rastro.
	case 'eliminar':
		if (!usuarioTienePermiso('acceso')) {
			echo "Solo un administrador puede eliminar ventas";
			break;
		}
		$rspta = $venta->eliminar($idventa, $idusuario);
		if (!empty($rspta["ok"])) {
			registrarAuditoria('ventas', 'eliminar', "Venta id " . $idventa . " (" . (isset($rspta["documento"]) ? $rspta["documento"] : "") . ") eliminada definitivamente");
		}
		echo isset($rspta["message"]) ? $rspta["message"] : "No se pudo eliminar la venta";
		break;

	case 'mostrar':
		$rspta = $venta->mostrar($idventa);
		if ($rspta) {
			$rspta["pagos"] = $venta->pagos($idventa);
		}
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	case 'listarDetalle':
		$id = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);
		$rspta = $venta->listarDetalle($id);
		$total = 0;
		echo '<thead><tr><th>Artículo</th><th>Unidad</th><th class="text-right">Cantidad</th><th class="text-right">Precio</th><th class="text-right">Dscto.</th><th class="text-right">Subtotal</th></tr></thead><tbody>';
		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				echo '<tr><td>' . e($reg->nombre) . '</td><td>' . e($reg->unidad) . '</td><td class="text-right">' . formatearCantidad($reg->cantidad) . '</td><td class="text-right">' . number_format((float)$reg->precio_venta, 2) . '</td><td class="text-right">' . number_format((float)$reg->descuento, 2) . '</td><td class="text-right">' . number_format((float)$reg->subtotal, 2) . '</td></tr>';
				$total = $total + ((float)$reg->precio_venta * (float)$reg->cantidad - (float)$reg->descuento);
			}
		}
		echo '</tbody><tfoot><tr><th colspan="5" class="text-right">TOTAL</th><th class="text-right"><span class="money" style="font-size:16px">' . formatearMoneda($total) . '</span></th></tr></tfoot>';
		break;

	case 'listar':
		$fecha_inicio = fechaSegura(isset($_GET["fecha_inicio"]) ? $_GET["fecha_inicio"] : '', '');
		$fecha_fin    = fechaSegura(isset($_GET["fecha_fin"]) ? $_GET["fecha_fin"] : '', '');
		$f_estado     = isset($_GET["estado"]) ? limpiarCadena($_GET["estado"]) : '';
		$f_tipo_pago  = isset($_GET["tipo_pago"]) ? limpiarCadena($_GET["tipo_pago"]) : '';
		$rspta = $venta->listarPorFecha($fecha_inicio, $fecha_fin, $f_estado, $f_tipo_pago, $idVendedorFiltro);
		$data = array();
		$puedeEliminar = usuarioTienePermiso('acceso');

		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$id = (int)$reg->idventa;
				$botones = '<button class="btn btn-default btn-xs" type="button" title="Ver detalle" onclick="mostrar(' . $id . ')"><i class="fa fa-eye"></i></button> ';
				if ($reg->estado == 'Aceptado') {
					$botones .= '<button class="btn btn-warning btn-xs" type="button" title="Devolver productos (nota de crédito)" onclick="abrirDevolucion(' . $id . ')"><i class="fa fa-undo"></i></button> ';
					// Sin permiso de anular, el boton pide la clave de un encargado
					$botones .= '<button class="btn btn-danger btn-xs" type="button" title="' . ($puedeAnular ? 'Anular venta' : 'Anular (pide la clave de un encargado)') . '" onclick="anular(' . $id . ')"><i class="fa fa-ban"></i></button> ';
				}
				$botones .= '<button class="btn btn-default btn-xs" type="button" title="Imprimir ticket en la ticketera" onclick="appImprimirTicket(' . $id . ')"><i class="fa fa-print"></i></button> ';
				$botones .= '<a class="btn btn-info btn-xs" target="_blank" href="../reportes/exFactura.php?id=' . $id . '" title="Comprobante en PDF A4"><i class="fa fa-file-pdf-o"></i></a>';
				if ($puedeEliminar && $reg->tipo_comprobante === 'Ticket') {   // boletas y facturas se anulan, no se borran
					$botones .= ' <button class="btn btn-danger btn-xs" type="button" title="Eliminar definitivamente" onclick="eliminar(' . $id . ')"><i class="fa fa-trash"></i></button>';
				}

				$tipoPago = strtoupper((string)$reg->tipo_pago);
				$clasePago = ($tipoPago === 'CREDITO') ? 'bg-yellow' : 'bg-aqua';
				$medioLista = strtoupper((string)$reg->medio_pago);
				$pagoHtml = '<span class="label ' . $clasePago . '">' . e($tipoPago) . ($medioLista !== '' && $medioLista !== $tipoPago ? ' · ' . e($medioLista) : '') . '</span>';
				if ($tipoPago === 'CREDITO' && !empty($reg->fecha_vencimiento)) {
					$pagoHtml .= ' <small title="Vence">' . e(date('d/m/Y', strtotime($reg->fecha_vencimiento))) . '</small>';
				}

				$data[] = array(
					"0"=>$botones,
					"1"=>'<span data-orden="' . e($reg->fecha_orden) . '-' . str_pad((string)$id, 10, '0', STR_PAD_LEFT) . '">' . e($reg->fecha) . '</span>',
					"2"=>e($reg->cliente),
					"3"=>e($reg->usuario),
					"4"=>e($reg->tipo_comprobante),
					"5"=>e($reg->serie_comprobante . '-' . $reg->num_comprobante),
					"6"=>formatearMoneda((float)$reg->total_venta),
					"7"=>$pagoHtml,
					"8"=>(($reg->estado == 'Aceptado') ? '<span class="label bg-green">Aceptado</span>' : '<span class="label bg-red">Anulado</span>')
						. ((float)$reg->devuelto > 0 ? ' <span class="label label-warning" title="Devuelto con notas de crédito"><i class="fa fa-undo"></i> ' . e(formatearMoneda((float)$reg->devuelto)) . '</span>' : '')
				);
			}
		}
		$results = array(
			"sEcho"=>1,
			"iTotalRecords"=>count($data),
			"iTotalDisplayRecords"=>count($data),
			"aaData"=>$data
		);
		echo json_encode($results, JSON_UNESCAPED_UNICODE);
		break;

	case 'selectCliente':
		$clientes = $venta->clientesActivos();
		foreach ($clientes as $reg) {
			echo '<option value="' . (int)$reg['idpersona'] . '" data-doc="' . e($reg['num_documento']) . '">' . e($reg['nombre']) . '</option>';
		}
		break;

	case 'crearClienteRapido':
		require_once "../modelos/Persona.php";
		$persona = new Persona();

		$nombreCliente    = isset($_POST['nombre']) ? limpiarCadena($_POST['nombre']) : '';
		$tipoDocumento    = isset($_POST['tipo_documento']) ? strtoupper(limpiarCadena($_POST['tipo_documento'])) : 'DNI';
		$numDocumento     = isset($_POST['num_documento']) ? limpiarCadena($_POST['num_documento']) : '';
		$direccionCliente = isset($_POST['direccion']) ? limpiarCadena($_POST['direccion']) : '';
		$telefonoCliente  = isset($_POST['telefono']) ? limpiarCadena($_POST['telefono']) : '';
		$emailRaw         = isset($_POST['email']) ? trim((string)$_POST['email']) : '';

		if ($nombreCliente === '') {
			echo json_encode(array("ok"=>false, "message"=>"El nombre del cliente es obligatorio"), JSON_UNESCAPED_UNICODE);
			break;
		}
		if (mb_strlen($nombreCliente) > 100) {
			echo json_encode(array("ok"=>false, "message"=>"El nombre del cliente no puede superar 100 caracteres"), JSON_UNESCAPED_UNICODE);
			break;
		}
		$tiposDocumentoPermitidos = array("DNI", "RUC", "CEDULA");
		if (!in_array($tipoDocumento, $tiposDocumentoPermitidos, true)) {
			$tipoDocumento = "DNI";
		}
		if ($emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL) === false) {
			echo json_encode(array("ok"=>false, "message"=>"El correo electronico no es valido"), JSON_UNESCAPED_UNICODE);
			break;
		}
		$emailCliente = limpiarCadena($emailRaw);

		$idClienteNuevo = $persona->insertarRetornarId("Cliente", $nombreCliente, $tipoDocumento, $numDocumento, $direccionCliente, $telefonoCliente, $emailCliente);
		if (!$idClienteNuevo) {
			echo json_encode(array("ok"=>false, "message"=>"No se pudo registrar el cliente"), JSON_UNESCAPED_UNICODE);
			break;
		}
		registrarAuditoria('ventas', 'crear_cliente', "Cliente rapido " . $nombreCliente . " (id " . (int)$idClienteNuevo . ")");

		echo json_encode(array(
			"ok"=>true,
			"message"=>"Cliente registrado correctamente",
			"idcliente"=>(int)$idClienteNuevo,
			"nombre"=>$nombreCliente,
			"num_documento"=>$numDocumento
		), JSON_UNESCAPED_UNICODE);
		break;

	case 'listarArticulos':
		require_once "../modelos/Articulo.php";
		$articulo = new Articulo();

		$rspta = $articulo->listarActivosVenta();
		$data = array();

		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$idart = (int)$reg->idarticulo;
				$precio = is_null($reg->precio_venta) ? 0 : (float)$reg->precio_venta;
				$stock = (float)$reg->stock;
				$stockVisible = $stock > 0 ? round($stock, 3) : 0;
				$stockFmt = formatearCantidad($stockVisible);
				$stockMinimo = isset($reg->stock_minimo) ? round((float)$reg->stock_minimo, 3) : 0;
				$umbralBajo = max($stockMinimo, 5);

				if ($stockVisible <= 0) {
					$btnAgregar = '<button class="btn btn-add-item btn-add-disabled" type="button" disabled title="Sin stock"><i class="fa fa-ban"></i> Sin stock</button>';
					$stockHtml = '<span class="stock-pill stock-empty">' . $stockFmt . '</span>';
				} elseif ($stockVisible <= $umbralBajo) {
					$btnAgregar = '<button class="btn btn-add-item" type="button" title="Agregar a la venta" onclick="agregarArticulo(' . $idart . ')"><i class="fa fa-plus-circle"></i> Agregar</button>';
					$stockHtml = '<span class="stock-pill stock-low">' . $stockFmt . '</span>';
				} else {
					$btnAgregar = '<button class="btn btn-add-item" type="button" title="Agregar a la venta" onclick="agregarArticulo(' . $idart . ')"><i class="fa fa-plus-circle"></i> Agregar</button>';
					$stockHtml = '<span class="stock-pill stock-ok">' . $stockFmt . '</span>';
				}

				$imagen = !empty($reg->imagen) ? nombreArchivoSeguro($reg->imagen) : '';
				if ($imagen === '') {
					$imagen = 'default-50x50.gif';
				}
				$imgHtml = "<img class='catalog-thumb' src='../files/articulos/" . e($imagen) . "' onerror=\"this.src='../public/img/default-50x50.gif';\" alt='articulo'>";
				$data[] = array(
					"0"=>$btnAgregar,
					"1"=>e($reg->nombre),
					"2"=>e($reg->categoria),
					"3"=>e($reg->abreviatura),
					"4"=>e($reg->codigo),
					"5"=>$stockHtml,
					"6"=>formatearMoneda($precio),
					"7"=>$imgHtml
				);
			}
		}
		$results = array(
			"sEcho"=>1,
			"iTotalRecords"=>count($data),
			"iTotalDisplayRecords"=>count($data),
			"aaData"=>$data
		);
		echo json_encode($results, JSON_UNESCAPED_UNICODE);
		break;

	// Ficha completa para agregar un articulo al detalle (presentaciones, escalas, fraccion)
	case 'infoArticulo':
		require_once "../modelos/Articulo.php";
		$articulo = new Articulo();
		$ficha = $articulo->fichaOperacion(enteroSeguro(isset($_POST['idarticulo']) ? $_POST['idarticulo'] : 0), true);
		if (!$ficha) {
			echo json_encode(array("ok"=>false, "message"=>"El artículo no existe o está inactivo"), JSON_UNESCAPED_UNICODE);
			break;
		}
		$ficha["ok"] = true;
		$ficha["idpresentacion"] = 0;
		$ficha["idvariante"] = 0;
		echo json_encode($ficha, JSON_UNESCAPED_UNICODE);
		break;

	case 'buscarArticuloCodigo':
		$codigo = isset($_POST['codigo']) ? limpiarCadena($_POST['codigo']) : '';
		require_once "../modelos/Articulo.php";
		$articulo = new Articulo();
		// Primero el codigo exacto de una talla/color o de una presentacion; luego el del articulo
		$var = Variante::buscarPorCodigo($codigo);
		$pres = $var ? null : $articulo->buscarPresentacionPorCodigo($codigo);
		$idPresentacion = 0;
		$idVariante = 0;
		if ($var) {
			$idArticulo = (int)$var['idarticulo'];
			$idVariante = (int)$var['idvariante'];
		} elseif ($pres) {
			$idArticulo = (int)$pres['idarticulo'];
			$idPresentacion = (int)$pres['idpresentacion'];
		} else {
			$reg = ($codigo !== '') ? $articulo->buscarActivoPorCodigo($codigo) : null;
			$idArticulo = $reg ? (int)$reg['idarticulo'] : 0;
		}
		$ficha = $idArticulo > 0 ? $articulo->fichaOperacion($idArticulo, true) : null;
		if (!$ficha) {
			echo json_encode(array("ok"=>false, "message"=>"No se encontró un artículo con ese código"), JSON_UNESCAPED_UNICODE);
			break;
		}
		if ($ficha['stock'] <= 0) {
			echo json_encode(array("ok"=>false, "message"=>$ficha['stock_vencido'] > 0 ? "El stock de " . $ficha['nombre'] . " está vencido: dale de baja en Vencimientos" : "El artículo no tiene stock disponible"), JSON_UNESCAPED_UNICODE);
			break;
		}
		$ficha["ok"] = true;
		$ficha["idpresentacion"] = $idPresentacion;
		$ficha["idvariante"] = $idVariante;
		echo json_encode($ficha, JSON_UNESCAPED_UNICODE);
		break;

	// Cuadricula del punto de venta: articulos activos + categorias + ajustes del ticket
	case 'catalogoPos':
		require_once "../modelos/Articulo.php";
		require_once "../modelos/Empresa.php";
		$articuloPos = new Articulo();
		$empresaPos = new Empresa();
		echo json_encode(array(
			"ok"=>true,
			"items"=>$articuloPos->catalogoPos(Stock::almacenActual()),
			"ticket"=>$empresaPos->configTicket()
		), JSON_UNESCAPED_UNICODE);
		break;

	case 'resumenDia':
		$totales = $venta->totalesDia($idVendedorFiltro);
		$totales["ok"] = true;
		$totales["monto_formateado"] = formatearMoneda($totales["monto"]);
		echo json_encode($totales, JSON_UNESCAPED_UNICODE);
		break;

	default:
		echo json_encode(array("ok"=>false, "message"=>"Operacion no valida"), JSON_UNESCAPED_UNICODE);
		break;
}
