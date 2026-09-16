<?php
require_once "../config/seguridad.php";
requiereLogin();                     // 401 JSON si no hay sesion; valida CSRF en POST
requierePermiso(array('compras'));   // 403 JSON si no tiene el permiso
require_once "../modelos/Ingreso.php";

$ingreso = new Ingreso();

// Entradas de cabecera (POST)
$idingreso         = enteroSeguro(isset($_POST["idingreso"]) ? $_POST["idingreso"] : 0);
$idproveedor       = enteroSeguro(isset($_POST["idproveedor"]) ? $_POST["idproveedor"] : 0);
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
		if ($idingreso <= 0) {
			$arrIdArticulo   = (isset($_POST["idarticulo"]) && is_array($_POST["idarticulo"])) ? $_POST["idarticulo"] : array();
			$arrCantidad     = (isset($_POST["cantidad"]) && is_array($_POST["cantidad"])) ? $_POST["cantidad"] : array();
			$arrPrecioCompra = (isset($_POST["precio_compra"]) && is_array($_POST["precio_compra"])) ? $_POST["precio_compra"] : array();
			$arrPrecioVenta  = (isset($_POST["precio_venta"]) && is_array($_POST["precio_venta"])) ? $_POST["precio_venta"] : array();
			$arrPresentacion = (isset($_POST["idpresentacion"]) && is_array($_POST["idpresentacion"])) ? $_POST["idpresentacion"] : array();
			$arrLoteCodigo   = (isset($_POST["lote_codigo"]) && is_array($_POST["lote_codigo"])) ? array_map('limpiarCadena', $_POST["lote_codigo"]) : array();
			$arrLoteVence    = (isset($_POST["lote_vencimiento"]) && is_array($_POST["lote_vencimiento"])) ? $_POST["lote_vencimiento"] : array();
			$arrVariante     = (isset($_POST["idvariante"]) && is_array($_POST["idvariante"])) ? $_POST["idvariante"] : array();

			$rspta = $ingreso->insertar(
				$idproveedor, $idusuario, $tipo_comprobante, $serie_comprobante, $num_comprobante, $fecha_hora, $impuesto,
				$tipo_pago, $medio_pago, $fecha_vencimiento, $observacion,
				$arrIdArticulo, $arrCantidad, $arrPrecioCompra, $arrPrecioVenta, $arrPresentacion, $arrLoteCodigo, $arrLoteVence, $arrVariante
			);
			if (is_array($rspta) && !empty($rspta["ok"])) {
				registrarAuditoria('compras', 'crear', "Ingreso " . $rspta["serie_comprobante"] . "-" . $rspta["num_comprobante"] . " total " . number_format((float)$rspta["total"], 2, '.', ''));
				echo json_encode(array(
					"ok"=>true,
					"message"=>"Datos registrados correctamente",
					"idingreso"=>(int)$rspta["idingreso"],
					"tipo_comprobante"=>$rspta["tipo_comprobante"],
					"serie_comprobante"=>$rspta["serie_comprobante"],
					"num_comprobante"=>$rspta["num_comprobante"],
					"total"=>(float)$rspta["total"],
					"caja_registrada"=>!empty($rspta["caja_registrada"]),
					"cuenta_pagar"=>!empty($rspta["cuenta_pagar"])
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
				"message"=>"Edicion de ingreso no disponible desde este formulario"
			), JSON_UNESCAPED_UNICODE);
		}
		break;

	case 'siguienteCorrelativo':
		$tipo = isset($_GET["tipo_comprobante"]) ? limpiarCadena($_GET["tipo_comprobante"]) : "Boleta";
		$serie = isset($_GET["serie_comprobante"]) ? limpiarCadena($_GET["serie_comprobante"]) : "";
		$rspta = $ingreso->obtenerSiguienteCorrelativo($tipo, $serie);
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	case 'anular':
		$rspta = $ingreso->anular($idingreso, $idusuario);
		if (!empty($rspta["ok"])) {
			registrarAuditoria('compras', 'anular', "Ingreso id " . $idingreso . " anulado");
		}
		echo isset($rspta["message"]) ? $rspta["message"] : "No se pudo anular el ingreso";
		break;

	// Borrado definitivo: reservado al administrador porque no deja rastro.
	case 'eliminar':
		if (!usuarioTienePermiso('acceso')) {
			echo "Solo un administrador puede eliminar compras";
			break;
		}
		$rspta = $ingreso->eliminar($idingreso, $idusuario);
		if (!empty($rspta["ok"])) {
			registrarAuditoria('compras', 'eliminar', "Ingreso id " . $idingreso . " (" . (isset($rspta["documento"]) ? $rspta["documento"] : "") . ") eliminado definitivamente");
		}
		echo isset($rspta["message"]) ? $rspta["message"] : "No se pudo eliminar la compra";
		break;

	case 'mostrar':
		$rspta = $ingreso->mostrar($idingreso);
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	case 'listarDetalle':
		$id = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);
		$rspta = $ingreso->listarDetalle($id);
		$total = 0;
		echo '<thead><tr><th>Artículo</th><th>Unidad</th><th class="text-right">Cantidad</th><th class="text-right">P. Compra</th><th class="text-right">P. Venta</th><th class="text-right">Subtotal</th></tr></thead><tbody>';
		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$subtotal = (float)$reg->precio_compra * (float)$reg->cantidad;
				echo '<tr><td>' . e($reg->nombre) . (!empty($reg->lotes) ? '<br><small class="text-soft"><i class="fa fa-calendar-times-o"></i> Lote ' . e($reg->lotes) . '</small>' : '') . '</td><td>' . e($reg->unidad) . '</td><td class="text-right">' . formatearCantidad($reg->cantidad) . '</td><td class="text-right">' . number_format((float)$reg->precio_compra, 2) . '</td><td class="text-right">' . number_format((float)$reg->precio_venta, 2) . '</td><td class="text-right">' . number_format($subtotal, 2) . '</td></tr>';
				$total = $total + $subtotal;
			}
		}
		echo '</tbody><tfoot><tr><th colspan="5" class="text-right">TOTAL</th><th class="text-right"><span class="money" style="font-size:16px">' . formatearMoneda($total) . '</span></th></tr></tfoot>';
		break;

	case 'listar':
		$fecha_inicio = fechaSegura(isset($_GET["fecha_inicio"]) ? $_GET["fecha_inicio"] : '', '');
		$fecha_fin    = fechaSegura(isset($_GET["fecha_fin"]) ? $_GET["fecha_fin"] : '', '');
		$f_estado     = isset($_GET["estado"]) ? limpiarCadena($_GET["estado"]) : '';
		$f_tipo_pago  = isset($_GET["tipo_pago"]) ? limpiarCadena($_GET["tipo_pago"]) : '';
		$rspta = $ingreso->listarPorFecha($fecha_inicio, $fecha_fin, $f_estado, $f_tipo_pago);
		$data = array();
		$puedeEliminar = usuarioTienePermiso('acceso');

		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$id = (int)$reg->idingreso;
				$url = '../reportes/exIngreso.php?id=';

				$botones = '<button class="btn btn-default btn-xs" type="button" title="Ver detalle" onclick="mostrar(' . $id . ')"><i class="fa fa-eye"></i></button> ';
				if ($reg->estado == 'Aceptado') {
					$botones .= '<button class="btn btn-danger btn-xs" type="button" title="Anular ingreso" onclick="anular(' . $id . ')"><i class="fa fa-ban"></i></button> ';
				}
				$botones .= '<a class="btn btn-info btn-xs" target="_blank" href="' . $url . $id . '" title="Imprimir comprobante"><i class="fa fa-print"></i></a>';
				if ($puedeEliminar) {
					$botones .= ' <button class="btn btn-danger btn-xs" type="button" title="Eliminar definitivamente" onclick="eliminar(' . $id . ')"><i class="fa fa-trash"></i></button>';
				}

				$tipoPago = strtoupper((string)$reg->tipo_pago);
				$clasePago = ($tipoPago === 'CREDITO') ? 'bg-yellow' : 'bg-aqua';
				$pagoHtml = '<span class="label ' . $clasePago . '">' . e($tipoPago) . ' · ' . e(strtoupper((string)$reg->medio_pago)) . '</span>';
				if ($tipoPago === 'CREDITO' && !empty($reg->fecha_vencimiento)) {
					$pagoHtml .= ' <small title="Vence">' . e(date('d/m/Y', strtotime($reg->fecha_vencimiento))) . '</small>';
				}

				$data[] = array(
					"0"=>$botones,
					"1"=>'<span data-orden="' . e($reg->fecha_orden) . '-' . str_pad((string)$id, 10, '0', STR_PAD_LEFT) . '">' . e($reg->fecha) . '</span>',
					"2"=>e($reg->proveedor),
					"3"=>e($reg->usuario),
					"4"=>e($reg->tipo_comprobante),
					"5"=>e($reg->serie_comprobante . '-' . $reg->num_comprobante),
					"6"=>formatearMoneda((float)$reg->total_compra),
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

	case 'selectProveedor':
		$proveedores = $ingreso->proveedoresActivos();
		foreach ($proveedores as $reg) {
			echo '<option value="' . (int)$reg['idpersona'] . '" data-doc="' . e($reg['num_documento']) . '">' . e($reg['nombre']) . '</option>';
		}
		break;

	case 'crearProveedorRapido':
		require_once "../modelos/Persona.php";
		$persona = new Persona();

		$nombreProveedor    = isset($_POST['nombre']) ? limpiarCadena($_POST['nombre']) : '';
		$tipoDocumento      = isset($_POST['tipo_documento']) ? strtoupper(limpiarCadena($_POST['tipo_documento'])) : 'DNI';
		$numDocumento       = isset($_POST['num_documento']) ? limpiarCadena($_POST['num_documento']) : '';
		$direccionProveedor = isset($_POST['direccion']) ? limpiarCadena($_POST['direccion']) : '';
		$telefonoProveedor  = isset($_POST['telefono']) ? limpiarCadena($_POST['telefono']) : '';
		$emailRaw           = isset($_POST['email']) ? trim((string)$_POST['email']) : '';

		if ($nombreProveedor === '') {
			echo json_encode(array("ok"=>false, "message"=>"El nombre del proveedor es obligatorio"), JSON_UNESCAPED_UNICODE);
			break;
		}
		if (mb_strlen($nombreProveedor) > 100) {
			echo json_encode(array("ok"=>false, "message"=>"El nombre del proveedor no puede superar 100 caracteres"), JSON_UNESCAPED_UNICODE);
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
		$emailProveedor = limpiarCadena($emailRaw);

		$idProveedorNuevo = $persona->insertarRetornarId("Proveedor", $nombreProveedor, $tipoDocumento, $numDocumento, $direccionProveedor, $telefonoProveedor, $emailProveedor);
		if (!$idProveedorNuevo) {
			echo json_encode(array("ok"=>false, "message"=>"No se pudo registrar el proveedor"), JSON_UNESCAPED_UNICODE);
			break;
		}
		registrarAuditoria('compras', 'crear_proveedor', "Proveedor rapido " . $nombreProveedor . " (id " . (int)$idProveedorNuevo . ")");

		echo json_encode(array(
			"ok"=>true,
			"message"=>"Proveedor registrado correctamente",
			"idproveedor"=>(int)$idProveedorNuevo,
			"nombre"=>$nombreProveedor,
			"num_documento"=>$numDocumento
		), JSON_UNESCAPED_UNICODE);
		break;

	// Ficha para agregar un articulo al detalle (presentaciones y si admite decimales)
	case 'infoArticulo':
		require_once "../modelos/Articulo.php";
		$articuloFicha = new Articulo();
		$ficha = $articuloFicha->fichaOperacion(enteroSeguro(isset($_POST['idarticulo']) ? $_POST['idarticulo'] : 0));
		if (!$ficha) {
			echo json_encode(array("ok"=>false, "message"=>"El artículo no existe o está inactivo"), JSON_UNESCAPED_UNICODE);
			break;
		}
		$ficha["ok"] = true;
		echo json_encode($ficha, JSON_UNESCAPED_UNICODE);
		break;

	case 'listarArticulos':
		require_once "../modelos/Articulo.php";
		$articulo = new Articulo();

		$rspta = $articulo->listarActivos();
		$data = array();

		if ($rspta) {
			while ($reg = $rspta->fetch_object()) {
				$idart = (int)$reg->idarticulo;
				$stock = (float)$reg->stock;
				$stockFmt = formatearCantidad($stock);
				$precioCompraRef = (isset($reg->precio_compra_ref) && !is_null($reg->precio_compra_ref)) ? (float)$reg->precio_compra_ref : 0;
				$precioVentaRef = (isset($reg->precio_venta_ref) && !is_null($reg->precio_venta_ref)) ? (float)$reg->precio_venta_ref : 0;
				$btnAgregar = '<button class="btn btn-add-item" type="button" title="Agregar al ingreso" onclick="agregarArticulo(' . $idart . ')"><i class="fa fa-plus-circle"></i> Agregar</button>';

				if ($stock <= 0) {
					$stockHtml = '<span class="stock-pill stock-empty">' . $stockFmt . '</span>';
				} elseif ($stock <= 5) {
					$stockHtml = '<span class="stock-pill stock-low">' . $stockFmt . '</span>';
				} else {
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
					"6"=>formatearMoneda((float)$precioCompraRef),
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

	default:
		echo json_encode(array("ok"=>false, "message"=>"Operacion no valida"), JSON_UNESCAPED_UNICODE);
		break;
}
