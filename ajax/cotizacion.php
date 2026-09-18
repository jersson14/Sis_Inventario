<?php
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('ventas'));
require_once "../modelos/Cotizacion.php";

$cot = new Cotizacion();
$op = isset($_GET['op']) ? $_GET['op'] : '';

switch ($op) {
	case 'guardar':
		$id = enteroSeguro(isset($_POST['idcotizacion']) ? $_POST['idcotizacion'] : 0);
		// Sin "Cambiar precios y descuentos" se cotiza al precio de lista; al
		// editar se respetan las lineas que ya tenia la cotizacion
		if (!usuarioTienePermiso('precios')) {
			require_once "../modelos/Articulo.php";
			$msgPrecio = (new Articulo())->validarPreciosDeLista(
				isset($_POST['idarticulo']) ? $_POST['idarticulo'] : array(),
				isset($_POST['idpresentacion']) ? $_POST['idpresentacion'] : array(),
				isset($_POST['idvariante']) ? $_POST['idvariante'] : array(),
				isset($_POST['cantidad']) ? $_POST['cantidad'] : array(),
				isset($_POST['precio']) ? $_POST['precio'] : array(),
				isset($_POST['descuento']) ? $_POST['descuento'] : array(),
				$id > 0 ? $cot->lineasPrecio($id) : array()
			);
			if ($msgPrecio !== '') {
				responderJson(array('ok' => false, 'message' => $msgPrecio));
			}
		}
		$r = $cot->guardar(
			$id,
			enteroSeguro(isset($_POST['idcliente']) ? $_POST['idcliente'] : 0),
			(int)$_SESSION['idusuario'],
			isset($_POST['fecha_hora']) ? $_POST['fecha_hora'] : '',
			isset($_POST['fecha_validez']) ? $_POST['fecha_validez'] : '',
			isset($_POST['impuesto']) ? $_POST['impuesto'] : 0,
			isset($_POST['observacion']) ? limpiarCadena($_POST['observacion']) : '',
			isset($_POST['condiciones']) ? limpiarCadena($_POST['condiciones']) : '',
			isset($_POST['idarticulo']) ? $_POST['idarticulo'] : array(),
			isset($_POST['cantidad']) ? $_POST['cantidad'] : array(),
			isset($_POST['precio']) ? $_POST['precio'] : array(),
			isset($_POST['descuento']) ? $_POST['descuento'] : array(),
			isset($_POST['idpresentacion']) ? $_POST['idpresentacion'] : array(),
			isset($_POST['idvariante']) ? $_POST['idvariante'] : array()
		);
		if (!empty($r['ok'])) {
			registrarAuditoria('cotizaciones', $id > 0 ? 'editar' : 'crear', 'Cotización ' . $r['numero'] . ' total ' . number_format((float)$r['total'], 2));
		}
		responderJson($r);
		break;

	case 'listar':
		$cot->actualizarVencidas();
		$fi = fechaSegura(isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '', '');
		$ff = fechaSegura(isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '', '');
		$estado = isset($_GET['estado']) ? strtoupper(trim($_GET['estado'])) : '';
		$rs = $cot->listar($fi, $ff, $estado);
		$data = array();
		$badges = array('PENDIENTE' => 'bg-yellow', 'ACEPTADA' => 'bg-green', 'RECHAZADA' => 'bg-red', 'VENCIDA' => 'bg-gray', 'CONVERTIDA' => 'bg-aqua');
		if ($rs) {
			while ($reg = $rs->fetch_object()) {
				$id = (int)$reg->idcotizacion;
				$btn = '<button class="btn btn-default btn-xs" title="Ver detalle" onclick="mostrar(' . $id . ')"><i class="fa fa-eye"></i></button> ';
				$btn .= '<a class="btn btn-info btn-xs" title="Imprimir PDF" target="_blank" href="../reportes/exCotizacion.php?id=' . $id . '"><i class="fa fa-file-pdf-o"></i></a> ';
				if ($reg->estado === 'PENDIENTE' || $reg->estado === 'ACEPTADA') {
					$btn .= '<a class="btn btn-success btn-xs" title="Convertir en venta" href="venta.php?cotizacion=' . $id . '"><i class="fa fa-shopping-cart"></i> Vender</a> ';
				}
				if ($reg->estado === 'PENDIENTE') {
					$btn .= '<button class="btn btn-warning btn-xs" title="Editar" onclick="editar(' . $id . ')"><i class="fa fa-pencil"></i></button> ';
					$btn .= '<button class="btn btn-success btn-xs" title="Marcar aceptada" onclick="cambiarEstado(' . $id . ',\'ACEPTADA\')"><i class="fa fa-thumbs-o-up"></i></button> ';
					$btn .= '<button class="btn btn-danger btn-xs" title="Marcar rechazada" onclick="cambiarEstado(' . $id . ',\'RECHAZADA\')"><i class="fa fa-thumbs-o-down"></i></button>';
				}
				$dias = (int)$reg->dias;
				$validez = e($reg->validez);
				if ($reg->estado === 'PENDIENTE') {
					$validez .= $dias < 0 ? ' <small class="text-danger">vencida</small>' : ($dias <= 3 ? ' <small class="text-warning">' . $dias . ' d</small>' : '');
				}
				$estadoTxt = $reg->estado === 'CONVERTIDA' && $reg->idventa ? 'CONVERTIDA <small>#' . (int)$reg->idventa . '</small>' : $reg->estado;
				$data[] = array(
					'0' => trim($btn),
					'1' => '<strong>' . e($reg->numero) . '</strong>',
					'2' => e($reg->fecha),
					'3' => e($reg->cliente),
					'4' => e($reg->usuario),
					'5' => $validez,
					'6' => '<span class="money">' . formatearMoneda((float)$reg->total) . '</span>',
					'7' => '<span class="label ' . (isset($badges[$reg->estado]) ? $badges[$reg->estado] : 'bg-gray') . '">' . $estadoTxt . '</span>'
				);
			}
		}
		echo json_encode(array('sEcho' => 1, 'iTotalRecords' => count($data), 'iTotalDisplayRecords' => count($data), 'aaData' => $data), JSON_UNESCAPED_UNICODE);
		break;

	case 'mostrar':
		$id = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : (isset($_POST['id']) ? $_POST['id'] : 0));
		$c = $cot->mostrar($id);
		if (!$c) { responderJson(array('ok' => false, 'message' => 'Cotización no encontrada.')); }
		$c['ok'] = true;
		$c['items'] = array();
		$rs = $cot->detalles($id);
		if ($rs) {
			while ($d = $rs->fetch_assoc()) {
				$c['items'][] = array('idarticulo' => (int)$d['idarticulo'], 'idpresentacion' => (int)$d['idpresentacion'], 'idvariante' => (int)$d['idvariante'], 'nombre' => $d['nombre'], 'codigo' => $d['codigo'], 'unidad' => $d['unidad'], 'stock' => round((float)$d['stock'], 3), 'cantidad' => round((float)$d['cantidad'], 3), 'precio' => round((float)$d['precio'], 2), 'descuento' => round((float)$d['descuento'], 2), 'subtotal' => round((float)$d['subtotal'], 2));
			}
		}
		responderJson($c);
		break;

	case 'estado':
		$id = enteroSeguro(isset($_POST['id']) ? $_POST['id'] : 0);
		$r = $cot->cambiarEstado($id, isset($_POST['estado']) ? $_POST['estado'] : '');
		if (!empty($r['ok'])) { registrarAuditoria('cotizaciones', 'estado', 'Cotización #' . $id . ' → ' . strtoupper((string)$_POST['estado'])); }
		responderJson($r);
		break;

	case 'paraVenta':
		$id = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);
		$r = $cot->paraVenta($id);
		if (!$r) { responderJson(array('ok' => false, 'message' => 'Cotización no encontrada.')); }
		if ($r['estado'] === 'CONVERTIDA') { responderJson(array('ok' => false, 'message' => 'Esta cotización ya fue convertida en venta.')); }
		$r['ok'] = true;
		responderJson($r);
		break;

	case 'selectCliente':
		$rs = $cot->clientesActivos();
		if ($rs) { while ($reg = $rs->fetch_object()) { echo '<option value="' . (int)$reg->idpersona . '">' . e($reg->nombre) . '</option>'; } }
		break;

	case 'siguienteNumero':
		responderJson(array('ok' => true, 'numero' => $cot->siguienteNumero(false)));
		break;

	case 'resumen':
		$r = $cot->resumen();
		responderJson($r ? $r : array('pendientes' => 0, 'pendientes_monto' => 0, 'convertidas_30' => 0, 'total_30' => 0));
		break;

	case 'listarArticulos':
		require_once "../modelos/Articulo.php";
		$articulo = new Articulo();
		$rs = $articulo->listarActivosVenta();
		$data = array();
		if ($rs) {
			while ($reg = $rs->fetch_object()) {
				$precio = is_null($reg->precio_venta) ? 0 : (float)$reg->precio_venta;
				$stock = round((float)$reg->stock, 3);
				$pill = $stock <= 0 ? 'stock-empty' : ($stock <= max((float)$reg->stock_minimo, 5) ? 'stock-low' : 'stock-ok');
				$btn = '<button class="btn btn-add-item" type="button" title="Agregar a la cotización" onclick="agregarArticulo(' . (int)$reg->idarticulo . ')"><i class="fa fa-plus-circle"></i> Agregar</button>';
				$imagen = !empty($reg->imagen) ? nombreArchivoSeguro($reg->imagen) : '';
				$img = "<img class='catalog-thumb' src='" . ($imagen !== '' ? '../files/articulos/' . e($imagen) : '../public/img/default-50x50.gif') . "' onerror=\"this.src='../public/img/default-50x50.gif';\" alt=''>";
				$data[] = array('0' => $btn, '1' => e($reg->nombre), '2' => e($reg->categoria), '3' => e($reg->abreviatura), '4' => e($reg->codigo), '5' => '<span class="stock-pill ' . $pill . '">' . formatearCantidad($stock) . '</span>', '6' => formatearMoneda($precio), '7' => $img);
			}
		}
		echo json_encode(array('sEcho' => 1, 'iTotalRecords' => count($data), 'iTotalDisplayRecords' => count($data), 'aaData' => $data), JSON_UNESCAPED_UNICODE);
		break;

	case 'infoArticulo':
		require_once "../modelos/Articulo.php";
		$articulo = new Articulo();
		$ficha = $articulo->fichaOperacion(enteroSeguro(isset($_POST['idarticulo']) ? $_POST['idarticulo'] : 0));
		if (!$ficha) { responderJson(array('ok' => false, 'message' => 'El artículo no existe o está inactivo')); }
		$ficha['ok'] = true;
		$ficha['idpresentacion'] = 0;
		responderJson($ficha);
		break;

	case 'buscarArticuloCodigo':
		require_once "../modelos/Articulo.php";
		$articulo = new Articulo();
		$codigo = isset($_POST['codigo']) ? limpiarCadena($_POST['codigo']) : '';
		$var = Variante::buscarPorCodigo($codigo);
		$pres = $var ? null : $articulo->buscarPresentacionPorCodigo($codigo);
		$idVar = 0;
		if ($var) {
			$ficha = $articulo->fichaOperacion($var['idarticulo']);
			$idPres = 0;
			$idVar = (int)$var['idvariante'];
		} elseif ($pres) {
			$ficha = $articulo->fichaOperacion($pres['idarticulo']);
			$idPres = (int)$pres['idpresentacion'];
		} else {
			$reg = $articulo->buscarActivoPorCodigo($codigo);
			$ficha = $reg ? $articulo->fichaOperacion($reg['idarticulo']) : null;
			$idPres = 0;
		}
		if (!$ficha) { responderJson(array('ok' => false, 'message' => 'No se encontró un artículo con ese código')); }
		$ficha['ok'] = true;
		$ficha['idpresentacion'] = $idPres;
		$ficha['idvariante'] = $idVar;
		responderJson($ficha);
		break;

	default:
		responderJson(array('ok' => false, 'message' => 'Operación no válida.'), 400);
}
