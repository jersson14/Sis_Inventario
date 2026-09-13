<?php
/**
 * Ajustes manuales de inventario (entradas y salidas con motivo).
 * No hay trigger: este modelo actualiza articulo.stock dentro de una transaccion.
 */
require_once "../config/Conexion.php";

class Inventario
{
	public static function motivos()
	{
		return array(
			'CONTEO'               => 'Conteo físico / regularización',
			'INICIAL'              => 'Stock inicial',
			'MERMA'                => 'Merma o deterioro',
			'VENCIMIENTO'          => 'Producto vencido',
			'DEVOLUCION_CLIENTE'   => 'Devolución de cliente',
			'DEVOLUCION_PROVEEDOR' => 'Devolución a proveedor',
			'ROBO'                 => 'Robo o pérdida',
			'USO_INTERNO'          => 'Uso interno / consumo',
			'DONACION'             => 'Donación o muestra',
			'TRASLADO'             => 'Traslado entre locales',
			'OTRO'                 => 'Otro motivo',
		);
	}

	public function articulosActivos()
	{
		return dbQuery("SELECT a.idarticulo, a.nombre, IFNULL(a.codigo,'') AS codigo, a.stock, a.precio_compra, IFNULL(u.abreviatura,'und') AS unidad
			FROM articulo a
			LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			WHERE a.condicion=1
			ORDER BY a.nombre ASC");
	}

	public function infoArticulo($idarticulo)
	{
		return dbRow("SELECT a.idarticulo, a.nombre, a.codigo, a.stock, a.stock_minimo, a.precio_compra, a.precio_venta, IFNULL(u.abreviatura,'und') AS unidad
			FROM articulo a
			LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
			WHERE a.idarticulo=? LIMIT 1", array((int)$idarticulo));
	}

	/**
	 * Registra un ajuste. Devuelve array(ok, message, stock_nuevo).
	 */
	public function registrar($idarticulo, $idusuario, $tipo, $motivo, $cantidad, $costo_unitario, $observacion)
	{
		$idarticulo = (int)$idarticulo;
		$idusuario = (int)$idusuario;
		$tipo = strtoupper(trim((string)$tipo));
		$motivo = strtoupper(trim((string)$motivo));
		$cantidad = (int)round((float)$cantidad);
		$costo_unitario = round((float)$costo_unitario, 2);
		$observacion = substr((string)$observacion, 0, 200);

		if ($idarticulo <= 0) {
			return array('ok' => false, 'message' => 'Selecciona un artículo válido.');
		}
		if (!in_array($tipo, array('ENTRADA', 'SALIDA'), true)) {
			return array('ok' => false, 'message' => 'El tipo de ajuste debe ser ENTRADA o SALIDA.');
		}
		$motivos = self::motivos();
		if (!isset($motivos[$motivo])) {
			return array('ok' => false, 'message' => 'Selecciona un motivo válido.');
		}
		if ($cantidad <= 0) {
			return array('ok' => false, 'message' => 'La cantidad debe ser mayor que cero.');
		}
		if ($costo_unitario < 0) {
			return array('ok' => false, 'message' => 'El costo no puede ser negativo.');
		}

		$resultado = dbTransaccion(function () use ($idarticulo, $idusuario, $tipo, $motivo, $cantidad, $costo_unitario, $observacion) {
			$art = dbRow("SELECT idarticulo, nombre, stock, precio_compra, condicion FROM articulo WHERE idarticulo=? FOR UPDATE", array($idarticulo));
			if (!$art) {
				return array('ok' => false, 'message' => 'El artículo no existe.');
			}
			if ((int)$art['condicion'] !== 1) {
				return array('ok' => false, 'message' => 'El artículo está desactivado.');
			}
			$stockAnterior = (float)$art['stock'];
			$delta = $tipo === 'ENTRADA' ? $cantidad : -$cantidad;
			$stockNuevo = $stockAnterior + $delta;
			if ($stockNuevo < 0) {
				return array('ok' => false, 'message' => 'No puedes retirar más de lo que hay en stock (' . number_format($stockAnterior, 0) . ').');
			}
			$costo = $costo_unitario > 0 ? $costo_unitario : (float)$art['precio_compra'];

			$id = dbInsert("INSERT INTO ajuste_inventario(idarticulo, idusuario, tipo, motivo, cantidad, stock_anterior, stock_nuevo, costo_unitario, observacion, fecha_hora)
				VALUES(?,?,?,?,?,?,?,?,?,NOW())",
				array($idarticulo, $idusuario, $tipo, $motivo, (float)$cantidad, $stockAnterior, $stockNuevo, $costo, $observacion));
			if ($id <= 0) {
				return false;
			}
			if (!dbExec("UPDATE articulo SET stock=? WHERE idarticulo=?", array($stockNuevo, $idarticulo))) {
				return false;
			}
			return array('ok' => true, 'message' => 'Ajuste registrado. Stock de ' . $art['nombre'] . ': ' . number_format($stockAnterior, 0) . ' → ' . number_format($stockNuevo, 0), 'idajuste' => $id, 'stock_nuevo' => $stockNuevo, 'articulo' => $art['nombre']);
		});

		if ($resultado === false) {
			return array('ok' => false, 'message' => 'No se pudo registrar el ajuste.');
		}
		return $resultado;
	}

	public function listar($fecha_inicio, $fecha_fin, $tipo = '', $idarticulo = 0)
	{
		$where = array("1=1");
		$params = array();
		if ($fecha_inicio !== '') {
			$where[] = "DATE(aj.fecha_hora) >= ?";
			$params[] = $fecha_inicio;
		}
		if ($fecha_fin !== '') {
			$where[] = "DATE(aj.fecha_hora) <= ?";
			$params[] = $fecha_fin;
		}
		if (in_array($tipo, array('ENTRADA', 'SALIDA'), true)) {
			$where[] = "aj.tipo = ?";
			$params[] = $tipo;
		}
		if ((int)$idarticulo > 0) {
			$where[] = "aj.idarticulo = ?";
			$params[] = (int)$idarticulo;
		}
		$sql = "SELECT aj.idajuste, aj.fecha_hora, aj.tipo, aj.motivo, aj.cantidad, aj.stock_anterior, aj.stock_nuevo, aj.costo_unitario, aj.observacion,
			a.nombre AS articulo, IFNULL(a.codigo,'') AS codigo, IFNULL(um.abreviatura,'und') AS unidad, u.nombre AS usuario
			FROM ajuste_inventario aj
			INNER JOIN articulo a ON a.idarticulo=aj.idarticulo
			LEFT JOIN unidad_medida um ON um.idunidad=a.idunidad
			INNER JOIN usuario u ON u.idusuario=aj.idusuario
			WHERE " . implode(" AND ", $where) . "
			ORDER BY aj.idajuste DESC";
		return dbQuery($sql, $params);
	}

	public function resumen($fecha_inicio, $fecha_fin)
	{
		$where = array("1=1");
		$params = array();
		if ($fecha_inicio !== '') {
			$where[] = "DATE(fecha_hora) >= ?";
			$params[] = $fecha_inicio;
		}
		if ($fecha_fin !== '') {
			$where[] = "DATE(fecha_hora) <= ?";
			$params[] = $fecha_fin;
		}
		return dbRow("SELECT
			COUNT(*) AS ajustes,
			IFNULL(SUM(CASE WHEN tipo='ENTRADA' THEN cantidad ELSE 0 END),0) AS entradas,
			IFNULL(SUM(CASE WHEN tipo='SALIDA' THEN cantidad ELSE 0 END),0) AS salidas,
			IFNULL(SUM(CASE WHEN tipo='ENTRADA' THEN cantidad*costo_unitario ELSE 0 END),0) AS valor_entradas,
			IFNULL(SUM(CASE WHEN tipo='SALIDA' THEN cantidad*costo_unitario ELSE 0 END),0) AS valor_salidas
			FROM ajuste_inventario WHERE " . implode(" AND ", $where), $params);
	}
}
