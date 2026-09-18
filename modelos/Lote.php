<?php
/**
 * Lotes y fechas de vencimiento (capacidades 'lotes' y 'vencimientos').
 *
 * Reglas que mantiene este modelo:
 *  - La suma del stock de los lotes activos nunca supera articulo.stock. Lo que
 *    sobra es stock "sin lote" (historico, o comprado sin fecha).
 *  - Las salidas toman primero el lote que vence antes (FEFO). Los lotes sin
 *    fecha van despues de los fechados, y el stock sin lote al final.
 *  - Lo vencido no se vende: solo puede salir por un ajuste (baja).
 *  - Cada consumo queda en lote_movimiento, asi anular una venta devuelve la
 *    mercaderia a los mismos lotes.
 *
 * Todos los metodos que escriben deben llamarse dentro de dbTransaccion(); los
 * que leen stock para decidir bloquean las filas con FOR UPDATE.
 *
 * Almacenes: cada lote esta en un almacen (lote.idalmacen). La salida FEFO y lo
 * vencido se calculan dentro del almacen, y en cada almacen la suma de sus
 * lotes nunca supera su stock (stock_almacen).
 */
require_once "../config/Conexion.php";
require_once "../config/negocio.php";

class Lote
{
	/** ¿El rubro usa lotes o vencimientos? */
	public static function activo()
	{
		return negocioTiene('lotes') || negocioTiene('vencimientos');
	}

	public static function diasAlerta()
	{
		$dias = (int)dbValue("SELECT dias_alerta_vencimiento FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1", array(), 30);
		return $dias > 0 ? $dias : 30;
	}

	/**
	 * Valida una fecha de vencimiento recibida (Y-m-d). Devuelve la fecha, ''
	 * si viene vacia, o false si no es valida.
	 */
	public static function fechaValida($valor)
	{
		$valor = trim((string)$valor);
		if ($valor === '') {
			return '';
		}
		$d = DateTime::createFromFormat('Y-m-d', $valor);
		return ($d && $d->format('Y-m-d') === $valor) ? $valor : false;
	}

	public static function codigoValido($valor)
	{
		$valor = trim(preg_replace('/\s+/', ' ', (string)$valor));
		return function_exists('mb_substr') ? mb_substr($valor, 0, 40, 'UTF-8') : substr($valor, 0, 40);
	}

	/** Crea un lote y devuelve su id (0 si falla). $cantidad en unidades base. */
	public static function crear($idarticulo, $cantidad, $fechaVencimiento, $codigo, $costoUnitario, $idingreso = null, $idajuste = null, $idalmacen = 0)
	{
		$cantidad = round((float)$cantidad, 3);
		if ($cantidad <= 0) {
			return 0;
		}
		if ((int)$idalmacen <= 0) {
			$idalmacen = (int)dbValue("SELECT idalmacen FROM almacen WHERE principal=1 ORDER BY idalmacen LIMIT 1", array(), 0);
		}
		return (int)dbInsert(
			"INSERT INTO lote (idarticulo,idalmacen,codigo_lote,fecha_vencimiento,cantidad_inicial,stock,costo_unitario,idingreso,idajuste,fecha_ingreso,condicion)
			 VALUES (?,?,?,?,?,?,?,?,?,NOW(),1)",
			array(
				(int)$idarticulo,
				(int)$idalmacen > 0 ? (int)$idalmacen : null,
				$codigo !== '' ? $codigo : null,
				$fechaVencimiento !== '' ? $fechaVencimiento : null,
				$cantidad, $cantidad, round((float)$costoUnitario, 2),
				$idingreso ? (int)$idingreso : null,
				$idajuste ? (int)$idajuste : null
			)
		);
	}

	/** Stock de lotes ya vencidos (fecha anterior a hoy) de un articulo. */
	public static function stockVencido($idarticulo, $idalmacen = 0)
	{
		return round((float)dbValue(
			"SELECT IFNULL(SUM(stock),0) FROM lote WHERE idarticulo=? AND condicion=1 AND stock>0 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE()"
			. ((int)$idalmacen > 0 ? " AND idalmacen=" . (int)$idalmacen : ""),
			array((int)$idarticulo),
			0
		), 3);
	}

