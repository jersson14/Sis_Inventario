<?php
/**
 * Ajustes manuales de inventario (entradas y salidas con motivo).
 * No hay trigger: este modelo actualiza articulo.stock dentro de una transaccion.
 */
require_once "../config/Conexion.php";
require_once "../config/negocio.php";
require_once "../modelos/Lote.php";
require_once "../modelos/Variante.php";
require_once "../modelos/Stock.php";

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

	public function articulosActivos($idalmacen = 0)
	{
		// Con almacen: el stock de ese almacen (lo que se puede ajustar alli)
		$colStock = (int)$idalmacen > 0 ? "IFNULL((SELECT SUM(sa.stock) FROM stock_almacen sa WHERE sa.idarticulo=a.idarticulo AND sa.idalmacen=" . (int)$idalmacen . "),0)" : "a.stock";
		return dbQuery("SELECT a.idarticulo, a.nombre, IFNULL(a.codigo,'') AS codigo, " . $colStock . " AS stock, a.precio_compra, IFNULL(u.abreviatura,'und') AS unidad, IFNULL(u.permite_fraccion,0) AS permite_fraccion,
			(SELECT COUNT(*) FROM articulo_variante v WHERE v.idarticulo=a.idarticulo AND v.condicion=1) AS variantes
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
	public function registrar($idarticulo, $idusuario, $tipo, $motivo, $cantidad, $costo_unitario, $observacion, $idlote = 0, $loteCodigo = '', $loteVencimiento = '', $idvariante = 0, $idalmacen = 0)
	{
		$idarticulo = (int)$idarticulo;
		$idusuario = (int)$idusuario;
		$tipo = strtoupper(trim((string)$tipo));
		$motivo = strtoupper(trim((string)$motivo));
		// Decimales solo si el rubro usa fracciones y la unidad del articulo lo admite
		$fraccion = articulosPermitenFraccion(array((int)$idarticulo));
		$cantidad = cantidadSegura($cantidad, !empty($fraccion[(int)$idarticulo]));
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
		$usaLotes = Lote::activo();
		// Articulo con tallas/colores: el ajuste es de una combinacion concreta
		$conVariantes = Variante::articulosConVariantes(array($idarticulo));
		$variante = Variante::resolverDetalle($idarticulo, $idvariante, $conVariantes);
		if (is_string($variante)) {
			return array('ok' => false, 'message' => $variante . '.');
		}
		$idvariante = $variante ? (int)$variante['idvariante'] : null;
		$loteCodigo = $usaLotes && $tipo === 'ENTRADA' ? Lote::codigoValido($loteCodigo) : '';
		$loteVencimiento = $usaLotes && $tipo === 'ENTRADA' ? Lote::fechaValida($loteVencimiento) : '';
		if ($loteVencimiento === false) {
			return array('ok' => false, 'message' => 'La fecha de vencimiento no es válida.');
		}
		// Lote: la salida sale de uno elegido o en orden FEFO; la entrada suma a uno
		// existente, crea uno nuevo (codigo/fecha) o queda como stock sin lote
		$lote = array('modo' => 'ninguno');
		if ($usaLotes && $tipo === 'SALIDA') {
			$lote = (int)$idlote > 0 ? array('modo' => 'lote', 'idlote' => (int)$idlote) : array('modo' => 'auto');
		} elseif ($usaLotes && (int)$idlote > 0) {
			$lote = array('modo' => 'lote', 'idlote' => (int)$idlote);
		} elseif ($usaLotes && ($loteVencimiento !== '' || $loteCodigo !== '')) {
			$lote = array('modo' => 'nuevo', 'codigo' => $loteCodigo, 'vence' => $loteVencimiento);
		}

		$error = '';
		$resultado = dbTransaccion(function () use ($idarticulo, $idusuario, $tipo, $motivo, $cantidad, $costo_unitario, $observacion, $idvariante, $lote, $idalmacen, &$error) {
			return self::moverStock(array(
				'idarticulo' => $idarticulo, 'idvariante' => $idvariante, 'idusuario' => $idusuario,
				'tipo' => $tipo, 'motivo' => $motivo, 'cantidad' => $cantidad, 'costo' => $costo_unitario,
				'observacion' => $observacion, 'lote' => $lote, 'idalmacen' => $idalmacen
			), $error);
		});

		if ($resultado === false) {
			return array('ok' => false, 'message' => $error !== '' ? $error : 'No se pudo registrar el ajuste.');
		}
		return $resultado;
	}

	/**
	 * Mueve el stock de un ajuste. Debe llamarse DENTRO de dbTransaccion(): si
	 * devuelve false hay que deshacer (el mensaje queda en $error).
	 * $p: idarticulo, idvariante (null o id ya validado), idusuario, tipo
	 *     (ENTRADA|SALIDA), motivo, cantidad (> 0, unidades base), costo (0 =
	 *     costo del articulo), observacion, idconteo (opcional), idalmacen (0 = principal) y lote:
	 *       array('modo' => 'ninguno')                 sin tocar lotes (entrada = stock sin lote)
	 *       array('modo' => 'auto')                    salida FEFO (lo vencido primero)
	 *       array('modo' => 'lote', 'idlote' => N)     sale de / entra a ese lote
	 *       array('modo' => 'sin_lote')                salida solo del stock sin lote
	 *       array('modo' => 'nuevo', 'codigo', 'vence') entrada que crea un lote
	 * Devuelve array(ok, message, idajuste, stock_nuevo, articulo) o false.
	 */
	public static function moverStock(array $p, &$error)
	{
		$idarticulo = (int)$p['idarticulo'];
		$idvariante = !empty($p['idvariante']) ? (int)$p['idvariante'] : null;
		$tipo = $p['tipo'];
		$cantidad = round((float)$p['cantidad'], 3);
		$lote = isset($p['lote']) ? $p['lote'] : array('modo' => 'ninguno');
		$modoLote = isset($lote['modo']) ? $lote['modo'] : 'ninguno';
		$idalmacen = !empty($p['idalmacen']) ? (int)$p['idalmacen'] : Stock::principal();
		$nombreAlm = Stock::multiAlmacen() ? ' en ' . Stock::nombre($idalmacen) : '';
		if ($cantidad <= 0) {
			$error = 'La cantidad debe ser mayor que cero.';
			return false;
		}

		$art = dbRow("SELECT idarticulo, nombre, stock, precio_compra, condicion FROM articulo WHERE idarticulo=? FOR UPDATE", array($idarticulo));
		if (!$art) {
			$error = 'El artículo no existe.';
			return false;
		}
		if ((int)$art['condicion'] !== 1) {
			$error = 'El artículo ' . $art['nombre'] . ' está desactivado.';
			return false;
		}
		$stockAnterior = (float)$art['stock'];
		$delta = $tipo === 'ENTRADA' ? $cantidad : -$cantidad;
		$stockNuevo = round($stockAnterior + $delta, 3);
		if ($stockNuevo < -0.0005) {
			$error = 'No puedes retirar más de lo que hay en stock de ' . $art['nombre'] . ' (' . formatearCantidad($stockAnterior) . ').';
			return false;
		}
		$stockNuevo = max(0, $stockNuevo);
		$costo = (float)$p['costo'] > 0 ? round((float)$p['costo'], 2) : (float)$art['precio_compra'];
		if ($tipo === 'SALIDA') {
			// Lo que sale tiene que estar en el almacen del ajuste
			$enAlm = Stock::enAlmacen($idalmacen, $idarticulo, $idvariante, true);
			if ($enAlm + 0.0005 < $cantidad) {
				$error = 'De ' . $art['nombre'] . ($idvariante !== null ? ' (esa talla/color)' : '') . ' solo hay ' . formatearCantidad(max(0, $enAlm)) . $nombreAlm . '.';
				return false;
			}
		}
		if ($modoLote === 'sin_lote' && $tipo === 'SALIDA') {
			$sinLote = round(Stock::enAlmacen($idalmacen, $idarticulo) - self::stockEnLotes($idarticulo, $idalmacen), 3);
			if ($sinLote + 0.0005 < $cantidad) {
				$error = 'El stock sin lote de ' . $art['nombre'] . $nombreAlm . ' es ' . formatearCantidad(max(0, $sinLote)) . '.';
				return false;
			}
		}

		$id = dbInsert("INSERT INTO ajuste_inventario(idarticulo, idvariante, idalmacen, idusuario, tipo, motivo, cantidad, stock_anterior, stock_nuevo, costo_unitario, observacion, idconteo, fecha_hora)
			VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
			array($idarticulo, $idvariante, $idalmacen, (int)$p['idusuario'], $tipo, $p['motivo'], $cantidad, $stockAnterior, $stockNuevo, $costo,
				substr((string)$p['observacion'], 0, 200), !empty($p['idconteo']) ? (int)$p['idconteo'] : null));
		if ($id <= 0) {
			return false;
		}
		// Total del articulo, su talla y el almacen del ajuste
		if (!Stock::mover($idarticulo, $idvariante, $delta, $idalmacen)) {
			return false;
		}

		if (Lote::activo()) {
			$ref = array('tipo' => 'AJUSTE', 'idajuste' => $id);
			if ($tipo === 'ENTRADA' && $modoLote === 'nuevo') {
				if (Lote::crear($idarticulo, $cantidad, $lote['vence'], $lote['codigo'], $costo, null, $id, $idalmacen) <= 0) {
					return false;
				}
			} elseif ($tipo === 'ENTRADA' && $modoLote === 'lote') {
				if ((int)dbValue("SELECT IFNULL(idalmacen,0) FROM lote WHERE idlote=?", array((int)$lote['idlote']), 0) !== $idalmacen) {
					$error = 'El lote está en otro almacén.';
					return false;
				}
				$r = Lote::sumarLote((int)$lote['idlote'], $idarticulo, $cantidad, $id);
				if ($r !== true) {
					$error = $r . '.';
					return false;
				}
			} elseif ($tipo === 'SALIDA' && $modoLote === 'lote') {
				$r = Lote::consumirLote((int)$lote['idlote'], $idarticulo, $cantidad, $ref, $idalmacen);
				if ($r !== true) {
					// false (no un array): dbTransaccion solo deshace el ajuste ya escrito con false
					$error = $r . '.';
					return false;
				}
			} elseif ($tipo === 'SALIDA' && $modoLote === 'auto') {
				if (Lote::consumir($idarticulo, $cantidad, $ref, $idalmacen) === false) {
					return false;
				}
			}
		}
		if (!Lote::ajustarAlStock($idarticulo)) {
			return false;
		}
		return array('ok' => true, 'message' => 'Ajuste registrado. Stock de ' . $art['nombre'] . ': ' . formatearCantidad($stockAnterior) . ' → ' . formatearCantidad($stockNuevo), 'idajuste' => $id, 'stock_nuevo' => $stockNuevo, 'articulo' => $art['nombre']);
	}

	/** Stock que esta en lotes activos (el resto del articulo es stock sin lote). */
	public static function stockEnLotes($idarticulo, $idalmacen = 0)
	{
		return round((float)dbValue(
			"SELECT IFNULL(SUM(stock),0) FROM lote WHERE idarticulo=? AND condicion=1 AND stock>0" . ((int)$idalmacen > 0 ? " AND idalmacen=" . (int)$idalmacen : ""),
			array((int)$idarticulo), 0), 3);
	}

	/**
	 * Lo que corresponde a un codigo escaneado (exacto, nunca por parecido):
	 * talla/color, presentacion (caja), articulo o codigo de lote.
	 * Devuelve {idarticulo, idvariante, idpresentacion, factor, presentacion, idlote} o null.
	 */
	public static function resolverCodigo($codigo)
	{
		$codigo = trim((string)$codigo);
		if ($codigo === '') {
			return null;
		}
		$res = array('idarticulo' => 0, 'idvariante' => 0, 'idpresentacion' => 0, 'factor' => 1, 'presentacion' => '', 'idlote' => 0);
		$v = Variante::buscarPorCodigo($codigo);
		if ($v) {
			$res['idarticulo'] = (int)$v['idarticulo'];
			$res['idvariante'] = (int)$v['idvariante'];
			return $res;
		}
		$a = dbRow("SELECT idarticulo FROM articulo WHERE codigo=? AND condicion=1 ORDER BY idarticulo LIMIT 1", array($codigo));
		if ($a) {
			$res['idarticulo'] = (int)$a['idarticulo'];
			return $res;
		}
		$p = dbRow(
			"SELECT p.idarticulo, p.idpresentacion, p.nombre, p.factor FROM articulo_presentacion p
			 INNER JOIN articulo a ON a.idarticulo=p.idarticulo
			 WHERE p.codigo=? AND p.condicion=1 AND a.condicion=1 LIMIT 1",
			array($codigo)
		);
		if ($p && (float)$p['factor'] > 0) {
			$res['idarticulo'] = (int)$p['idarticulo'];
			$res['idpresentacion'] = (int)$p['idpresentacion'];
			$res['factor'] = round((float)$p['factor'], 3);
			$res['presentacion'] = html_entity_decode((string)$p['nombre'], ENT_QUOTES, 'UTF-8');
			return $res;
		}
		// Codigo de lote impreso en la caja (solo si no es ambiguo)
		if (Lote::activo()) {
			$lotes = dbAll(
				"SELECT l.idlote, l.idarticulo FROM lote l INNER JOIN articulo a ON a.idarticulo=l.idarticulo
				 WHERE l.codigo_lote=? AND l.condicion=1 AND l.stock>0 AND a.condicion=1 LIMIT 2",
				array($codigo)
			);
			if (count($lotes) === 1) {
				$res['idarticulo'] = (int)$lotes[0]['idarticulo'];
				$res['idlote'] = (int)$lotes[0]['idlote'];
				return $res;
			}
		}
		return null;
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
			CONCAT(a.nombre, IFNULL(CONCAT(' (', NULLIF(CONCAT_WS(' / ', NULLIF(av.talla,''), NULLIF(av.color,'')),''), ')'),'')) AS articulo, COALESCE(av.codigo, a.codigo, '') AS codigo, IFNULL(um.abreviatura,'und') AS unidad, u.nombre AS usuario,
			IFNULL((SELECT al.nombre FROM almacen al WHERE al.idalmacen=aj.idalmacen),'') AS almacen
			FROM ajuste_inventario aj
			INNER JOIN articulo a ON a.idarticulo=aj.idarticulo
			LEFT JOIN articulo_variante av ON av.idvariante=aj.idvariante
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
