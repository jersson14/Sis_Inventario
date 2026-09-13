<?php
/**
 * Cotizaciones / proformas. No mueven stock; pueden convertirse en venta.
 */
require_once "../config/Conexion.php";

class Cotizacion
{
	public static function estados()
	{
		return array('PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'VENCIDA', 'CONVERTIDA');
	}

	public function serie()
	{
		$row = dbRow("SELECT serie_cotizacion FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1");
		$serie = $row && !empty($row['serie_cotizacion']) ? strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($row['serie_cotizacion']))) : 'COT';
		return $serie !== '' ? substr($serie, 0, 6) : 'COT';
	}

	public function siguienteNumero($forUpdate = false)
	{
		$serie = $this->serie();
		$row = dbRow("SELECT IFNULL(MAX(CAST(SUBSTRING_INDEX(numero,'-',-1) AS UNSIGNED)),0) AS maximo FROM cotizacion WHERE numero LIKE ?" . ($forUpdate ? " FOR UPDATE" : ""), array($serie . '-%'));
		$n = $row ? (int)$row['maximo'] + 1 : 1;
		return $serie . '-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
	}

	public function clientesActivos()
	{
		return dbQuery("SELECT idpersona, nombre, num_documento FROM persona WHERE tipo_persona='Cliente' AND condicion=1 ORDER BY nombre ASC");
	}

	/**
	 * Valida y normaliza items. Devuelve array(ok, items|mensaje, total).
	 */
	private function normalizarItems($idarticulo, $cantidad, $precio, $descuento)
	{
		if (!is_array($idarticulo) || count($idarticulo) === 0) {
			return array(false, 'Agrega al menos un artículo a la cotización.', 0);
		}
		$items = array();
		$total = 0;
		for ($i = 0; $i < count($idarticulo); $i++) {
			$id = (int)$idarticulo[$i];
			$cant = (int)round((float)(isset($cantidad[$i]) ? $cantidad[$i] : 0));
			$pre = round((float)(isset($precio[$i]) ? $precio[$i] : 0), 2);
			$des = round((float)(isset($descuento[$i]) ? $descuento[$i] : 0), 2);
			if ($id <= 0 || $cant <= 0) {
				return array(false, 'Hay un artículo o cantidad inválida en el detalle.', 0);
			}
			if ($pre < 0 || $des < 0 || $des > $cant * $pre) {
				return array(false, 'Precio o descuento inválido en el detalle.', 0);
			}
			$existe = dbRow("SELECT idarticulo FROM articulo WHERE idarticulo=? AND condicion=1", array($id));
			if (!$existe) {
				return array(false, 'Uno de los artículos no existe o está desactivado.', 0);
			}
			$items[] = array('idarticulo' => $id, 'cantidad' => $cant, 'precio' => $pre, 'descuento' => $des);
			$total += $cant * $pre - $des;
		}
		return array(true, $items, round($total, 2));
	}

	public function guardar($idcotizacion, $idcliente, $idusuario, $fecha_hora, $fecha_validez, $impuesto, $observacion, $condiciones, $idarticulo, $cantidad, $precio, $descuento)
	{
		$idcotizacion = (int)$idcotizacion;
		$idcliente = (int)$idcliente;
		$cliente = dbRow("SELECT idpersona FROM persona WHERE idpersona=? AND tipo_persona='Cliente' AND condicion=1", array($idcliente));
		if (!$cliente) {
			return array('ok' => false, 'message' => 'Selecciona un cliente válido.');
		}
		$fecha_hora = trim((string)$fecha_hora);
		if ($fecha_hora === '' || strtotime($fecha_hora) === false) {
			$fecha_hora = date('Y-m-d H:i:s');
		} else {
			$fecha_hora = date('Y-m-d H:i:s', strtotime($fecha_hora));
		}
		$fecha_validez = fechaSegura($fecha_validez, date('Y-m-d', strtotime($fecha_hora . ' +15 days')));
		$impuesto = round((float)$impuesto, 2);
		if ($impuesto < 0 || $impuesto > 100) $impuesto = 0;
		list($ok, $items, $total) = $this->normalizarItems($idarticulo, $cantidad, $precio, $descuento);
		if (!$ok) {
			return array('ok' => false, 'message' => $items);
		}
		$observacion = substr((string)$observacion, 0, 300);
		$condiciones = substr((string)$condiciones, 0, 300);

		$res = dbTransaccion(function () use ($idcotizacion, $idcliente, $idusuario, $fecha_hora, $fecha_validez, $impuesto, $observacion, $condiciones, $items, $total) {
			if ($idcotizacion > 0) {
				$actual = dbRow("SELECT idcotizacion, numero, estado FROM cotizacion WHERE idcotizacion=? FOR UPDATE", array($idcotizacion));
				if (!$actual) { return array('ok' => false, 'message' => 'La cotización no existe.'); }
				if ($actual['estado'] !== 'PENDIENTE') { return array('ok' => false, 'message' => 'Solo se pueden editar cotizaciones pendientes.'); }
				if (!dbExec("UPDATE cotizacion SET idcliente=?, fecha_hora=?, fecha_validez=?, impuesto=?, total=?, observacion=?, condiciones=? WHERE idcotizacion=?",
					array($idcliente, $fecha_hora, $fecha_validez, $impuesto, $total, $observacion, $condiciones, $idcotizacion))) { return false; }
				dbExec("DELETE FROM detalle_cotizacion WHERE idcotizacion=?", array($idcotizacion));
				$id = $idcotizacion;
				$numero = $actual['numero'];
			} else {
				$numero = $this->siguienteNumero(true);
				$id = dbInsert("INSERT INTO cotizacion(idcliente, idusuario, numero, fecha_hora, fecha_validez, impuesto, total, estado, observacion, condiciones) VALUES(?,?,?,?,?,?,?,'PENDIENTE',?,?)",
					array($idcliente, (int)$idusuario, $numero, $fecha_hora, $fecha_validez, $impuesto, $total, $observacion, $condiciones));
				if ($id <= 0) { return false; }
			}
			foreach ($items as $it) {
				if (dbInsert("INSERT INTO detalle_cotizacion(idcotizacion, idarticulo, cantidad, precio, descuento) VALUES(?,?,?,?,?)",
					array($id, $it['idarticulo'], (float)$it['cantidad'], $it['precio'], $it['descuento'])) <= 0) { return false; }
			}
			return array('ok' => true, 'idcotizacion' => $id, 'numero' => $numero, 'total' => $total, 'message' => ($idcotizacion > 0 ? 'Cotización actualizada' : 'Cotización ' . $numero . ' registrada'));
		});
		return $res === false ? array('ok' => false, 'message' => 'No se pudo guardar la cotización.') : $res;
	}

	public function mostrar($id)
	{
		return dbRow("SELECT c.*, DATE_FORMAT(c.fecha_hora,'%Y-%m-%d %H:%i:%s') AS fecha, p.nombre AS cliente, p.num_documento, p.tipo_documento, p.direccion, p.telefono, p.email, u.nombre AS usuario
			FROM cotizacion c
			INNER JOIN persona p ON p.idpersona=c.idcliente
			INNER JOIN usuario u ON u.idusuario=c.idusuario
			WHERE c.idcotizacion=?", array((int)$id));
	}

	public function detalles($id)
	{
		return dbQuery("SELECT d.*, a.nombre, a.codigo, a.stock, IFNULL(u.abreviatura,'und') AS unidad, (d.cantidad*d.precio-d.descuento) AS subtotal
			FROM detalle_cotizacion d
			INNER JOIN articulo a ON a.idarticulo=d.idarticulo
			LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			WHERE d.idcotizacion=? ORDER BY d.iddetalle_cotizacion ASC", array((int)$id));
	}

	public function actualizarVencidas()
	{
		return dbExec("UPDATE cotizacion SET estado='VENCIDA' WHERE estado='PENDIENTE' AND fecha_validez < CURDATE()");
	}

	public function listar($fecha_inicio, $fecha_fin, $estado = '')
	{
		$where = array("1=1");
		$params = array();
		if ($fecha_inicio !== '') { $where[] = "DATE(c.fecha_hora) >= ?"; $params[] = $fecha_inicio; }
		if ($fecha_fin !== '') { $where[] = "DATE(c.fecha_hora) <= ?"; $params[] = $fecha_fin; }
		if (in_array($estado, self::estados(), true)) { $where[] = "c.estado = ?"; $params[] = $estado; }
		return dbQuery("SELECT c.idcotizacion, c.numero, DATE_FORMAT(c.fecha_hora,'%d/%m/%Y %H:%i') AS fecha, DATE_FORMAT(c.fecha_validez,'%d/%m/%Y') AS validez, c.fecha_validez, c.total, c.estado, c.idventa,
			p.nombre AS cliente, u.nombre AS usuario, DATEDIFF(c.fecha_validez, CURDATE()) AS dias
			FROM cotizacion c
			INNER JOIN persona p ON p.idpersona=c.idcliente
			INNER JOIN usuario u ON u.idusuario=c.idusuario
			WHERE " . implode(" AND ", $where) . " ORDER BY c.idcotizacion DESC", $params);
	}

	public function cambiarEstado($id, $estado)
	{
		$estado = strtoupper(trim((string)$estado));
		if (!in_array($estado, array('PENDIENTE', 'ACEPTADA', 'RECHAZADA'), true)) {
			return array('ok' => false, 'message' => 'Estado no válido.');
		}
		$c = dbRow("SELECT estado FROM cotizacion WHERE idcotizacion=?", array((int)$id));
		if (!$c) { return array('ok' => false, 'message' => 'La cotización no existe.'); }
		if ($c['estado'] === 'CONVERTIDA') { return array('ok' => false, 'message' => 'La cotización ya fue convertida en venta.'); }
		dbExec("UPDATE cotizacion SET estado=? WHERE idcotizacion=?", array($estado, (int)$id));
		return array('ok' => true, 'message' => 'Cotización marcada como ' . strtolower($estado) . '.');
	}

	public function marcarConvertida($id, $idventa)
	{
		return dbExec("UPDATE cotizacion SET estado='CONVERTIDA', idventa=? WHERE idcotizacion=? AND estado<>'CONVERTIDA'", array((int)$idventa, (int)$id));
	}

	/** Datos para precargar el punto de venta. */
	public function paraVenta($id)
	{
		$c = $this->mostrar($id);
		if (!$c) { return null; }
		$items = array();
		$rs = $this->detalles($id);
		if ($rs) {
			while ($d = $rs->fetch_assoc()) {
				$items[] = array(
					'idarticulo' => (int)$d['idarticulo'],
					'nombre' => html_entity_decode((string)$d['nombre'], ENT_QUOTES, 'UTF-8'),
					'unidad' => $d['unidad'],
					'stock' => (int)round((float)$d['stock']),
					'cantidad' => (int)round((float)$d['cantidad']),
					'precio' => round((float)$d['precio'], 2),
					'descuento' => round((float)$d['descuento'], 2)
				);
			}
		}
		return array(
			'idcotizacion' => (int)$c['idcotizacion'],
			'numero' => $c['numero'],
			'estado' => $c['estado'],
			'idcliente' => (int)$c['idcliente'],
			'impuesto' => (float)$c['impuesto'],
			'observacion' => 'Según cotización ' . $c['numero'],
			'items' => $items
		);
	}

	public function resumen()
	{
		return dbRow("SELECT
			(SELECT COUNT(*) FROM cotizacion WHERE estado='PENDIENTE') AS pendientes,
			(SELECT IFNULL(SUM(total),0) FROM cotizacion WHERE estado='PENDIENTE') AS pendientes_monto,
			(SELECT COUNT(*) FROM cotizacion WHERE estado='CONVERTIDA' AND fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS convertidas_30,
			(SELECT COUNT(*) FROM cotizacion WHERE fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS total_30");
	}
}