	/** Proximo lote vigente por vencer: array {fecha_vencimiento, codigo_lote, stock} o null. */
	public static function proximoVencimiento($idarticulo, $idalmacen = 0)
	{
		return dbRow(
			"SELECT fecha_vencimiento, codigo_lote, stock FROM lote
			 WHERE idarticulo=? AND condicion=1 AND stock>0 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento >= CURDATE()"
			. ((int)$idalmacen > 0 ? " AND idalmacen=" . (int)$idalmacen : "") . "
			 ORDER BY fecha_vencimiento ASC, idlote ASC LIMIT 1",
			array((int)$idarticulo)
		);
	}

	/**
	 * Si los lotes suman mas que el stock del articulo (el stock bajo por una via
	 * que no descuenta lotes: edicion manual, importacion, venta con otro rubro),
	 * recorta los lotes empezando por los que vencen antes.
	 */
	public static function ajustarAlStock($idarticulo)
	{
		$idarticulo = (int)$idarticulo;
		dbValue("SELECT stock FROM articulo WHERE idarticulo=? FOR UPDATE", array($idarticulo), 0);
		// En cada almacen con lotes, los lotes no pasan del stock de ese almacen
		$almacenes = dbAll("SELECT DISTINCT IFNULL(idalmacen,0) AS idalmacen FROM lote WHERE idarticulo=? AND condicion=1 AND stock>0", array($idarticulo));
		foreach ($almacenes as $al) {
			$idal = (int)$al['idalmacen'];
			$stock = $idal > 0
				? (float)dbValue("SELECT IFNULL(SUM(stock),0) FROM stock_almacen WHERE idalmacen=? AND idarticulo=?", array($idal, $idarticulo), 0)
				: (float)dbValue("SELECT stock FROM articulo WHERE idarticulo=?", array($idarticulo), 0);
			$lotes = dbAll(
				"SELECT idlote, stock FROM lote WHERE idarticulo=? AND IFNULL(idalmacen,0)=? AND condicion=1 AND stock>0
				 ORDER BY (fecha_vencimiento IS NULL), fecha_vencimiento ASC, idlote ASC FOR UPDATE",
				array($idarticulo, $idal)
			);
			$suma = 0.0;
			foreach ($lotes as $l) {
				$suma += (float)$l['stock'];
			}
			$exceso = round($suma - max($stock, 0), 3);
			foreach ($lotes as $l) {
				if ($exceso <= 0) {
					break;
				}
				$quitar = min((float)$l['stock'], $exceso);
				if (!dbExec("UPDATE lote SET stock=ROUND(stock-?,3) WHERE idlote=?", array($quitar, (int)$l['idlote']))) {
					return false;
				}
				$exceso = round($exceso - $quitar, 3);
			}
		}
		return true;
	}

	/**
	 * Descuenta $cantidad (unidades base) de los lotes del articulo en orden FEFO.
	 * Venta: salta los vencidos. Ajuste: los vencidos van primero (una merma
	 * suele ser lo que ya no sirve). Lo que no cubren los lotes sale del stock
	 * sin lote. $ref: tipo, idventa, iddetalle_venta, idajuste.
	 * Devuelve la lista de {idlote, cantidad} consumidos, o false si falla.
	 */
	public static function consumir($idarticulo, $cantidad, array $ref, $idalmacen = 0)
	{
		$idarticulo = (int)$idarticulo;
		$pendiente = round((float)$cantidad, 3);
		$esVenta = isset($ref['tipo']) && $ref['tipo'] === 'VENTA';
		$orden = $esVenta
			? "(fecha_vencimiento IS NULL), fecha_vencimiento ASC, idlote ASC"
			: "(fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE()) DESC, (fecha_vencimiento IS NULL), fecha_vencimiento ASC, idlote ASC";
		$filtroVencidos = $esVenta ? " AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= CURDATE())" : "";
		$lotes = dbAll(
			"SELECT idlote, stock FROM lote WHERE idarticulo=? AND condicion=1 AND stock>0" . $filtroVencidos
			. ((int)$idalmacen > 0 ? " AND idalmacen=" . (int)$idalmacen : "") . " ORDER BY " . $orden . " FOR UPDATE",
			array($idarticulo)
		);
		$consumos = array();
		foreach ($lotes as $l) {
			if ($pendiente <= 0) {
				break;
			}
			$tomar = round(min((float)$l['stock'], $pendiente), 3);
			if (!self::registrarConsumo((int)$l['idlote'], $tomar, $ref)) {
				return false;
			}
			$consumos[] = array('idlote' => (int)$l['idlote'], 'cantidad' => $tomar);
			$pendiente = round($pendiente - $tomar, 3);
		}
		return $consumos;
	}

