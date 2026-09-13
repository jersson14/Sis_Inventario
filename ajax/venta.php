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

switch ($op) {
	case 'guardaryeditar':
		if ($idventa <= 0) {
			$arrIdArticulo  = (isset($_POST["idarticulo"]) && is_array($_POST["idarticulo"])) ? $_POST["idarticulo"] : array();
			$arrCantidad    = (isset($_POST["cantidad"]) && is_array($_POST["cantidad"])) ? $_POST["cantidad"] : array();
			$arrPrecioVenta = (isset($_POST["precio_venta"]) && is_array($_POST["precio_venta"])) ? $_POST["precio_venta"] : array();
			$arrDescuento   = (isset($_POST["descuento"]) && is_array($_POST["descuento"])) ? $_POST["descuento"] : array();

			$rspta = $venta->insertar(
				$idcliente, $idusuario, $tipo_comprobante, $serie_comprobante, $num_comprobante, $fecha_hora, $impuesto,
				$tipo_pago, $medio_pago, $fecha_vencimiento, $observacion,
				$arrIdArticulo, $arrCantidad, $arrPrecioVenta, $arrDescuento
			);
			if (is_array($rspta) && !empty($rspta["ok"])) {
				registrarAuditoria('ventas', 'crear', "Venta " . $rspta["serie_comprobante"] . "-" . $rspta["num_comprobante"] . " total " . number_format((float)$rspta["total"], 2, '.', ''));
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
					"cuenta_cobrar"=>!empty($rspta["cuenta_cobrar"])
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
		$rspta = $venta->anular($idventa, $idusuario);
		if (!empty($rspta["ok"])) {
			registrarAuditoria('ventas', 'anular', "Venta id " . $idventa . " anulada");
		}
		echo isset($rspta["message"]) ? $rspta["message"] : "No se pudo anular la venta";
		break;

	case 'mostrar':
		$rspta = $venta->mostrar($idventa);
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	case 'listarDetalle':
		$id = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);
		$rspta = $venta->listarDetalle($id);
		$total = 0;
		echo '<thead><tr><th>Artículo</th><th>Unidad</th><th class="text-right">Cantidad</th><th class="text-right">Precio</th><th class="text-right">Dscto.</th><th class="text-right">Subtotal</th></tr></thead><tbody>';
		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				echo '<tr><td>' . e($reg->nombre) . '</td><td>' . e($reg->unidad) . '</td><td class="text-right">' . number_format((float)$reg->cantidad, 0) . '</td><td class="text-right">' . number_format((float)$reg->precio_venta, 2) . '</td><td class="text-right">' . number_format((float)$reg->descuento, 2) . '</td><td class="text-right">' . number_format((float)$reg->subtotal, 2) . '</td></tr>';
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
		$rspta = $venta->listarPorFecha($fecha_inicio, $fecha_fin, $f_estado, $f_tipo_pago);
		$data = array();

		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$id = (int)$reg->idventa;
				$url = ($reg->tipo_comprobante == 'Ticket') ? '../reportes/exTicket.php?id=' : '../reportes/exFactura.php?id=';

				$botones = '<button class="btn btn-warning btn-xs" type="button" title="Ver detalle" onclick="mostrar(' . $id . ')"><i class="fa fa-eye"></i></button> ';
				if ($reg->estado == 'Aceptado') {
					$botones .= '<button class="btn btn-danger btn-xs" type="button" title="Anular venta" onclick="anular(' . $id . ')"><i class="fa fa-close"></i></button> ';
				}
				$botones .= '<a target="_blank" href="' . $url . $id . '" title="Imprimir comprobante"><button class="btn btn-info btn-xs" type="button" title="Imprimir comprobante"><i class="fa fa-file"></i></button></a>';

				$tipoPago = strtoupper((string)$reg->tipo_pago);
				$clasePago = ($tipoPago === 'CREDITO') ? 'bg-yellow' : 'bg-aqua';
				$pagoHtml = '<span class="label ' . $clasePago . '">' . e($tipoPago) . ' · ' . e(strtoupper((string)$reg->medio_pago)) . '</span>';
				if ($tipoPago === 'CREDITO' && !empty($reg->fecha_vencimiento)) {
					$pagoHtml .= ' <small title="Vence">' . e(date('d/m/Y', strtotime($reg->fecha_vencimiento))) . '</small>';
				}

				$data[] = array(
					"0"=>$botones,
					"1"=>e($reg->fecha),
					"2"=>e($reg->cliente),
					"3"=>e($reg->usuario),
					"4"=>e($reg->tipo_comprobante),
					"5"=>e($reg->serie_comprobante . '-' . $reg->num_comprobante),
					"6"=>formatearMoneda((float)$reg->total_venta),
					"7"=>$pagoHtml,
					"8"=>($reg->estado == 'Aceptado') ? '<span class="label bg-green">Aceptado</span>' : '<span class="label bg-red">Anulado</span>'
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
				$nombrejs = addslashes((string)$reg->nombre);
				$unidadjs = addslashes((string)$reg->abreviatura);
				$precio = is_null($reg->precio_venta) ? 0 : (float)$reg->precio_venta;
				$stock = (float)$reg->stock;
				$stockVisible = $stock > 0 ? (int)round($stock) : 0;
				$stockFmt = number_format($stockVisible, 0);
				$stockMinimo = isset($reg->stock_minimo) ? (int)round((float)$reg->stock_minimo) : 0;
				$umbralBajo = max($stockMinimo, 5);

				if ($stockVisible <= 0) {
					$btnAgregar = '<button class="btn btn-add-item btn-add-disabled" type="button" disabled title="Sin stock"><i class="fa fa-ban"></i> Sin stock</button>';
					$stockHtml = '<span class="stock-pill stock-empty">' . $stockFmt . '</span>';
				} elseif ($stockVisible <= $umbralBajo) {
					$btnAgregar = '<button class="btn btn-add-item" type="button" title="Agregar a la venta" onclick="agregarDetalle(' . $idart . ',\'' . $nombrejs . '\',' . $precio . ',\'' . $unidadjs . '\',' . $stockVisible . ')"><i class="fa fa-plus-circle"></i> Agregar</button>';
					$stockHtml = '<span class="stock-pill stock-low">' . $stockFmt . '</span>';
				} else {
					$btnAgregar = '<button class="btn btn-add-item" type="button" title="Agregar a la venta" onclick="agregarDetalle(' . $idart . ',\'' . $nombrejs . '\',' . $precio . ',\'' . $unidadjs . '\',' . $stockVisible . ')"><i class="fa fa-plus-circle"></i> Agregar</button>';
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

	case 'buscarArticuloCodigo':
		$codigo = isset($_POST['codigo']) ? limpiarCadena($_POST['codigo']) : '';
		require_once "../modelos/Articulo.php";
		$articulo = new Articulo();
		$reg = ($codigo !== '') ? $articulo->buscarActivoPorCodigo($codigo) : null;
		if (!$reg) {
			echo json_encode(array("ok"=>false, "message"=>"No se encontro un articulo con ese codigo"), JSON_UNESCAPED_UNICODE);
			break;
		}
		if ((float)$reg['stock'] <= 0) {
			echo json_encode(array("ok"=>false, "message"=>"El articulo no tiene stock disponible"), JSON_UNESCAPED_UNICODE);
			break;
		}
		echo json_encode(array(
			"ok"=>true,
			"idarticulo"=>(int)$reg['idarticulo'],
			"nombre"=>$reg['nombre'],
			"precio_venta"=>(float)$reg['precio_venta'],
			"unidad"=>$reg['abreviatura'],
			"stock"=>(int)round((float)$reg['stock'])
		), JSON_UNESCAPED_UNICODE);
		break;

	case 'resumenDia':
		$totales = $venta->totalesDia();
		$totales["ok"] = true;
		$totales["monto_formateado"] = formatearMoneda($totales["monto"]);
		echo json_encode($totales, JSON_UNESCAPED_UNICODE);
		break;

	default:
		echo json_encode(array("ok"=>false, "message"=>"Operacion no valida"), JSON_UNESCAPED_UNICODE);
		break;
}
