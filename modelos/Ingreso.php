<?php
/**
 * Modelo Ingreso (compras): registro, anulacion y consultas.
 *
 * Reglas:
 *  - Todas las consultas con datos externos usan sentencias preparadas (dbQuery/dbRow/dbAll/...).
 *  - El total de la compra se recalcula SIEMPRE en servidor (sum cantidad*precio_compra).
 *  - El trigger tr_updStockIngreso suma el stock al insertar detalle_ingreso.
 *  - Cada detalle actualiza los precios de referencia del articulo.
 *  - CONTADO: si el usuario tiene caja abierta se registra el egreso en caja_movimiento.
 *  - CREDITO: se genera automaticamente una cuenta_pagar.
 */
require_once "../config/Conexion.php";

class Ingreso{

	private $tiposComprobante = array("Boleta", "Factura", "Ticket");
	private $tiposPago = array("CONTADO", "CREDITO");
	private $mediosPago = array("EFECTIVO", "TARJETA", "TRANSFERENCIA", "YAPE", "PLIN", "OTRO");
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

	private function normalizarCantidadEntera($valor){
		$cantidad = (int)round((float)$valor);
		if ($cantidad < 0) {
			$cantidad = 0;
		}
		return $cantidad;
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
			 FROM ingreso
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
	 * Registra un ingreso (compra) con su detalle. El total se calcula en servidor.
	 * Devuelve array {ok, message} o
	 * {ok:true, idingreso, tipo_comprobante, serie_comprobante, num_comprobante, total, caja_registrada, cuenta_pagar}.
	 */
	public function insertar($idproveedor,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$tipo_pago,$medio_pago,$fecha_vencimiento,$observacion,$idarticulo,$cantidad,$precio_compra,$precio_venta){
		$idproveedor = (int)$idproveedor;
		$idusuario = (int)$idusuario;

		if ($idusuario <= 0) {
			return $this->error("Sesion de usuario no valida");
		}
		if ($idproveedor <= 0) {
			return $this->error("Debes seleccionar un proveedor valido");
		}
		$proveedor = dbRow(
			"SELECT idpersona FROM persona WHERE idpersona=? AND tipo_persona='Proveedor' AND condicion=1 LIMIT 1",
			array($idproveedor)
		);
		if (!$proveedor) {
			return $this->error("El proveedor seleccionado no existe o esta inactivo");
		}

		if (!is_array($idarticulo) || count($idarticulo) === 0) {
			return $this->error("Debes agregar al menos un articulo al ingreso");
		}
		if (!is_array($cantidad) || count($cantidad) !== count($idarticulo)) {
			return $this->error("El detalle de cantidades no es valido");
		}
		if (!is_array($precio_compra)) {
			$precio_compra = array();
		}
		if (!is_array($precio_venta)) {
			$precio_venta = array();
		}

		// Cabecera
		$fecha_hora = $this->normalizarFechaHora($fecha_hora);
		$fecha_compra = substr($fecha_hora, 0, 10);
		$impuesto = decimalSeguro($impuesto, 2, 0);
		if ($impuesto < 0) {
			$impuesto = 0.0;
		}
		$tipo_pago = $this->normalizarTipoPago($tipo_pago);
		$medio_pago = $this->normalizarMedioPago($medio_pago);
		$fecha_vencimiento = fechaSegura($fecha_vencimiento, '');
		if ($tipo_pago === "CREDITO") {
			if ($fecha_vencimiento === '') {
				$fecha_vencimiento = date("Y-m-d", strtotime($fecha_compra . " +30 days"));
			}
			if ($fecha_vencimiento < $fecha_compra) {
				return $this->error("La fecha de vencimiento no puede ser anterior a la fecha de la compra");
			}
		} else {
			$fecha_vencimiento = null;
		}
		$observacion = $this->textoOpcional($observacion, 200);

		// Detalle: validaciones y total en servidor
		$detalles = array();
		$articulosAfectados = array();
		$total = 0.0;
		$n = count($idarticulo);
		for ($i = 0; $i < $n; $i++) {
			$idArticuloActual = (int)$idarticulo[$i];
			$cantidadActual = $this->normalizarCantidadEntera($cantidad[$i]);
			$precioCompraActual = isset($precio_compra[$i]) ? decimalSeguro($precio_compra[$i], 2, -1) : -1;
			$precioVentaActual = isset($precio_venta[$i]) ? decimalSeguro($precio_venta[$i], 2, 0) : 0.0;

			if ($idArticuloActual <= 0) {
				return $this->error("Se detecto un articulo invalido en el detalle");
			}
			if ($cantidadActual <= 0) {
				return $this->error("La cantidad debe ser mayor que cero");
			}
			if ($precioCompraActual < 0 || $precioVentaActual < 0) {
				return $this->error("Los precios no pueden ser negativos");
			}

			$articulosAfectados[$idArticuloActual] = true;
			$detalles[] = array(
				"idarticulo"=>$idArticuloActual,
				"cantidad"=>$cantidadActual,
				"precio_compra"=>(float)$precioCompraActual,
				"precio_venta"=>(float)$precioVentaActual
			);
			$total += round($cantidadActual * $precioCompraActual, 2);
		}
		$total = round($total, 2);

		$tipo_comprobante = $this->normalizarTipoComprobante($tipo_comprobante);
		$serie_comprobante = $this->normalizarSerieComprobante($serie_comprobante, $tipo_comprobante);
		$num_comprobante = substr(preg_replace('/[^0-9]/', '', (string)$num_comprobante), 0, 10);

		$ctx = array(
			"idproveedor"=>$idproveedor,
			"idusuario"=>$idusuario,
			"tipo_comprobante"=>$tipo_comprobante,
			"serie_comprobante"=>$serie_comprobante,
			"num_comprobante"=>$num_comprobante,
			"fecha_hora"=>$fecha_hora,
			"fecha_compra"=>$fecha_compra,
			"fecha_vencimiento"=>$fecha_vencimiento,
			"impuesto"=>(float)$impuesto,
			"tipo_pago"=>$tipo_pago,
			"medio_pago"=>$medio_pago,
			"observacion"=>$observacion,
			"total"=>(float)$total
		);
		$mensajeError = '';

		$resultado = dbTransaccion(function($cx) use ($ctx, $detalles, $articulosAfectados, &$mensajeError) {
			$tipo = $ctx["tipo_comprobante"];
			$serie = $ctx["serie_comprobante"];
			$num = $ctx["num_comprobante"];

			// Correlativo automatico (con bloqueo) o verificacion de unicidad
			if ($num === '') {
				$correlativo = $this->obtenerCorrelativoInterno($tipo, $serie, true);
				$num = $correlativo["numero"];
			} else {
				$existe = (int)dbValue(
					"SELECT idingreso FROM ingreso WHERE tipo_comprobante=? AND serie_comprobante=? AND num_comprobante=? LIMIT 1 FOR UPDATE",
					array($tipo, $serie, $num),
					0
				);
				if ($existe > 0) {
					$mensajeError = "Ya existe un ingreso con el mismo tipo, serie y numero de comprobante";
					return false;
				}
			}

			// Verificar que los articulos existan y esten activos (bloqueo de filas)
			$ids = array_keys($articulosAfectados);
			$placeholders = implode(",", array_fill(0, count($ids), "?"));
			$filas = dbAll(
				"SELECT idarticulo,nombre,condicion FROM articulo WHERE idarticulo IN (" . $placeholders . ") FOR UPDATE",
				$ids
			);
			$existentes = array();
			foreach ($filas as $f) {
				$existentes[(int)$f["idarticulo"]] = $f;
			}
			$errores = array();
			foreach ($ids as $idArt) {
				if (!isset($existentes[$idArt])) {
					$errores[] = "Articulo ID " . $idArt . " no encontrado";
				} elseif ((int)$existentes[$idArt]["condicion"] !== 1) {
					$errores[] = $existentes[$idArt]["nombre"] . " esta inactivo";
				}
			}
			if (count($errores) > 0) {
				$mensajeError = "Articulos no validos: " . implode("; ", $errores);
				return false;
			}

			$idingreso = dbInsert(
				"INSERT INTO ingreso (idproveedor,idusuario,tipo_comprobante,serie_comprobante,num_comprobante,fecha_hora,fecha_vencimiento,impuesto,tipo_pago,medio_pago,total_compra,estado,observacion)
				 VALUES (?,?,?,?,?,?,?,?,?,?,?,'Aceptado',?)",
				array(
					$ctx["idproveedor"], $ctx["idusuario"], $tipo, $serie, $num, $ctx["fecha_hora"],
					$ctx["fecha_vencimiento"], $ctx["impuesto"], $ctx["tipo_pago"], $ctx["medio_pago"],
					$ctx["total"], $ctx["observacion"]
				)
			);
			if ($idingreso <= 0) {
				$mensajeError = "No se pudo registrar la cabecera del ingreso";
				return false;
			}

			// Detalle (el trigger suma el stock) + precios de referencia del articulo
			foreach ($detalles as $d) {
				$ok = dbExec(
					"INSERT INTO detalle_ingreso (idingreso,idarticulo,cantidad,precio_compra,precio_venta) VALUES (?,?,?,?,?)",
					array($idingreso, $d["idarticulo"], $d["cantidad"], $d["precio_compra"], $d["precio_venta"])
				);
				if (!$ok) {
					$mensajeError = "No se pudo registrar el detalle del ingreso";
					return false;
				}
				$ok = dbExec(
					"UPDATE articulo SET precio_compra=?, precio_venta=IF(?>0, ?, precio_venta) WHERE idarticulo=?",
					array($d["precio_compra"], $d["precio_venta"], $d["precio_venta"], $d["idarticulo"])
				);
				if (!$ok) {
					$mensajeError = "No se pudo actualizar los precios de referencia del articulo";
					return false;
				}
			}

			$documento = $tipo . " " . $serie . "-" . $num;
			$cuentaPagar = false;
			$cajaRegistrada = false;

			if ($ctx["tipo_pago"] === "CREDITO") {
				$idcp = dbInsert(
					"INSERT INTO cuenta_pagar (idproveedor,idingreso,fecha_emision,fecha_vencimiento,documento_ref,monto_total,saldo,estado,observacion)
					 VALUES (?,?,?,?,?,?,?,'PENDIENTE','Generada automaticamente desde compra')",
					array($ctx["idproveedor"], $idingreso, $ctx["fecha_compra"], $ctx["fecha_vencimiento"], $documento, $ctx["total"], $ctx["total"])
				);
				if ($idcp <= 0) {
					$mensajeError = "No se pudo generar la cuenta por pagar";
					return false;
				}
				$cuentaPagar = true;
			} else {
				$idcaja = $this->cajaAbiertaUsuario($ctx["idusuario"]);
				if ($idcaja > 0) {
					$idmov = dbInsert(
						"INSERT INTO caja_movimiento (idcaja,idusuario,tipo,concepto,referencia,medio_pago,monto,fecha_hora)
						 VALUES (?,?,'EGRESO',?,?,?,?,NOW())",
						array($idcaja, $ctx["idusuario"], "Compra " . $documento, "C-" . $idingreso, $ctx["medio_pago"], $ctx["total"])
					);
					if ($idmov <= 0) {
						$mensajeError = "No se pudo registrar el movimiento de caja";
						return false;
					}
					$cajaRegistrada = true;
				}
			}

			return array(
				"idingreso"=>$idingreso,
				"tipo_comprobante"=>$tipo,
				"serie_comprobante"=>$serie,
				"num_comprobante"=>$num,
				"caja_registrada"=>$cajaRegistrada,
				"cuenta_pagar"=>$cuentaPagar
			);
		});

		if ($resultado === false || !is_array($resultado)) {
			return $this->error($mensajeError !== '' ? $mensajeError : "No se pudo registrar el ingreso");
		}

		return array(
			"ok"=>true,
			"idingreso"=>(int)$resultado["idingreso"],
			"tipo_comprobante"=>$resultado["tipo_comprobante"],
			"serie_comprobante"=>$resultado["serie_comprobante"],
			"num_comprobante"=>$resultado["num_comprobante"],
			"total"=>$total,
			"caja_registrada"=>(bool)$resultado["caja_registrada"],
			"cuenta_pagar"=>(bool)$resultado["cuenta_pagar"]
		);
	}

	// ---------- Anulacion ----------

	/**
	 * Anula un ingreso: resta el stock ingresado (si aun esta disponible), anula la
	 * cuenta por pagar (si no tiene pagos) y registra el reingreso en caja si sigue abierta.
	 * Devuelve array {ok, message}.
	 */
	public function anular($idingreso, $idusuario){
		$idingreso = (int)$idingreso;
		$idusuario = (int)$idusuario;
		if ($idingreso <= 0) {
			return $this->error("Ingreso no valido");
		}
		if ($idusuario <= 0) {
			return $this->error("Sesion de usuario no valida");
		}
		$mensajeError = '';

		$resultado = dbTransaccion(function($cx) use ($idingreso, $idusuario, &$mensajeError) {
			$ingreso = dbRow(
				"SELECT idingreso,estado,tipo_comprobante,serie_comprobante,num_comprobante,medio_pago,total_compra
				 FROM ingreso WHERE idingreso=? FOR UPDATE",
				array($idingreso)
			);
			if (!$ingreso) {
				$mensajeError = "El ingreso no existe";
				return false;
			}
			if ($ingreso["estado"] === "Anulado") {
				$mensajeError = "El ingreso ya se encuentra anulado";
				return false;
			}
			$documento = $ingreso["tipo_comprobante"] . " " . $ingreso["serie_comprobante"] . "-" . $ingreso["num_comprobante"];

			// Cuentas por pagar vinculadas: bloquear si ya tienen pagos
			$cuentas = dbAll("SELECT idcuenta_pagar FROM cuenta_pagar WHERE idingreso=? FOR UPDATE", array($idingreso));
			foreach ($cuentas as $c) {
				$pagos = (int)dbValue(
					"SELECT COUNT(*) FROM pago_cuenta_pagar WHERE idcuenta_pagar=?",
					array((int)$c["idcuenta_pagar"]),
					0
				);
				if ($pagos > 0) {
					$mensajeError = "El ingreso tiene pagos registrados; anula primero los pagos";
					return false;
				}
			}

			// Verificar que el stock ingresado siga disponible (por articulo, con bloqueo)
			$detalle = dbAll(
				"SELECT d.idarticulo, SUM(d.cantidad) AS cantidad
				 FROM detalle_ingreso d
				 WHERE d.idingreso=?
				 GROUP BY d.idarticulo",
				array($idingreso)
			);
			foreach ($detalle as $d) {
				$art = dbRow("SELECT nombre,stock FROM articulo WHERE idarticulo=? FOR UPDATE", array((int)$d["idarticulo"]));
				if (!$art) {
					$mensajeError = "No se encontro el articulo ID " . (int)$d["idarticulo"];
					return false;
				}
				if (((float)$art["stock"] - (float)$d["cantidad"]) < 0) {
					$mensajeError = "No se puede anular: el stock de " . $art["nombre"] . " ya fue utilizado";
					return false;
				}
			}

			foreach ($cuentas as $c) {
				if (!dbExec("UPDATE cuenta_pagar SET estado='ANULADO', saldo=0 WHERE idcuenta_pagar=?", array((int)$c["idcuenta_pagar"]))) {
					$mensajeError = "No se pudo anular la cuenta por pagar";
					return false;
				}
			}

			// Restar stock
			foreach ($detalle as $d) {
				if (!dbExec("UPDATE articulo SET stock=stock-? WHERE idarticulo=?", array((float)$d["cantidad"], (int)$d["idarticulo"]))) {
					$mensajeError = "No se pudo revertir el stock de los articulos";
					return false;
				}
			}

			// Reingreso en caja si la caja de origen sigue abierta
			$mov = dbRow(
				"SELECT m.idcaja,m.medio_pago,m.monto,c.estado
				 FROM caja_movimiento m
				 INNER JOIN caja_diaria c ON c.idcaja=m.idcaja
				 WHERE m.referencia=? AND m.tipo='EGRESO'
				 ORDER BY m.idmovimiento DESC LIMIT 1",
				array("C-" . $idingreso)
			);
			if ($mov && $mov["estado"] === "ABIERTA") {
				$idmov = dbInsert(
					"INSERT INTO caja_movimiento (idcaja,idusuario,tipo,concepto,referencia,medio_pago,monto,fecha_hora)
					 VALUES (?,?,'INGRESO',?,?,?,?,NOW())",
					array((int)$mov["idcaja"], $idusuario, "Anulacion compra " . $documento, "AC-" . $idingreso, $mov["medio_pago"], (float)$mov["monto"])
				);
				if ($idmov <= 0) {
					$mensajeError = "No se pudo registrar el movimiento en caja";
					return false;
				}
			}

			if (!dbExec("UPDATE ingreso SET estado='Anulado' WHERE idingreso=?", array($idingreso))) {
				$mensajeError = "No se pudo actualizar el estado del ingreso";
				return false;
			}
			return true;
		});

		if ($resultado === false) {
			return $this->error($mensajeError !== '' ? $mensajeError : "No se pudo anular el ingreso");
		}
		return array("ok"=>true, "message"=>"Ingreso anulado correctamente");
	}

	// ---------- Consultas ----------

	public function mostrar($idingreso){
		return dbRow(
			"SELECT i.idingreso,DATE_FORMAT(i.fecha_hora,'%Y-%m-%d %H:%i:%s') AS fecha,i.idproveedor,p.nombre AS proveedor,
				u.idusuario,u.nombre AS usuario,i.tipo_comprobante,i.serie_comprobante,i.num_comprobante,i.total_compra,i.impuesto,i.estado,
				i.tipo_pago,i.medio_pago,i.fecha_vencimiento,i.observacion
			 FROM ingreso i
			 INNER JOIN persona p ON i.idproveedor=p.idpersona
			 INNER JOIN usuario u ON i.idusuario=u.idusuario
			 WHERE i.idingreso=?",
			array((int)$idingreso)
		);
	}

	/** @return mysqli_result|false */
	public function listarDetalle($idingreso){
		return dbQuery(
			"SELECT di.idingreso,di.idarticulo,a.nombre,IFNULL(u.abreviatura,'und') AS unidad,di.cantidad,di.precio_compra,di.precio_venta
			 FROM detalle_ingreso di
			 INNER JOIN articulo a ON di.idarticulo=a.idarticulo
			 LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			 WHERE di.idingreso=?",
			array((int)$idingreso)
		);
	}

	/** @return mysqli_result|false */
	public function listar(){
		return $this->listarPorFecha('', '');
	}

	/**
	 * Lista ingresos con filtros opcionales. $estado y $tipo_pago se validan por whitelist.
	 * @return mysqli_result|false
	 */
	public function listarPorFecha($fechaInicio, $fechaFin, $estado = '', $tipo_pago = ''){
		$where = array();
		$params = array();
		$fechaInicio = fechaSegura($fechaInicio, '');
		$fechaFin = fechaSegura($fechaFin, '');
		if ($fechaInicio !== '') {
			$where[] = "DATE(i.fecha_hora)>=?";
			$params[] = $fechaInicio;
		}
		if ($fechaFin !== '') {
			$where[] = "DATE(i.fecha_hora)<=?";
			$params[] = $fechaFin;
		}
		$estado = trim((string)$estado);
		if ($estado !== '' && in_array($estado, $this->estados, true)) {
			$where[] = "i.estado=?";
			$params[] = $estado;
		}
		$tipo_pago = strtoupper(trim((string)$tipo_pago));
		if ($tipo_pago !== '' && in_array($tipo_pago, $this->tiposPago, true)) {
			$where[] = "i.tipo_pago=?";
			$params[] = $tipo_pago;
		}
		$filtro = count($where) > 0 ? " WHERE " . implode(" AND ", $where) : "";

		return dbQuery(
			"SELECT i.idingreso,DATE_FORMAT(i.fecha_hora,'%d/%m/%Y %H:%i') AS fecha,i.idproveedor,p.nombre AS proveedor,
				u.idusuario,u.nombre AS usuario,i.tipo_comprobante,i.serie_comprobante,i.num_comprobante,i.total_compra,i.impuesto,i.estado,
				i.tipo_pago,i.medio_pago,i.fecha_vencimiento
			 FROM ingreso i
			 INNER JOIN persona p ON i.idproveedor=p.idpersona
			 INNER JOIN usuario u ON i.idusuario=u.idusuario" . $filtro . "
			 ORDER BY i.idingreso DESC",
			$params
		);
	}

	/** @return mysqli_result|false */
	public function ingresocabecera($idingreso){
		return dbQuery(
			"SELECT i.idingreso, i.idproveedor, p.nombre AS proveedor, p.direccion, p.tipo_documento, p.num_documento, p.email, p.telefono,
				i.idusuario, u.nombre AS usuario, i.tipo_comprobante, i.serie_comprobante, i.num_comprobante,
				DATE_FORMAT(i.fecha_hora,'%d/%m/%Y %H:%i') AS fecha, i.impuesto, i.total_compra,
				i.tipo_pago, i.medio_pago, i.fecha_vencimiento, i.observacion, i.estado
			 FROM ingreso i
			 INNER JOIN persona p ON i.idproveedor=p.idpersona
			 INNER JOIN usuario u ON i.idusuario=u.idusuario
			 WHERE i.idingreso=?",
			array((int)$idingreso)
		);
	}

	/** @return mysqli_result|false */
	public function ingresodetalles($idingreso){
		return dbQuery(
			"SELECT a.nombre AS articulo, a.codigo, IFNULL(u.abreviatura,'und') AS unidad, d.cantidad, d.precio_compra, d.precio_venta,
				(d.cantidad*d.precio_compra) AS subtotal
			 FROM detalle_ingreso d
			 INNER JOIN articulo a ON d.idarticulo=a.idarticulo
			 LEFT JOIN unidad_medida u ON a.idunidad=u.idunidad
			 WHERE d.idingreso=?",
			array((int)$idingreso)
		);
	}

	/** Proveedores activos para el selector de compras. */
	public function proveedoresActivos(){
		return dbAll(
			"SELECT idpersona,nombre,num_documento FROM persona
			 WHERE tipo_persona='Proveedor' AND condicion=1
			 ORDER BY nombre ASC"
		);
	}

}