	/** Descuenta de un lote concreto (baja de un lote vencido). */
	public static function consumirLote($idlote, $idarticulo, $cantidad, array $ref, $idalmacen = 0)
	{
		$l = dbRow("SELECT idlote, stock, idalmacen FROM lote WHERE idlote=? AND idarticulo=? AND condicion=1 FOR UPDATE", array((int)$idlote, (int)$idarticulo));
		if (!$l) {
			return 'El lote no existe o no pertenece al artículo';
		}
		if ((int)$idalmacen > 0 && (int)$l['idalmacen'] !== (int)$idalmacen) {
			return 'El lote está en otro almacén';
		}
		if ((float)$l['stock'] + 0.0005 < (float)$cantidad) {
			return 'El lote solo tiene ' . formatearCantidad($l['stock']) . ' en stock';
		}
		return self::registrarConsumo((int)$idlote, round((float)$cantidad, 3), $ref) ? true : 'No se pudo descontar el lote';
	}

	/**
	 * Suma stock a un lote existente (ajuste de entrada o sobrante de un conteo).
	 * Queda en lote_movimiento como ENTRADA. Devuelve true o el mensaje de error.
	 */
	public static function sumarLote($idlote, $idarticulo, $cantidad, $idajuste)
	{
		$cantidad = round((float)$cantidad, 3);
		$l = dbRow("SELECT idlote FROM lote WHERE idlote=? AND idarticulo=? AND condicion=1 FOR UPDATE", array((int)$idlote, (int)$idarticulo));
		if (!$l) {
			return 'El lote no existe o no pertenece al artículo';
		}
		if (!dbExec("UPDATE lote SET stock=ROUND(stock+?,3) WHERE idlote=?", array($cantidad, (int)$idlote))) {
			return 'No se pudo actualizar el lote';
		}
		$ok = dbInsert(
			"INSERT INTO lote_movimiento (idlote,tipo,cantidad,idajuste,fecha_hora) VALUES (?,'ENTRADA',?,?,NOW())",
			array((int)$idlote, $cantidad, (int)$idajuste)
		) > 0;
		return $ok ? true : 'No se pudo registrar el movimiento del lote';
	}

	private static function registrarConsumo($idlote, $cantidad, array $ref)
	{
		if ($cantidad <= 0) {
			return true;
		}
		if (!dbExec("UPDATE lote SET stock=ROUND(stock-?,3) WHERE idlote=?", array($cantidad, $idlote))) {
			return false;
		}
		return dbInsert(
			"INSERT INTO lote_movimiento (idlote,tipo,cantidad,iddetalle_venta,idventa,idajuste,fecha_hora) VALUES (?,?,?,?,?,?,NOW())",
			array(
				$idlote,
				isset($ref['tipo']) ? $ref['tipo'] : 'AJUSTE',
				$cantidad,
				!empty($ref['iddetalle_venta']) ? (int)$ref['iddetalle_venta'] : null,
				!empty($ref['idventa']) ? (int)$ref['idventa'] : null,
				!empty($ref['idajuste']) ? (int)$ref['idajuste'] : null
			)
		) > 0;
	}

