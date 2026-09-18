<?php
/**
 * Modelo Venta: registro, anulacion y consultas de ventas.
 *
 * Reglas:
 *  - Todas las consultas con datos externos usan sentencias preparadas (dbQuery/dbRow/dbAll/...).
 *  - El total de la venta se recalcula SIEMPRE en servidor.
 *  - El trigger tr_udpStockVenta descuenta el stock al insertar detalle_venta.
 *  - CONTADO: si el usuario tiene caja abierta se registra el ingreso en caja_movimiento.
 *  - CREDITO: se genera automaticamente una cuenta_cobrar.
 */
require_once "../config/Conexion.php";
require_once "../config/negocio.php";   // fracciones y presentaciones
require_once "../modelos/Lote.php";      // lotes y vencimientos (FEFO)
require_once "../modelos/Variante.php";  // tallas y colores
require_once "../modelos/Stock.php";     // stock por almacen

class Venta{

	private $tiposComprobante = array("Boleta", "Factura", "Ticket");
	private $tiposPago = array("CONTADO", "CREDITO");
	// NOTA_CREDITO: saldo a favor de una devolucion (solo como linea de pago, no como medio unico del formulario anterior)
	private $mediosPago = array("EFECTIVO", "DEPOSITO", "TARJETA", "TRANSFERENCIA", "YAPE", "PLIN", "OTRO", "NOTA_CREDITO");
	private $estados = array("Aceptado", "Anulado");

	public function __construct(){
	}

	// ---------- Normalizadores / validadores ----------

	private function error($mensaje){
		return array("ok"=>false, "message"=>$mensaje);
	}

	private function normalizarTipoComprobante($tipo){
		$tipo = trim((string)$tipo);
		if (!in_array($tipo, $this->tiposComprobante, true)) {
			return "Boleta";
		}
		return $tipo;
	}

	private function normalizarSerieComprobante($serie, $tipoComprobante){
		$serie = strtoupper(trim((string)$serie));
		$serie = preg_replace('/[^A-Z0-9]/', '', $serie);
		$serie = substr($serie, 0, 7);
		if ($serie !== '') {
			return $serie;
		}
		if ($tipoComprobante === "Factura") {
			return "F001";
		}
		if ($tipoComprobante === "Ticket") {
			return "T001";
		}
		return "B001";
	}

