<?php
/**
 * Variantes de articulo: talla y color con stock propio (capacidad 'variantes').
 *
 * Reglas:
 *  - En un articulo con variantes activas, articulo.stock = suma del stock de
 *    sus variantes. Los triggers de detalle_venta/detalle_ingreso mueven ambos
 *    en la misma sentencia; los modelos lo hacen igual al anular o ajustar.
 *  - Un articulo con variantes exige elegir variante en ventas, compras,
 *    cotizaciones y ajustes, sin importar el rubro: si se vendiera "el
 *    articulo" sin talla, ya no se sabria de que talla salio.
 *  - El rubro solo decide si se pueden crear o editar variantes.
 *  - Las variantes no se borran: se desactivan (condicion=0) para conservar
 *    el historial, y solo si no tienen stock.
 */
require_once "../config/Conexion.php";
require_once "../config/negocio.php";

class Variante
{
	public static function activo()
	{
		return negocioTiene('variantes');
	}

	/** "M / Negro", "M", "Negro" o "" segun lo que tenga. */
	public static function etiqueta($talla, $color)
	{
		return implode(' / ', array_filter(array(trim((string)$talla), trim((string)$color)), 'strlen'));
	}

	public static function textoCorto($valor, $max)
	{
		$valor = trim(preg_replace('/\s+/', ' ', (string)$valor));
		return function_exists('mb_substr') ? mb_substr($valor, 0, $max, 'UTF-8') : substr($valor, 0, $max);
	}

	public static function deArticulo($idarticulo, $soloActivas = true)
	{
		return dbAll(
			"SELECT idvariante, idarticulo, talla, color, codigo, stock, stock_minimo, precio_venta, orden, condicion
			 FROM articulo_variante WHERE idarticulo=?" . ($soloActivas ? " AND condicion=1" : "") . "
			 ORDER BY orden ASC, idvariante ASC",
			array((int)$idarticulo)
		);
	}

	public static function tieneVariantes($idarticulo)
	{
		return (int)dbValue("SELECT COUNT(*) FROM articulo_variante WHERE idarticulo=? AND condicion=1", array((int)$idarticulo), 0) > 0;
	}