	/**
	 * Devuelve a sus lotes lo que salio con una venta y borra esos movimientos.
	 * Se usa al anular y al eliminar (si la venta seguia vigente).
	 */
	public static function revertirVenta($idventa)
	{
		$movs = dbAll("SELECT idmovimiento, idlote, cantidad FROM lote_movimiento WHERE idventa=? AND tipo='VENTA' FOR UPDATE", array((int)$idventa));
		foreach ($movs as $m) {
			if (!dbExec("UPDATE lote SET stock=ROUND(stock+?,3) WHERE idlote=?", array((float)$m['cantidad'], (int)$m['idlote']))) {
				return false;
			}
		}
		return dbExec("DELETE FROM lote_movimiento WHERE idventa=? AND tipo='VENTA'", array((int)$idventa));
	}

	/**
	 * Devolucion de una linea vendida (nota de credito): la mercaderia vuelve a
	 * los mismos lotes de los que salio esa linea, sin pasar de lo que salio de
	 * cada uno (descontando devoluciones anteriores). Lo que no salio de un lote
	 * queda como stock sin lote. Devuelve true o false.
	 */
	public static function devolverLinea($iddetalleVenta, $idventa, $cantidad, $idnota)
	{
		$pendiente = round((float)$cantidad, 3);
		$salidas = dbAll(
			"SELECT m.idlote, SUM(m.cantidad) AS salio,
				IFNULL((SELECT SUM(d.cantidad) FROM lote_movimiento d WHERE d.tipo='DEVOLUCION' AND d.iddetalle_venta=m.iddetalle_venta AND d.idlote=m.idlote),0) AS volvio
			 FROM lote_movimiento m
			 INNER JOIN lote l ON l.idlote=m.idlote
			 WHERE m.tipo='VENTA' AND m.iddetalle_venta=? AND l.condicion=1
			 GROUP BY m.idlote
			 ORDER BY m.idlote DESC",
			array((int)$iddetalleVenta)
		);
		foreach ($salidas as $s) {
			if ($pendiente <= 0) {
				break;
			}
			$puede = round((float)$s['salio'] - (float)$s['volvio'], 3);
			if ($puede <= 0) {
				continue;
			}
			$vuelve = min($puede, $pendiente);
			$l = dbRow("SELECT idlote FROM lote WHERE idlote=? FOR UPDATE", array((int)$s['idlote']));
			if (!$l) {
				continue;
			}
			if (!dbExec("UPDATE lote SET stock=ROUND(stock+?,3) WHERE idlote=?", array($vuelve, (int)$s['idlote']))) {
				return false;
			}
			$ok = dbInsert(
				"INSERT INTO lote_movimiento (idlote,tipo,cantidad,iddetalle_venta,idventa,idnota,fecha_hora) VALUES (?,'DEVOLUCION',?,?,?,?,NOW())",
				array((int)$s['idlote'], $vuelve, (int)$iddetalleVenta, (int)$idventa, (int)$idnota)
			) > 0;
			if (!$ok) {
				return false;
			}
			$pendiente = round($pendiente - $vuelve, 3);
		}
		return true;
	}

	/**
	 * Lotes de una compra que ya perdieron stock (vendido o dado de baja).
	 * Si hay alguno, la compra no puede anularse sin descuadrar los lotes.
	 */
	public static function lotesConsumidosDeIngreso($idingreso)
	{
		return dbAll(
			"SELECT l.idlote, l.codigo_lote, l.fecha_vencimiento, a.nombre
			 FROM lote l INNER JOIN articulo a ON a.idarticulo=l.idarticulo
			 WHERE l.idingreso=? AND l.condicion=1 AND l.stock + 0.0005 < l.cantidad_inicial",
			array((int)$idingreso)
		);
	}

	public static function anularLotesIngreso($idingreso)
	{
		return dbExec("UPDATE lote SET stock=0, condicion=0 WHERE idingreso=?", array((int)$idingreso));
	}

	public static function eliminarLotesIngreso($idingreso)
	{
		return dbExec("DELETE FROM lote WHERE idingreso=?", array((int)$idingreso));
	}

	// ---------- Consultas ----------