	private function normalizarFechaHora($valor){
		$raw = trim((string)$valor);
		if ($raw === '') {
			return date("Y-m-d H:i:s");
		}
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
			return $raw . " 00:00:00";
		}
		if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/', $raw)) {
			$normalizado = str_replace("T", " ", $raw);
			if (strlen($normalizado) === 16) {
				$normalizado .= ":00";
			}
			return $normalizado;
		}
		$ts = strtotime($raw);
		if ($ts === false) {
			return date("Y-m-d H:i:s");
		}
		return date("Y-m-d H:i:s", $ts);
	}

	private function normalizarTipoPago($valor){
		$valor = strtoupper(trim((string)$valor));
		return in_array($valor, $this->tiposPago, true) ? $valor : "CONTADO";
	}

	private function normalizarMedioPago($valor){
		$valor = strtoupper(trim((string)$valor));
		return in_array($valor, $this->mediosPago, true) ? $valor : "EFECTIVO";
	}

	/** Texto opcional recortado a $max caracteres; null si viene vacio. */
	private function textoOpcional($valor, $max){
		$valor = trim((string)$valor);
		if ($valor === '') {
			return null;
		}
		if (function_exists('mb_substr')) {
			return mb_substr($valor, 0, $max, 'UTF-8');
		}
		return substr($valor, 0, $max);
	}

	private function obtenerCorrelativoInterno($tipoComprobante, $serieComprobante, $forUpdate = false){
		$tipoComprobante = $this->normalizarTipoComprobante($tipoComprobante);
		$serieComprobante = $this->normalizarSerieComprobante($serieComprobante, $tipoComprobante);
		$lockSql = $forUpdate ? " FOR UPDATE" : "";

		$row = dbRow(
			"SELECT IFNULL(MAX(CAST(num_comprobante AS UNSIGNED)),0) AS maximo
			 FROM venta
			 WHERE tipo_comprobante=? AND serie_comprobante=?" . $lockSql,
			array($tipoComprobante, $serieComprobante)
		);
		$siguiente = ($row && isset($row["maximo"])) ? ((int)$row["maximo"] + 1) : 1;
		if ($siguiente <= 0) {
			$siguiente = 1;
		}

		return array(
			"tipo_comprobante"=>$tipoComprobante,
			"serie_comprobante"=>$serieComprobante,
			"correlativo"=>$siguiente,
			"numero"=>str_pad((string)$siguiente, 8, "0", STR_PAD_LEFT)
		);
	}

	/**
	 * Lineas de pago validadas (pago mixto).
	 * $pago['lineas'] = [ {medio, monto, recibido, num_operacion}, ... ]; sin 'lineas'
	 * se usa el formato anterior (un solo $medio_pago con monto_recibido /
	 * num_operacion) para que los clientes viejos del API sigan funcionando.
	 * Contado: los pagos suman exactamente el total. Credito: los pagos son el
	 * adelanto (menor que el total; el resto va a la cuenta por cobrar).
	 * El vuelto solo existe en efectivo: recibido - monto.
	 * Devuelve array(lineas, medio_resumen, monto_recibido, num_operacion, adelanto, vuelto) o un string de error.
	 */
	private function prepararPagos($tipo_pago, $medio_pago, $total, $pago){
		$crudas = (isset($pago["lineas"]) && is_array($pago["lineas"])) ? $pago["lineas"] : null;
		if ($crudas === null) {
			$crudas = $tipo_pago === "CREDITO" ? array() : array(array(
				"medio"=>$medio_pago,
				"monto"=>$total,
				"recibido"=>isset($pago["monto_recibido"]) ? $pago["monto_recibido"] : "",
				"num_operacion"=>isset($pago["num_operacion"]) ? $pago["num_operacion"] : ""
			));
		}
		if (count($crudas) > 8) {
			return "Demasiadas formas de pago en una venta (máximo 8)";
		}
		$lineas = array();
		$pagado = 0.0;
		$vuelto = 0.0;
		foreach ($crudas as $c) {
			$medio = strtoupper(trim((string)(isset($c["medio"]) ? $c["medio"] : "")));
			if (!in_array($medio, $this->mediosPago, true)) {
				return "Medio de pago no válido: " . $medio;
			}
			$monto = round(decimalSeguro(isset($c["monto"]) ? $c["monto"] : 0, 2, 0), 2);
			if ($monto <= 0) {
				return "Cada pago debe tener un monto mayor que cero";
			}
			$recibido = null;
			$operacion = null;
			if ($medio === "EFECTIVO") {
				$raw = trim((string)(isset($c["recibido"]) ? $c["recibido"] : ""));
				if ($raw !== "") {
					$recibido = round(decimalSeguro($raw, 2, 0), 2);
					if ($recibido <= 0) {
						$recibido = null;
					} elseif ($recibido + 0.001 < $monto) {
						return count($crudas) === 1 && $tipo_pago === "CONTADO"
							? "El efectivo recibido (" . number_format($recibido, 2) . ") es menor que el total de la venta (" . number_format($total, 2) . ")"
							: "El efectivo recibido (" . number_format($recibido, 2) . ") es menor que el monto en efectivo (" . number_format($monto, 2) . ")";
					} else {
						$vuelto += $recibido - $monto;
					}
				}
			} else {
				$operacion = $this->textoOpcional(isset($c["num_operacion"]) ? $c["num_operacion"] : "", 40);
				if ($medio === "NOTA_CREDITO" && $operacion === null) {
					return "Indica el número de la nota de crédito con que paga el cliente";
				}
			}
			$pagado += $monto;
			$lineas[] = array("medio"=>$medio, "monto"=>$monto, "recibido"=>$recibido, "num_operacion"=>$operacion);
		}
		$pagado = round($pagado, 2);
		if ($tipo_pago === "CONTADO") {
			if (!$lineas) {
				return "Indica cómo pagó el cliente";
			}
			if (abs($pagado - $total) > 0.009) {
				return "Los pagos (" . number_format($pagado, 2) . ") no suman el total de la venta (" . number_format($total, 2) . ")";
			}
		} elseif ($pagado >= $total - 0.009) {
			return "El adelanto cubre todo el total: registra la venta al contado";
		}
		$medios = array_values(array_unique(array_map(function ($l) { return $l["medio"]; }, $lineas)));
		$resumen = !$lineas ? "CREDITO" : (count($medios) === 1 ? $medios[0] : "MIXTO");
		$unica = count($lineas) === 1 ? $lineas[0] : null;
		return array(
			"lineas"=>$lineas,
			"medio_resumen"=>$resumen,
			"monto_recibido"=>($unica && $unica["medio"] === "EFECTIVO") ? $unica["recibido"] : null,
			"num_operacion"=>($unica && $unica["medio"] !== "EFECTIVO") ? $unica["num_operacion"] : null,
			"adelanto"=>$tipo_pago === "CREDITO" ? $pagado : 0.0,
			"vuelto"=>round($vuelto, 2)
		);
	}

	/** Pagos de una venta (una fila por medio). */
	public function pagos($idventa){
		$filas = dbAll(
			"SELECT idpago, medio_pago, monto, recibido, num_operacion, fecha_hora FROM venta_pago WHERE idventa=? ORDER BY idpago",
			array((int)$idventa)
		);
		foreach ($filas as &$f) {
			$f["monto"] = round((float)$f["monto"], 2);
			$f["recibido"] = $f["recibido"] === null ? null : round((float)$f["recibido"], 2);
			$f["vuelto"] = ($f["medio_pago"] === "EFECTIVO" && $f["recibido"] !== null) ? round(max(0, $f["recibido"] - $f["monto"]), 2) : 0.0;
		}
		unset($f);
		return $filas;
	}

	/** Impuesto por defecto de la empresa (porcentaje, ej. 18). */
	private function impuestoEmpresa(){
		$imp = (float)dbValue("SELECT impuesto_default FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1", array(), 18);
		return ($imp >= 0 && $imp <= 100) ? round($imp, 2) : 18.0;
	}

	/** Id de la caja ABIERTA del usuario (0 si no tiene). */
	private function cajaAbiertaUsuario($idusuario){
		return (int)dbValue(
			"SELECT idcaja FROM caja_diaria WHERE idusuario=? AND estado='ABIERTA' ORDER BY idcaja DESC LIMIT 1",
			array((int)$idusuario),
			0
		);
	}

	// ---------- Correlativo ----------

	public function obtenerSiguienteCorrelativo($tipoComprobante, $serieComprobante){
		$data = $this->obtenerCorrelativoInterno($tipoComprobante, $serieComprobante, false);
		return array(
			"ok"=>true,
			"tipo_comprobante"=>$data["tipo_comprobante"],
			"serie_comprobante"=>$data["serie_comprobante"],
			"correlativo"=>$data["correlativo"],
			"numero"=>$data["numero"]
		);
	}

	// ---------- Registro ----------

	/**
	 * Registra una venta con su detalle. El total se calcula en servidor.
	 * Devuelve array {ok, message} o
	 * {ok:true, idventa, tipo_comprobante, serie_comprobante, num_comprobante, total, alertas, caja_registrada, cuenta_cobrar}.
	 */
	public function insertar($idcliente,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$tipo_pago,$medio_pago,$fecha_vencimiento,$observacion,$idarticulo,$cantidad,$precio_venta,$descuento,$idpresentacion = array(),$idvariante = array(),$pago = array(),$idalmacen = 0){
		$idcliente = (int)$idcliente;
		$idusuario = (int)$idusuario;

		if ($idusuario <= 0) {
			return $this->error("Sesion de usuario no valida");
		}
		if ($idcliente <= 0) {
			return $this->error("Debes seleccionar un cliente valido");
		}
		$cliente = dbRow(
			"SELECT idpersona FROM persona WHERE idpersona=? AND tipo_persona='Cliente' AND condicion=1 LIMIT 1",
			array($idcliente)
		);
		if (!$cliente) {
			return $this->error("El cliente seleccionado no existe o esta inactivo");
		}

		if (!is_array($idarticulo) || count($idarticulo) === 0) {
			return $this->error("Debes agregar al menos un articulo a la venta");
		}
		if (!is_array($cantidad) || count($cantidad) !== count($idarticulo)) {
			return $this->error("El detalle de cantidades no es valido");
		}
		if (!is_array($precio_venta)) {
			$precio_venta = array();
		}
		if (!is_array($descuento)) {
			$descuento = array();
		}
		if (!is_array($idpresentacion)) {
			$idpresentacion = array();
		}
		if (!is_array($idvariante)) {
			$idvariante = array();
		}

		// Cabecera
		$fecha_hora = $this->normalizarFechaHora($fecha_hora);
		$fecha_venta = substr($fecha_hora, 0, 10);
		// $impuesto que llega del navegador se ignora: lo fija el tipo de comprobante (mas abajo)
		$tipo_pago = $this->normalizarTipoPago($tipo_pago);
		$medio_pago = $this->normalizarMedioPago($medio_pago);
		$fecha_vencimiento = fechaSegura($fecha_vencimiento, '');
		if ($tipo_pago === "CREDITO") {
			if ($fecha_vencimiento === '') {
				$fecha_vencimiento = date("Y-m-d", strtotime($fecha_venta . " +30 days"));
			}
			if ($fecha_vencimiento < $fecha_venta) {
				return $this->error("La fecha de vencimiento no puede ser anterior a la fecha de la venta");
			}
		} else {
			$fecha_vencimiento = null;
		}
		$observacion = $this->textoOpcional($observacion, 200);

		// Detalle: validaciones y total en servidor
		// cantidadesSolicitadas se acumula en unidades base (lo que mueve el stock)
		$cantidadesSolicitadas = array();
		$detalles = array();
		$total = 0.0;
		$n = count($idarticulo);
		$fraccion = articulosPermitenFraccion($idarticulo);
		$conVariantes = Variante::articulosConVariantes($idarticulo);
		$cantidadesVariante = array();
		for ($i = 0; $i < $n; $i++) {
			$idArticuloActual = (int)$idarticulo[$i];
			$variante = Variante::resolverDetalle($idArticuloActual, isset($idvariante[$i]) ? $idvariante[$i] : 0, $conVariantes);
			if (is_string($variante)) {
				return $this->error($variante);
			}
			$idVarianteActual = $variante ? (int)$variante["idvariante"] : null;
			$presentacion = resolverPresentacionDetalle($idArticuloActual, isset($idpresentacion[$i]) ? $idpresentacion[$i] : 0);
			if ($presentacion === false) {
				return $this->error("Una de las presentaciones del detalle no es valida o fue desactivada");
			}
			list($idPresentacionActual, $factorActual) = $presentacion;
			// Las presentaciones (cajas, paquetes) se venden enteras; la unidad base puede fraccionarse
			$permiteFraccion = $idPresentacionActual === null && !empty($fraccion[$idArticuloActual]);
			$cantidadActual = cantidadSegura($cantidad[$i], $permiteFraccion);
			$precioActual = isset($precio_venta[$i]) ? decimalSeguro($precio_venta[$i], 2, -1) : -1;
			$descuentoActual = isset($descuento[$i]) ? decimalSeguro($descuento[$i], 2, 0) : 0.0;

			if ($idArticuloActual <= 0) {
				return $this->error("Se detecto un articulo invalido en el detalle");
			}
			if ($cantidadActual <= 0) {
				return $this->error("La cantidad debe ser mayor que cero");
			}
			if ($precioActual < 0) {
				return $this->error("El precio de venta no puede ser negativo");
			}
			if ($descuentoActual < 0) {
				return $this->error("El descuento no puede ser negativo");
			}
			$subtotalBruto = round($cantidadActual * $precioActual, 2);
			if ($descuentoActual > $subtotalBruto) {
				return $this->error("El descuento no puede superar el subtotal del artículo");
			}

			if (!isset($cantidadesSolicitadas[$idArticuloActual])) {
				$cantidadesSolicitadas[$idArticuloActual] = 0.0;
			}
			$cantidadesSolicitadas[$idArticuloActual] = round($cantidadesSolicitadas[$idArticuloActual] + ($cantidadActual * $factorActual), 3);
			if ($idVarianteActual !== null) {
				$cantidadesVariante[$idVarianteActual] = round((isset($cantidadesVariante[$idVarianteActual]) ? $cantidadesVariante[$idVarianteActual] : 0) + ($cantidadActual * $factorActual), 3);
			}
			$detalles[] = array(
				"idarticulo"=>$idArticuloActual,
				"idvariante"=>$idVarianteActual,
				"idpresentacion"=>$idPresentacionActual,
				"factor"=>$factorActual,
				"cantidad"=>$cantidadActual,
				"precio_venta"=>(float)$precioActual,
				"descuento"=>(float)$descuentoActual
			);
			$total += ($subtotalBruto - $descuentoActual);
		}
		$total = round($total, 2);
		ksort($cantidadesSolicitadas);

		// Pagos: uno o varios medios (pago mixto); al credito, el adelanto
		$pagos = $this->prepararPagos($tipo_pago, $medio_pago, $total, is_array($pago) ? $pago : array());
		if (is_string($pagos)) {
			return $this->error($pagos);
		}
		$medio_pago = $pagos["medio_resumen"];
		$numOperacion = $pagos["num_operacion"];
		$montoRecibido = $pagos["monto_recibido"];

		$tipo_comprobante = $this->normalizarTipoComprobante($tipo_comprobante);
		$serie_comprobante = $this->normalizarSerieComprobante($serie_comprobante, $tipo_comprobante);
		// El numero siempre es el siguiente correlativo de la serie (se asigna con
		// bloqueo dentro de la transaccion): nunca uno escrito a mano, para que la
		// numeracion no tenga huecos ni repetidos (requisito de SUNAT).
		$num_comprobante = '';
		// IGV: boleta y factura llevan el impuesto de la empresa (los precios ya lo
		// incluyen; solo cambia el desglose). La nota de venta interna (Ticket) no
		// es comprobante tributario y no desglosa IGV.
		$impuesto = ($tipo_comprobante === "Ticket") ? 0.0 : $this->impuestoEmpresa();

		$ctx = array(
			"idcliente"=>$idcliente,
			"idusuario"=>$idusuario,
			"tipo_comprobante"=>$tipo_comprobante,
			"serie_comprobante"=>$serie_comprobante,
			"num_comprobante"=>$num_comprobante,
			"fecha_hora"=>$fecha_hora,
			"fecha_venta"=>$fecha_venta,
			"fecha_vencimiento"=>$fecha_vencimiento,
			"impuesto"=>(float)$impuesto,
			"tipo_pago"=>$tipo_pago,
			"medio_pago"=>$medio_pago,
			"num_operacion"=>$numOperacion,
			"monto_recibido"=>$montoRecibido,
			"observacion"=>$observacion,
			"total"=>(float)$total,
			"pagos"=>$pagos["lineas"],
			"adelanto"=>$pagos["adelanto"],
			// Almacen del que sale la mercaderia (el de la sesion del vendedor)
			"idalmacen"=>((int)$idalmacen > 0 && Stock::almacenValido($idalmacen)) ? (int)$idalmacen : Stock::principal()
		);
		$mensajeError = '';

		$resultado = dbTransaccion(function($cx) use ($ctx, $detalles, $cantidadesSolicitadas, $cantidadesVariante, &$mensajeError) {
			$tipo = $ctx["tipo_comprobante"];
			$serie = $ctx["serie_comprobante"];
			$num = $ctx["num_comprobante"];

			// Correlativo automatico (con bloqueo) o verificacion de unicidad
			$correlativo = $this->obtenerCorrelativoInterno($tipo, $serie, true);
			$num = $correlativo["numero"];

			// Stock con bloqueo de filas
			$ids = array_keys($cantidadesSolicitadas);
			$placeholders = implode(",", array_fill(0, count($ids), "?"));
			$filas = dbAll(
				"SELECT idarticulo,nombre,stock,condicion FROM articulo WHERE idarticulo IN (" . $placeholders . ") FOR UPDATE",
				$ids
			);
			$stockActual = array();
			foreach ($filas as $f) {
				$stockActual[(int)$f["idarticulo"]] = $f;
			}
			$erroresStock = array();
			$enAlmacenTxt = Stock::multiAlmacen() ? " en " . Stock::nombre($ctx["idalmacen"]) : "";
			foreach ($cantidadesSolicitadas as $idArt => $cantSolicitada) {
				if (!isset($stockActual[$idArt])) {
					$erroresStock[] = "Articulo ID " . $idArt . " no encontrado";
					continue;
				}
				if ((int)$stockActual[$idArt]["condicion"] !== 1) {
					$erroresStock[] = $stockActual[$idArt]["nombre"] . " esta inactivo";
					continue;
				}
				// Disponible en el almacen de la venta; lo vencido no se vende
				$stockDisp = Stock::enAlmacen($ctx["idalmacen"], $idArt, null, true);
				$vencido = Lote::activo() ? Lote::stockVencido($idArt, $ctx["idalmacen"]) : 0.0;
				if (round($stockDisp - $vencido, 3) + 0.0005 < $cantSolicitada) {
					$otros = round((float)$stockActual[$idArt]["stock"] - $stockDisp, 3);
					$erroresStock[] = $stockActual[$idArt]["nombre"] . " (disponible" . $enAlmacenTxt . ": " . formatearCantidad(max($stockDisp - $vencido, 0)) . ($vencido > 0 ? ", vencido: " . formatearCantidad($vencido) : "") . ", solicitado: " . formatearCantidad($cantSolicitada) . ($otros > 0.0005 ? "; hay " . formatearCantidad($otros) . " en otros almacenes" : "") . ")";
				}
			}
			// Stock por talla/color
			if (count($cantidadesVariante) > 0) {
				$idsVar = array_keys($cantidadesVariante);
				$filasVar = dbAll(
					"SELECT v.idvariante, v.idarticulo, v.talla, v.color, v.stock, a.nombre FROM articulo_variante v INNER JOIN articulo a ON a.idarticulo=v.idarticulo
					 WHERE v.idvariante IN (" . implode(",", array_fill(0, count($idsVar), "?")) . ") FOR UPDATE",
					$idsVar
				);
				foreach ($filasVar as $fv) {
					$pedido = $cantidadesVariante[(int)$fv["idvariante"]];
					$enAlm = Stock::enAlmacen($ctx["idalmacen"], (int)$fv["idarticulo"], (int)$fv["idvariante"]);
					if ($enAlm + 0.0005 < $pedido) {
						$erroresStock[] = $fv["nombre"] . " " . Variante::etiqueta($fv["talla"], $fv["color"]) . " (disponible" . $enAlmacenTxt . ": " . formatearCantidad(max($enAlm, 0)) . ", solicitado: " . formatearCantidad($pedido) . ")";
					}
				}
			}
			if (count($erroresStock) > 0) {
				$mensajeError = "Stock insuficiente: " . implode("; ", $erroresStock);
				return false;
			}

			// Caja abierta del usuario: recibe cada pago (contado o adelanto del credito)
			$idcaja = 0;
			if (count($ctx["pagos"]) > 0) {
				$idcaja = $this->cajaAbiertaUsuario($ctx["idusuario"]);
			}

			$idventa = dbInsert(
				"INSERT INTO venta (idcliente,idusuario,idalmacen,tipo_comprobante,serie_comprobante,num_comprobante,fecha_hora,fecha_vencimiento,impuesto,tipo_pago,medio_pago,num_operacion,idcaja,total_venta,monto_recibido,estado,observacion)
				 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Aceptado',?)",
				array(
					$ctx["idcliente"], $ctx["idusuario"], $ctx["idalmacen"], $tipo, $serie, $num, $ctx["fecha_hora"],
					$ctx["fecha_vencimiento"], $ctx["impuesto"], $ctx["tipo_pago"], $ctx["medio_pago"], $ctx["num_operacion"],
					($idcaja > 0 ? $idcaja : null), $ctx["total"], $ctx["monto_recibido"], $ctx["observacion"]
				)
			);
			if ($idventa <= 0) {
				$mensajeError = "No se pudo registrar la cabecera de la venta";
				return false;
			}

			// Detalle (el trigger descuenta el stock) y salida de lotes en orden FEFO
			$usaLotes = Lote::activo();
			foreach ($detalles as $d) {
				$iddetalle = dbInsert(
					"INSERT INTO detalle_venta (idventa,idarticulo,idpresentacion,idvariante,cantidad,factor,precio_venta,descuento) VALUES (?,?,?,?,?,?,?,?)",
					array($idventa, $d["idarticulo"], $d["idpresentacion"], $d["idvariante"], $d["cantidad"], $d["factor"], $d["precio_venta"], $d["descuento"])
				);
				if ($iddetalle <= 0) {
					$mensajeError = "No se pudo registrar el detalle de la venta";
					return false;
				}
				if ($usaLotes) {
					$ref = array("tipo"=>"VENTA", "idventa"=>$idventa, "iddetalle_venta"=>$iddetalle);
					if (Lote::consumir($d["idarticulo"], round($d["cantidad"] * $d["factor"], 3), $ref, $ctx["idalmacen"]) === false) {
						$mensajeError = "No se pudo descontar los lotes del articulo";
						return false;
					}
				}
			}
			if (!$usaLotes) {
				// Sin control de lotes igual se mantiene la regla: los lotes no superan el stock
				foreach (array_keys($cantidadesSolicitadas) as $idArt) {
					if (!Lote::ajustarAlStock($idArt)) {
						$mensajeError = "No se pudo actualizar los lotes del articulo";
						return false;
					}
				}
			}

			$documento = $tipo . " " . $serie . "-" . $num;
			$cuentaCobrar = false;
			$cajaRegistrada = false;

			// Un pago por medio; en la caja, un movimiento por medio (el arqueo
			// cuenta solo el efectivo)
			$credito = $ctx["tipo_pago"] === "CREDITO";
			foreach ($ctx["pagos"] as $p) {
				// Saldo a favor de una nota de credito: se descuenta de la nota y no pasa por la caja
				$idnotaPago = null;
				if ($p["medio"] === "NOTA_CREDITO") {
					require_once "../modelos/NotaCredito.php";
					$idnotaPago = NotaCredito::consumirSaldo($p["num_operacion"], $ctx["idcliente"], $p["monto"]);
					if (is_string($idnotaPago)) {
						$mensajeError = $idnotaPago;
						return false;
					}
				}
				$idpago = dbInsert(
					"INSERT INTO venta_pago (idventa,medio_pago,monto,recibido,num_operacion,idcaja,idnota,fecha_hora) VALUES (?,?,?,?,?,?,?,NOW())",
					array($idventa, $p["medio"], $p["monto"], $p["recibido"], $p["num_operacion"], ($idcaja > 0 && $idnotaPago === null) ? $idcaja : null, $idnotaPago)
				);
				if ($idpago <= 0) {
					$mensajeError = "No se pudo registrar el pago de la venta";
					return false;
				}
				if ($idcaja > 0 && $idnotaPago === null) {
					$idmov = dbInsert(
						"INSERT INTO caja_movimiento (idcaja,idusuario,tipo,concepto,referencia,medio_pago,monto,fecha_hora)
						 VALUES (?,?,'INGRESO',?,?,?,?,NOW())",
						array($idcaja, $ctx["idusuario"], ($credito ? "Adelanto venta " : "Venta ") . $documento, "V-" . $idventa, $p["medio"], $p["monto"])
					);
					if ($idmov <= 0) {
						$mensajeError = "No se pudo registrar el movimiento de caja";
						return false;
					}
					$cajaRegistrada = true;
				}
			}

			if ($credito) {
				$saldo = round($ctx["total"] - $ctx["adelanto"], 2);
				$idcc = dbInsert(
					"INSERT INTO cuenta_cobrar (idcliente,idventa,fecha_emision,fecha_vencimiento,documento_ref,monto_total,saldo,estado,observacion)
					 VALUES (?,?,?,?,?,?,?,'PENDIENTE',?)",
					array($ctx["idcliente"], $idventa, $ctx["fecha_venta"], $ctx["fecha_vencimiento"], $documento, $saldo, $saldo,
						"Generada automaticamente desde venta" . ($ctx["adelanto"] > 0 ? " (total " . number_format($ctx["total"], 2, '.', '') . ", adelanto " . number_format($ctx["adelanto"], 2, '.', '') . ")" : ""))
				);
				if ($idcc <= 0) {
					$mensajeError = "No se pudo generar la cuenta por cobrar";
					return false;
				}
				$cuentaCobrar = true;
			}

			return array(
				"idventa"=>$idventa,
				"tipo_comprobante"=>$tipo,
				"serie_comprobante"=>$serie,
				"num_comprobante"=>$num,
				"caja_registrada"=>$cajaRegistrada,
				"cuenta_cobrar"=>$cuentaCobrar
			);
		});

		if ($resultado === false || !is_array($resultado)) {
			return $this->error($mensajeError !== '' ? $mensajeError : "No se pudo registrar la venta");
		}

		// Alertas de stock bajo (fuera de la transaccion)
		$alertas = array();
		$ids = array_keys($cantidadesSolicitadas);
		if (count($ids) > 0) {
			$placeholders = implode(",", array_fill(0, count($ids), "?"));
			$filas = dbAll(
				"SELECT idarticulo,codigo,nombre,stock,IFNULL(stock_minimo,0) AS stock_minimo
				 FROM articulo
				 WHERE idarticulo IN (" . $placeholders . ")
				 AND stock<=GREATEST(IFNULL(stock_minimo,0),5)",
				$ids
			);
			foreach ($filas as $reg) {
				$alertas[] = array(
					"idarticulo"=>(int)$reg["idarticulo"],
					"codigo"=>$reg["codigo"],
					"nombre"=>$reg["nombre"],
					"stock"=>round((float)$reg["stock"], 3),
					"stock_minimo"=>round((float)$reg["stock_minimo"], 3)
				);
			}
		}

		return array(
			"ok"=>true,
			"idventa"=>(int)$resultado["idventa"],
			"tipo_comprobante"=>$resultado["tipo_comprobante"],
			"serie_comprobante"=>$resultado["serie_comprobante"],
			"num_comprobante"=>$resultado["num_comprobante"],
			"total"=>$total,
			"alertas"=>$alertas,
			"caja_registrada"=>(bool)$resultado["caja_registrada"],
			"cuenta_cobrar"=>(bool)$resultado["cuenta_cobrar"],
			"vuelto"=>$pagos["vuelto"],
			"adelanto"=>$pagos["adelanto"],
			"saldo_credito"=>$tipo_pago === "CREDITO" ? round($total - $pagos["adelanto"], 2) : 0.0
		);
	}

	// ---------- Anulacion ----------

	/**
	 * Anula una venta: emite una nota de credito 01 por todo lo vendido, que
	 * devuelve el stock (a sus tallas y lotes), anula la cuenta por cobrar (si no
	 * tiene cobros) y revierte cada pago por su medio (egreso en la caja donde
	 * entro, o en la caja abierta de quien anula). Devuelve array {ok, message}.
	 */
	public function anular($idventa, $idusuario, $motivo = "", $idautoriza = null){
		$idventa = (int)$idventa;
		$idusuario = (int)$idusuario;
		if ($idventa <= 0) {
			return $this->error("Venta no valida");
		}
		if ($idusuario <= 0) {
			return $this->error("Sesion de usuario no valida");
		}
		require_once "../modelos/NotaCredito.php";
		$r = (new NotaCredito())->anularVenta($idventa, $idusuario, $motivo, $idautoriza);
		if (empty($r["ok"])) {
			return $r;
		}
		return array("ok"=>true, "message"=>"Venta anulada correctamente · nota de crédito " . $r["numero"], "idnota"=>$r["idnota"], "numero_nota"=>$r["numero"]);
	}

	/**
	 * Borrado definitivo de una venta (solo perfil con permiso 'acceso').
	 *
	 * A diferencia de anular(), aqui no queda rastro del documento, por eso se
	 * exige que no haya cobros aplicados ni movimientos en una caja ya cerrada
	 * (eso rompería un arqueo historico).
	 *
	 * El stock se devuelve UNICAMENTE si la venta seguia vigente; si ya estaba
	 * anulada el stock se repuso en la anulacion y volver a sumarlo duplicaria
	 * las existencias.
	 */
	public function eliminar($idventa, $idusuario){
		$idventa = (int)$idventa;
		$idusuario = (int)$idusuario;
		if ($idventa <= 0) {
			return $this->error("Venta no valida");
		}
		if ($idusuario <= 0) {
			return $this->error("Sesion de usuario no valida");
		}
		$mensajeError = '';
		$documento = '';

		$resultado = dbTransaccion(function($cx) use ($idventa, &$mensajeError, &$documento) {
			$venta = dbRow(
				"SELECT idventa,estado,tipo_comprobante,serie_comprobante,num_comprobante,total_venta
				 FROM venta WHERE idventa=? FOR UPDATE",
				array($idventa)
			);
			if (!$venta) {
				$mensajeError = "La venta no existe";
				return false;
			}
			$documento = $venta["tipo_comprobante"] . " " . $venta["serie_comprobante"] . "-" . $venta["num_comprobante"];

			// Boletas y facturas no se borran: dejarian un hueco en la numeracion
			// (o, si era la ultima, el siguiente comprobante repetiria su numero).
			// Se anulan y quedan registradas. Solo la nota de venta interna se elimina.
			if ($venta["tipo_comprobante"] !== "Ticket") {
				$mensajeError = "Las boletas y facturas no se eliminan porque dejarían un hueco en la numeración. Anúlala: el stock vuelve igual y el comprobante queda registrado como anulado.";
				return false;
			}
			// Con devoluciones no se borra; su propia nota de anulacion (01) se borra con ella
			$notas = dbAll("SELECT idnota, tipo_nota FROM nota_credito WHERE idventa=? FOR UPDATE", array($idventa));
			$refs = array("V-" . $idventa, "AV-" . $idventa);
			foreach ($notas as $nt) {
				if ($nt["tipo_nota"] !== "01") {
					$mensajeError = "La venta tiene devoluciones (notas de crédito): no se elimina, queda registrada.";
					return false;
				}
				$refs[] = "NC-" . (int)$nt["idnota"];
			}

			// Cobros aplicados: el dinero ya entro, no se puede borrar el origen
			$cuentas = dbAll("SELECT idcuenta_cobrar FROM cuenta_cobrar WHERE idventa=? FOR UPDATE", array($idventa));
			foreach ($cuentas as $c) {
				$pagos = (int)dbValue(
					"SELECT COUNT(*) FROM pago_cuenta_cobrar WHERE idcuenta_cobrar=?",
					array((int)$c["idcuenta_cobrar"]),
					0
				);
				if ($pagos > 0) {
					$mensajeError = "La venta tiene cobros registrados; anula primero los pagos";
					return false;
				}
			}

			// Un arqueo cerrado es historico: no se le quitan movimientos
			$enCajaCerrada = (int)dbValue(
				"SELECT COUNT(*) FROM caja_movimiento m
				 INNER JOIN caja_diaria c ON c.idcaja=m.idcaja
				 WHERE m.referencia IN (" . implode(",", array_fill(0, count($refs), "?")) . ") AND c.estado='CERRADA'",
				$refs,
				0
			);
			if ($enCajaCerrada > 0) {
				$mensajeError = "La venta pertenece a una caja ya cerrada; solo puede anularse, no eliminarse";
				return false;
			}

			// Stock: solo si la venta seguia vigente (una anulada ya lo devolvio)
			if ($venta["estado"] !== "Anulado") {
				$detalle = dbAll("SELECT idarticulo,idvariante,(cantidad*factor) AS cantidad FROM detalle_venta WHERE idventa=?", array($idventa));
				$almVenta = (int)dbValue("SELECT IFNULL(idalmacen,0) FROM venta WHERE idventa=?", array($idventa), 0);
				foreach ($detalle as $d) {
					if (!Stock::mover((int)$d["idarticulo"], $d["idvariante"], (float)$d["cantidad"], $almVenta)) {
						$mensajeError = "No se pudo devolver el stock de los articulos";
						return false;
					}
				}
				if (!Lote::revertirVenta($idventa)) {
					$mensajeError = "No se pudo devolver la mercaderia a sus lotes";
					return false;
				}
				// Lo pagado con saldo a favor vuelve a su nota de credito
				foreach (dbAll("SELECT idnota, monto FROM venta_pago WHERE idventa=? AND medio_pago='NOTA_CREDITO' AND idnota IS NOT NULL", array($idventa)) as $pn) {
					dbExec("UPDATE nota_credito SET saldo_favor=saldo_favor+? WHERE idnota=?", array((float)$pn["monto"], (int)$pn["idnota"]));
				}
			}

			if (!dbExec("DELETE FROM venta_pago WHERE idventa=?", array($idventa))) {
				$mensajeError = "No se pudieron eliminar los pagos";
				return false;
			}
			if (!dbExec("DELETE FROM caja_movimiento WHERE referencia IN (" . implode(",", array_fill(0, count($refs), "?")) . ")", $refs)) {
				$mensajeError = "No se pudieron eliminar los movimientos de caja";
				return false;
			}
			foreach ($cuentas as $c) {
				if (!dbExec("DELETE FROM cuenta_cobrar WHERE idcuenta_cobrar=?", array((int)$c["idcuenta_cobrar"]))) {
					$mensajeError = "No se pudo eliminar la cuenta por cobrar";
					return false;
				}
			}
			// Nota de anulacion propia y los movimientos de lote de la venta (el stock ya esta repuesto)
			foreach ($notas as $nt) {
				dbExec("DELETE FROM detalle_nota_credito WHERE idnota=?", array((int)$nt["idnota"]));
				dbExec("DELETE FROM nota_credito WHERE idnota=?", array((int)$nt["idnota"]));
			}
			dbExec("DELETE FROM lote_movimiento WHERE idventa=?", array($idventa));
			if (!dbExec("DELETE FROM detalle_venta WHERE idventa=?", array($idventa))) {
				$mensajeError = "No se pudo eliminar el detalle de la venta";
				return false;
			}

			// La cotizacion de origen vuelve a quedar disponible
			$hayCotizacion = (int)dbValue(
				"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cotizacion'",
				array(),
				0
			);
			if ($hayCotizacion > 0) {
				dbExec("UPDATE cotizacion SET estado='ACEPTADA', idventa=NULL WHERE idventa=?", array($idventa));
			}

			if (!dbExec("DELETE FROM venta WHERE idventa=?", array($idventa))) {
				$mensajeError = "No se pudo eliminar la venta";
				return false;
			}
			return true;
		});

		if ($resultado === false) {
			return $this->error($mensajeError !== '' ? $mensajeError : "No se pudo eliminar la venta");
		}
		return array("ok"=>true, "message"=>"Venta eliminada correctamente", "documento"=>$documento);
	}

	// ---------- Consultas ----------

	public function mostrar($idventa){
		return dbRow(
			"SELECT v.idventa,DATE_FORMAT(v.fecha_hora,'%Y-%m-%d %H:%i:%s') AS fecha,v.idcliente,p.nombre AS cliente,
				u.idusuario,u.nombre AS usuario,v.tipo_comprobante,v.serie_comprobante,v.num_comprobante,v.total_venta,v.impuesto,v.estado,
				v.tipo_pago,v.medio_pago,v.num_operacion,v.monto_recibido,v.fecha_vencimiento,v.observacion,v.idcaja
			 FROM venta v
			 INNER JOIN persona p ON v.idcliente=p.idpersona
			 INNER JOIN usuario u ON v.idusuario=u.idusuario
			 WHERE v.idventa=?",
			array((int)$idventa)
		);
	}

	/** @return mysqli_result|false */
	public function listarDetalle($idventa){
		return dbQuery(
			"SELECT dv.idventa,dv.idarticulo,CONCAT(a.nombre, IFNULL(CONCAT(' (', NULLIF(CONCAT_WS(' / ', NULLIF(av.talla,''), NULLIF(av.color,'')),''), ')'),'')) AS nombre,IFNULL(ap.nombre, IFNULL(u.abreviatura,'und')) AS unidad,dv.cantidad,dv.factor,dv.precio_venta,dv.descuento,
				(dv.cantidad*dv.precio_venta-dv.descuento) AS subtotal
			 FROM detalle_venta dv
			 INNER JOIN articulo a ON dv.idarticulo=a.idarticulo
			 LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			 LEFT JOIN articulo_presentacion ap ON ap.idpresentacion=dv.idpresentacion
			 LEFT JOIN articulo_variante av ON av.idvariante=dv.idvariante
			 WHERE dv.idventa=?",
			array((int)$idventa)
		);
	}

	/** @return mysqli_result|false */
	public function listar(){
		return $this->listarPorFecha('', '');
	}

	/**
	 * Lista ventas con filtros opcionales. $estado y $tipo_pago se validan por whitelist.
	 * @return mysqli_result|false
	 */
	public function listarPorFecha($fechaInicio, $fechaFin, $estado = '', $tipo_pago = '', $idusuario = 0){
		$where = array();
		$params = array();
		// Un vendedor sin "Consulta ventas" solo ve sus propias ventas
		if ((int)$idusuario > 0) {
			$where[] = "v.idusuario=?";
			$params[] = (int)$idusuario;
		}
		$fechaInicio = fechaSegura($fechaInicio, '');
		$fechaFin = fechaSegura($fechaFin, '');
		if ($fechaInicio !== '') {
			$where[] = "DATE(v.fecha_hora)>=?";
			$params[] = $fechaInicio;
		}
		if ($fechaFin !== '') {
			$where[] = "DATE(v.fecha_hora)<=?";
			$params[] = $fechaFin;
		}
		$estado = trim((string)$estado);
		if ($estado !== '' && in_array($estado, $this->estados, true)) {
			$where[] = "v.estado=?";
			$params[] = $estado;
		}
		$tipo_pago = strtoupper(trim((string)$tipo_pago));
		if ($tipo_pago !== '' && in_array($tipo_pago, $this->tiposPago, true)) {
			$where[] = "v.tipo_pago=?";
			$params[] = $tipo_pago;
		}
		$filtro = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";

		return dbQuery(
			"SELECT v.idventa,DATE_FORMAT(v.fecha_hora,'%d/%m/%Y %H:%i') AS fecha,DATE_FORMAT(v.fecha_hora,'%Y-%m-%d %H:%i:%s') AS fecha_orden,v.idcliente,p.nombre AS cliente,
				u.idusuario,u.nombre AS usuario,v.tipo_comprobante,v.serie_comprobante,v.num_comprobante,v.total_venta,v.impuesto,v.estado,
				v.tipo_pago,v.medio_pago,v.fecha_vencimiento,
				(SELECT IFNULL(SUM(n.total),0) FROM nota_credito n WHERE n.idventa=v.idventa AND n.estado='EMITIDA' AND n.tipo_nota<>'01') AS devuelto
			 FROM venta v
			 INNER JOIN persona p ON v.idcliente=p.idpersona
			 INNER JOIN usuario u ON v.idusuario=u.idusuario" . $filtro . "
			 ORDER BY v.fecha_hora DESC, v.idventa DESC",
			$params
		);
	}

	/** @return mysqli_result|false */
	public function ventacabecera($idventa){
		return dbQuery(
			"SELECT v.idventa, v.idcliente, p.nombre AS cliente, p.direccion, p.tipo_documento, p.num_documento, p.email, p.telefono,
				v.idusuario, u.nombre AS usuario, v.tipo_comprobante, v.serie_comprobante, v.num_comprobante,
				DATE_FORMAT(v.fecha_hora,'%d/%m/%Y %H:%i') AS fecha, v.impuesto, v.total_venta,
				v.tipo_pago, v.medio_pago, v.num_operacion, v.monto_recibido, v.fecha_vencimiento, v.observacion, v.estado
			 FROM venta v
			 INNER JOIN persona p ON v.idcliente=p.idpersona
			 INNER JOIN usuario u ON v.idusuario=u.idusuario
			 WHERE v.idventa=?",
			array((int)$idventa)
		);
	}

	/** @return mysqli_result|false */
	public function ventadetalles($idventa){
		return dbQuery(
			"SELECT CONCAT(a.nombre, IFNULL(CONCAT(' (', NULLIF(CONCAT_WS(' / ', NULLIF(av.talla,''), NULLIF(av.color,'')),''), ')'),'')) AS articulo, COALESCE(av.codigo, ap.codigo, a.codigo) AS codigo, IFNULL(ap.nombre, IFNULL(u.abreviatura,'und')) AS unidad, d.cantidad, d.factor, d.precio_venta, d.descuento,
				(d.cantidad*d.precio_venta-d.descuento) AS subtotal
			 FROM detalle_venta d
			 INNER JOIN articulo a ON d.idarticulo=a.idarticulo
			 LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			 LEFT JOIN articulo_presentacion ap ON ap.idpresentacion=d.idpresentacion
			 LEFT JOIN articulo_variante av ON av.idvariante=d.idvariante
			 WHERE d.idventa=?",
			array((int)$idventa)
		);
	}

	/** Clientes activos para el selector de ventas. */
	public function clientesActivos(){
		return dbAll(
			"SELECT idpersona,nombre,num_documento FROM persona
			 WHERE tipo_persona='Cliente' AND condicion=1
			 ORDER BY nombre ASC"
		);
	}

	/** Resumen de ventas aceptadas agrupado por medio de pago en un rango de fechas. */
	public function resumenPorMedioPago($fechaInicio, $fechaFin){
		$where = array("v.estado='Aceptado'");
		$params = array();
		$fechaInicio = fechaSegura($fechaInicio, '');
		$fechaFin = fechaSegura($fechaFin, '');
		if ($fechaInicio !== '') {
			$where[] = "DATE(v.fecha_hora)>=?";
			$params[] = $fechaInicio;
		}
		if ($fechaFin !== '') {
			$where[] = "DATE(v.fecha_hora)<=?";
			$params[] = $fechaFin;
		}
		return dbAll(
			"SELECT v.medio_pago, v.tipo_pago, COUNT(*) AS comprobantes, IFNULL(SUM(v.total_venta),0) AS total
			 FROM venta v
			 WHERE " . implode(" AND ", $where) . "
			 GROUP BY v.medio_pago, v.tipo_pago
			 ORDER BY total DESC",
			$params
		);
	}

	/** true si la venta la registro ese usuario (control de "solo mis ventas"). */
	public function esDelUsuario($idventa, $idusuario){
		return (int)dbValue("SELECT idusuario FROM venta WHERE idventa=?", array((int)$idventa), 0) === (int)$idusuario && (int)$idusuario > 0;
	}

	/**
	 * Clave del enlace publico de la venta (QR del ticket). Se crea la primera
	 * vez que se pide: 16 letras y numeros aleatorios, imposibles de adivinar.
	 * '' si la venta no existe.
	 */
	public function codigoPublico($idventa){
		$idventa = (int)$idventa;
		$actual = dbValue("SELECT IFNULL(codigo_publico,'') FROM venta WHERE idventa=?", array($idventa), null);
		if ($actual === null) {
			return '';
		}
		if ($actual !== '') {
			return (string)$actual;
		}
		$abc = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
		for ($intento = 0; $intento < 5; $intento++) {
			$codigo = '';
			for ($i = 0; $i < 16; $i++) {
				$codigo .= $abc[random_int(0, strlen($abc) - 1)];
			}
			try {
				dbExec("UPDATE venta SET codigo_publico=? WHERE idventa=? AND codigo_publico IS NULL", array($codigo, $idventa));
			} catch (Throwable $e) {
				continue;   // choque con otra clave (improbable): se genera otra
			}
			// Si otra peticion la creo a la vez, gana la que quedo guardada
			$guardado = (string)dbValue("SELECT IFNULL(codigo_publico,'') FROM venta WHERE idventa=?", array($idventa), '');
			if ($guardado !== '') {
				return $guardado;
			}
		}
		return '';
	}

	/** idventa de una clave publica, o 0. */
	public function idPorCodigoPublico($codigo){
		if (!is_string($codigo) || !preg_match('/^[A-Za-z0-9]{16}$/', $codigo)) {
			return 0;
		}
		return (int)dbValue("SELECT idventa FROM venta WHERE codigo_publico=?", array($codigo), 0);
	}

	/** Totales de las ventas aceptadas de hoy (de un vendedor si $idusuario > 0). */
	public function totalesDia($idusuario = 0){
		$row = dbRow(
			"SELECT COUNT(*) AS comprobantes,
				IFNULL(SUM(total_venta),0) AS monto,
				IFNULL(SUM(CASE WHEN tipo_pago='CONTADO' THEN total_venta ELSE 0 END),0) AS contado,
				IFNULL(SUM(CASE WHEN tipo_pago='CREDITO' THEN total_venta ELSE 0 END),0) AS credito
			 FROM venta
			 WHERE estado='Aceptado' AND DATE(fecha_hora)=CURDATE() AND (?=0 OR idusuario=?)",
			array((int)$idusuario, (int)$idusuario)
		);
		return array(
			"fecha"=>date("Y-m-d"),
			"comprobantes"=>$row ? (int)$row["comprobantes"] : 0,
			"monto"=>$row ? round((float)$row["monto"], 2) : 0.0,
			"contado"=>$row ? round((float)$row["contado"], 2) : 0.0,
			"credito"=>$row ? round((float)$row["credito"], 2) : 0.0
		);
	}

}