	/** Mapa idarticulo => true para los articulos (de la lista) que tienen variantes activas. */
	public static function articulosConVariantes(array $ids)
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if (!$ids) {
			return array();
		}
		$ph = implode(',', array_fill(0, count($ids), '?'));
		$mapa = array();
		foreach (dbAll("SELECT DISTINCT idarticulo FROM articulo_variante WHERE condicion=1 AND idarticulo IN ($ph)", $ids) as $r) {
			$mapa[(int)$r['idarticulo']] = true;
		}
		return $mapa;
	}

	/**
	 * Valida la variante de una linea de detalle. Devuelve la fila de la
	 * variante, null si el articulo no usa variantes, o un string con el error.
	 */
	public static function resolverDetalle($idarticulo, $idvariante, $conVariantes, $nombreArticulo = '')
	{
		$idvariante = (int)$idvariante;
		if (empty($conVariantes[(int)$idarticulo])) {
			return $idvariante > 0 ? 'Se envió una talla/color para un artículo que no las tiene' : null;
		}
		if ($idvariante <= 0) {
			return 'Elige la talla y el color de ' . ($nombreArticulo !== '' ? $nombreArticulo : 'cada artículo');
		}
		$v = dbRow(
			"SELECT idvariante, talla, color, stock, precio_venta FROM articulo_variante WHERE idvariante=? AND idarticulo=? AND condicion=1",
			array($idvariante, (int)$idarticulo)
		);
		return $v ? $v : 'Una de las tallas/colores del detalle no es válida o fue desactivada';
	}

	/** Mueve el stock de una variante (delta positivo suma, negativo resta). */
	public static function moverStock($idvariante, $delta)
	{
		return dbExec("UPDATE articulo_variante SET stock=ROUND(stock+?,3) WHERE idvariante=?", array(round((float)$delta, 3), (int)$idvariante));
	}

	/** Deja articulo.stock igual a la suma de sus variantes activas (si tiene). */
	public static function recalcularArticulo($idarticulo)
	{
		if (!self::tieneVariantes($idarticulo)) {
			return true;
		}
		return dbExec(
			"UPDATE articulo SET stock=(SELECT IFNULL(SUM(stock),0) FROM articulo_variante WHERE idarticulo=? AND condicion=1) WHERE idarticulo=?",
			array((int)$idarticulo, (int)$idarticulo)
		);
	}

	/** Variante activa por codigo de barras exacto: {idarticulo, idvariante} o null. */
	public static function buscarPorCodigo($codigo)
	{
		if ((string)$codigo === '') {
			return null;
		}
		return dbRow(
			"SELECT v.idarticulo, v.idvariante FROM articulo_variante v
			 INNER JOIN articulo a ON a.idarticulo=v.idarticulo
			 WHERE v.codigo=? AND v.condicion=1 AND a.condicion=1 LIMIT 1",
			array((string)$codigo)
		);
	}

	/** ¿Otro articulo, presentacion o variante usa este codigo? */
	public static function codigoEnUso($codigo, $idarticulo)
	{
		return (int)dbValue(
			"SELECT (SELECT COUNT(*) FROM articulo WHERE codigo=? AND idarticulo<>?)
			      + (SELECT COUNT(*) FROM articulo_variante WHERE codigo=? AND idarticulo<>? AND condicion=1)",
			array((string)$codigo, (int)$idarticulo, (string)$codigo, (int)$idarticulo),
			0
		) > 0;
	}

	/**
	 * Sincroniza las variantes del formulario del articulo. Debe llamarse dentro
	 * de una transaccion. $filas: {idvariante, talla, color, codigo, stock_inicial,
	 * stock_minimo, precio_venta}, ya validadas y sin combinaciones repetidas.
	 * El stock de una variante existente no se edita aqui (se mueve con compras,
	 * ventas y ajustes); el stock_inicial solo aplica a variantes nuevas.
	 * Devuelve true o un mensaje de error.
	 */
	public static function sincronizar($idarticulo, array $filas)
	{
		$idarticulo = (int)$idarticulo;
		$actuales = array();
		foreach (self::deArticulo($idarticulo, false) as $v) {
			$actuales[(int)$v['idvariante']] = $v;
		}
		$recibidas = array();
		foreach ($filas as $f) {
			if ($f['idvariante'] > 0) {
				$recibidas[$f['idvariante']] = true;
			}
		}
		// 1. Quitar: solo si ya no tiene stock; se desactiva para conservar el historial
		foreach ($actuales as $id => $v) {
			if ((int)$v['condicion'] === 1 && !isset($recibidas[$id])) {
				if ((float)$v['stock'] > 0.0005) {
					return 'No puedes quitar ' . self::etiqueta($v['talla'], $v['color']) . ': todavía tiene ' . formatearCantidad($v['stock']) . ' en stock. Retíralo con un ajuste de salida primero';
				}
				if (!dbExec("UPDATE articulo_variante SET condicion=0 WHERE idvariante=?", array($id))) {
					return 'No se pudo quitar una talla/color';
				}
			}
		}
		$orden = 0;
		foreach ($filas as $f) {
			$orden++;
			$codigo = $f['codigo'] !== '' ? $f['codigo'] : null;
			$id = $f['idvariante'];
			if ($id > 0 && isset($actuales[$id]) && (int)$actuales[$id]['condicion'] === 1) {
				// Si una variante inactiva ocupa esa talla/color, se le cambia para liberar la combinacion
				dbExec(
					"UPDATE articulo_variante SET talla=LEFT(CONCAT(talla,'~',idvariante),20) WHERE idarticulo=? AND talla=? AND color=? AND idvariante<>? AND condicion=0",
					array($idarticulo, $f['talla'], $f['color'], $id)
				);
				$ok = dbExec(
					"UPDATE articulo_variante SET talla=?, color=?, codigo=?, stock_minimo=?, precio_venta=?, orden=? WHERE idvariante=? AND idarticulo=?",
					array($f['talla'], $f['color'], $codigo, $f['stock_minimo'], $f['precio_venta'], $orden, $id, $idarticulo)
				);
				if (!$ok) {
					return 'No se pudo actualizar la talla/color ' . self::etiqueta($f['talla'], $f['color']);
				}
				continue;
			}
			// Nueva combinacion: si existio antes, se reactiva con su mismo id
			$previa = (int)dbValue(
				"SELECT idvariante FROM articulo_variante WHERE idarticulo=? AND talla=? AND color=? AND condicion=0 LIMIT 1",
				array($idarticulo, $f['talla'], $f['color']),
				0
			);
			if ($previa > 0) {
				$ok = dbExec(
					"UPDATE articulo_variante SET codigo=?, stock=?, stock_minimo=?, precio_venta=?, orden=?, condicion=1 WHERE idvariante=?",
					array($codigo, $f['stock_inicial'], $f['stock_minimo'], $f['precio_venta'], $orden, $previa)
				);
			} else {
				$ok = dbInsert(
					"INSERT INTO articulo_variante (idarticulo,talla,color,codigo,stock,stock_minimo,precio_venta,orden,condicion) VALUES (?,?,?,?,?,?,?,?,1)",
					array($idarticulo, $f['talla'], $f['color'], $codigo, $f['stock_inicial'], $f['stock_minimo'], $f['precio_venta'], $orden)
				) > 0;
			}
			if (!$ok) {
				return 'No se pudo registrar la talla/color ' . self::etiqueta($f['talla'], $f['color']);
			}
		}
		return self::recalcularArticulo($idarticulo) ? true : 'No se pudo actualizar el stock del artículo';
	}

	/**
	 * Cantidades por talla/color que trajo una compra, con su stock actual
	 * bloqueado. Devuelve array de filas o un string si alguna talla/color ya
	 * no tiene lo que entro (se vendio): en ese caso la compra no se revierte.
	 */
	public static function reversionIngreso($idingreso, $verbo)
	{
		$filas = dbAll(
			"SELECT idvariante, SUM(cantidad*factor) AS cantidad FROM detalle_ingreso WHERE idingreso=? AND idvariante IS NOT NULL GROUP BY idvariante",
			array((int)$idingreso)
		);
		foreach ($filas as &$f) {
			$v = dbRow(
				"SELECT v.stock, v.talla, v.color, a.nombre FROM articulo_variante v INNER JOIN articulo a ON a.idarticulo=v.idarticulo WHERE v.idvariante=? FOR UPDATE",
				array((int)$f['idvariante'])
			);
			if (!$v) {
				return 'No se encontró una de las tallas/colores de la compra';
			}
			if ((float)$v['stock'] + 0.0005 < (float)$f['cantidad']) {
				return 'No se puede ' . $verbo . ': el stock de ' . $v['nombre'] . ' ' . self::etiqueta($v['talla'], $v['color']) . ' ya fue utilizado';
			}
		}
		unset($f);
		return $filas;
	}

	/** Variantes con stock en cero o bajo su minimo (articulos activos). */
	public static function resumenAlertas()
	{
		$r = dbRow(
			"SELECT
				SUM(CASE WHEN v.stock<=0 THEN 1 ELSE 0 END) AS agotadas,
				SUM(CASE WHEN v.stock>0 AND v.stock_minimo>0 AND v.stock<=v.stock_minimo THEN 1 ELSE 0 END) AS bajo_minimo
			 FROM articulo_variante v INNER JOIN articulo a ON a.idarticulo=v.idarticulo
			 WHERE v.condicion=1 AND a.condicion=1"
		);
		return array('agotadas' => (int)($r ? $r['agotadas'] : 0), 'bajo_minimo' => (int)($r ? $r['bajo_minimo'] : 0));
	}
}