	/** Lotes con stock de un articulo, en orden FEFO. */
	public static function deArticulo($idarticulo, $idalmacen = 0)
	{
		return dbAll(
			"SELECT idlote, idalmacen, codigo_lote, fecha_vencimiento, cantidad_inicial, stock, costo_unitario, fecha_ingreso,
				DATEDIFF(fecha_vencimiento, CURDATE()) AS dias
			 FROM lote WHERE idarticulo=? AND condicion=1 AND stock>0" . ((int)$idalmacen > 0 ? " AND idalmacen=" . (int)$idalmacen : "") . "
			 ORDER BY (fecha_vencimiento IS NULL), fecha_vencimiento ASC, idlote ASC",
			array((int)$idarticulo)
		);
	}

	/**
	 * Listado para la pantalla de vencimientos.
	 * $estado: '' todos | VENCIDO | POR_VENCER | VIGENTE | SIN_FECHA
	 */
	public static function listar($estado, $dias)
	{
		$dias = max(1, (int)$dias);
		$where = array("l.condicion=1", "l.stock>0");
		$params = array();
		if ($estado === 'VENCIDO') {
			$where[] = "l.fecha_vencimiento < CURDATE()";
		} elseif ($estado === 'POR_VENCER') {
			$where[] = "l.fecha_vencimiento >= CURDATE() AND l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
			$params[] = $dias;
		} elseif ($estado === 'VIGENTE') {
			$where[] = "l.fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL ? DAY)";
			$params[] = $dias;
		} elseif ($estado === 'SIN_FECHA') {
			$where[] = "l.fecha_vencimiento IS NULL";
		}
		return dbAll(
			"SELECT l.idlote, l.idarticulo, a.nombre AS articulo, a.codigo, IFNULL(u.abreviatura,'und') AS unidad,
				l.codigo_lote, l.fecha_vencimiento, DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias,
				l.cantidad_inicial, l.stock, IF(l.costo_unitario>0, l.costo_unitario, a.precio_compra) AS costo, l.fecha_ingreso,
				i.serie_comprobante, i.num_comprobante, p.nombre AS proveedor, IFNULL(al.nombre,'') AS almacen, l.idalmacen
			 FROM lote l
			 LEFT JOIN almacen al ON al.idalmacen=l.idalmacen
			 INNER JOIN articulo a ON a.idarticulo=l.idarticulo
			 LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			 LEFT JOIN ingreso i ON i.idingreso=l.idingreso
			 LEFT JOIN persona p ON p.idpersona=i.idproveedor
			 WHERE " . implode(" AND ", $where) . "
			 ORDER BY (l.fecha_vencimiento IS NULL), l.fecha_vencimiento ASC, a.nombre ASC",
			$params
		);
	}

	/** Totales para tarjetas y alertas: vencidos y por vencer (cantidad de lotes y valor). */
	public static function resumen($dias)
	{
		$dias = max(1, (int)$dias);
		$r = dbRow(
			"SELECT
				SUM(CASE WHEN l.fecha_vencimiento < CURDATE() THEN 1 ELSE 0 END) AS vencidos,
				SUM(CASE WHEN l.fecha_vencimiento < CURDATE() THEN l.stock * IF(l.costo_unitario>0, l.costo_unitario, a.precio_compra) ELSE 0 END) AS vencidos_valor,
				SUM(CASE WHEN l.fecha_vencimiento >= CURDATE() AND l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN 1 ELSE 0 END) AS por_vencer,
				SUM(CASE WHEN l.fecha_vencimiento >= CURDATE() AND l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN l.stock * IF(l.costo_unitario>0, l.costo_unitario, a.precio_compra) ELSE 0 END) AS por_vencer_valor,
				COUNT(*) AS lotes_con_stock
			 FROM lote l INNER JOIN articulo a ON a.idarticulo=l.idarticulo
			 WHERE l.condicion=1 AND l.stock>0 AND a.condicion=1",
			array($dias, $dias)
		);
		return array(
			'vencidos' => (int)($r ? $r['vencidos'] : 0),
			'vencidos_valor' => round((float)($r ? $r['vencidos_valor'] : 0), 2),
			'por_vencer' => (int)($r ? $r['por_vencer'] : 0),
			'por_vencer_valor' => round((float)($r ? $r['por_vencer_valor'] : 0), 2),
			'lotes_con_stock' => (int)($r ? $r['lotes_con_stock'] : 0),
			'dias' => $dias
		);
	}
}
