<?php
/**
 * Stock por almacen.
 *
 * Reglas:
 *  - articulo.stock (y articulo_variante.stock) es el total de todos los
 *    almacenes, incluido el oculto "En transito". Los reportes globales siguen
 *    usando el total; ventas, ajustes, conteos y transferencias usan el stock
 *    del almacen (stock_almacen, idvariante 0 = sin talla/color).
 *  - Los triggers de detalle_venta / detalle_ingreso mueven el almacen del
 *    documento. Lo que se mueve desde PHP pasa por Stock::mover() (total +
 *    almacen) o Stock::moverAlmacen() (solo entre almacenes: transferencias).
 *  - Lo que cambia el total a mano (ficha del articulo, importacion, tallas)
 *    se cuadra con Stock::cuadrar(): la diferencia va al almacen principal.
 */
require_once "../config/Conexion.php";
require_once "../modelos/Variante.php";

class Stock
{
	private static $cachePrincipal = null;
	private static $cacheTransito = null;

	/** Id del almacen principal. */
	public static function principal()
	{
		if (self::$cachePrincipal === null) {
			self::$cachePrincipal = (int)dbValue("SELECT idalmacen FROM almacen WHERE principal=1 ORDER BY idalmacen LIMIT 1", array(), 0);
		}
		return self::$cachePrincipal;
	}

	/** Id del almacen oculto "En transito" (mercaderia enviada sin recibir). */
	public static function transito()
	{
		if (self::$cacheTransito === null) {
			self::$cacheTransito = (int)dbValue("SELECT idalmacen FROM almacen WHERE tipo='TRANSITO' ORDER BY idalmacen LIMIT 1", array(), 0);
		}
		return self::$cacheTransito;
	}

	/** Almacenes activos visibles (sin el de transito). */
	public static function almacenes($soloActivos = true)
	{
		return dbAll(
			"SELECT idalmacen, nombre, direccion, responsable, principal, condicion FROM almacen
			 WHERE tipo='NORMAL'" . ($soloActivos ? " AND condicion=1" : "") . " ORDER BY principal DESC, nombre ASC"
		);
	}

	/** ¿Hay mas de un almacen activo? (si no, la interfaz no muestra nada de almacenes) */
	public static function multiAlmacen()
	{
		return (int)dbValue("SELECT COUNT(*) FROM almacen WHERE tipo='NORMAL' AND condicion=1", array(), 1) > 1;
	}

	/** Id valido de un almacen NORMAL activo, o 0. */
	public static function almacenValido($idalmacen)
	{
		return (int)dbValue("SELECT idalmacen FROM almacen WHERE idalmacen=? AND tipo='NORMAL' AND condicion=1", array((int)$idalmacen), 0);
	}

	public static function nombre($idalmacen)
	{
		return html_entity_decode((string)dbValue("SELECT nombre FROM almacen WHERE idalmacen=?", array((int)$idalmacen), ''), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Almacen en que trabaja la sesion: el elegido en la cabecera, si no el del
	 * usuario, si no el principal.
	 */
	public static function almacenActual()
	{
		if (!empty($_SESSION['idalmacen']) && self::almacenValido($_SESSION['idalmacen'])) {
			return (int)$_SESSION['idalmacen'];
		}
		if (!empty($_SESSION['idusuario'])) {
			$propio = self::almacenValido((int)dbValue("SELECT IFNULL(idalmacen,0) FROM usuario WHERE idusuario=?", array((int)$_SESSION['idusuario']), 0));
			if ($propio > 0) {
				return $propio;
			}
		}
		return self::principal();
	}

	/**
	 * Suma (delta > 0) o resta (delta < 0) unidades base al total del articulo,
	 * a su talla/color y al almacen. Devuelve true o false (deshacer).
	 */
	public static function mover($idarticulo, $idvariante, $delta, $idalmacen = 0)
	{
		$delta = round((float)$delta, 3);
		if (abs($delta) < 0.0005) {
			return true;
		}
		if (!dbExec("UPDATE articulo SET stock=ROUND(stock+?,3) WHERE idarticulo=?", array($delta, (int)$idarticulo))) {
			return false;
		}
		if (!empty($idvariante) && !Variante::moverStock((int)$idvariante, $delta)) {
			return false;
		}
		return self::moverAlmacen($idalmacen > 0 ? $idalmacen : self::principal(), $idarticulo, $idvariante, $delta);
	}

	/** Mueve solo el stock de un almacen (el total no cambia: transferencias). */
	public static function moverAlmacen($idalmacen, $idarticulo, $idvariante, $delta)
	{
		$delta = round((float)$delta, 3);
		if (abs($delta) < 0.0005) {
			return true;
		}
		return dbExec(
			"INSERT INTO stock_almacen (idalmacen,idarticulo,idvariante,stock) VALUES (?,?,?,?)
			 ON DUPLICATE KEY UPDATE stock=ROUND(stock+VALUES(stock),3)",
			array((int)$idalmacen, (int)$idarticulo, (int)$idvariante, $delta)
		);
	}

	/**
	 * Stock en un almacen. $idvariante null = todo el articulo (suma de sus
	 * tallas); un id = esa talla/color. $bloquear: FOR UPDATE (dentro de una transaccion).
	 */
	public static function enAlmacen($idalmacen, $idarticulo, $idvariante = null, $bloquear = false)
	{
		$sql = "SELECT IFNULL(SUM(stock),0) FROM stock_almacen WHERE idalmacen=? AND idarticulo=?";
		$params = array((int)$idalmacen, (int)$idarticulo);
		if ($idvariante !== null) {
			$sql .= " AND idvariante=?";
			$params[] = (int)$idvariante;
		}
		// Si algo cambio el total sin pasar por un almacen (datos cargados por
		// fuera), se cuadra antes de leer
		self::asegurar($idarticulo);
		if ($bloquear) {
			// Bloquea las filas del articulo en ese almacen antes de leer
			dbAll("SELECT stock FROM stock_almacen WHERE idalmacen=? AND idarticulo=? FOR UPDATE", array((int)$idalmacen, (int)$idarticulo));
		}
		return round((float)dbValue($sql, $params, 0), 3);
	}

	/** Stock del articulo (o talla) en cada almacen visible: [{idalmacen, nombre, stock}]. */
	public static function porAlmacen($idarticulo, $idvariante = null)
	{
		$params = array((int)$idarticulo);
		$filtroVar = '';
		if ($idvariante !== null) {
			$filtroVar = ' AND s.idvariante=?';
			$params[] = (int)$idvariante;
		}
		return dbAll(
			"SELECT al.idalmacen, al.nombre, al.principal, IFNULL(SUM(s.stock),0) AS stock
			 FROM almacen al
			 LEFT JOIN stock_almacen s ON s.idalmacen=al.idalmacen AND s.idarticulo=?" . $filtroVar . "
			 WHERE al.tipo='NORMAL' AND al.condicion=1
			 GROUP BY al.idalmacen, al.nombre, al.principal
			 ORDER BY al.principal DESC, al.nombre ASC",
			$params
		);
	}

	/**
	 * Articulos cuyo total (o el de alguna talla) no coincide con la suma de sus
	 * almacenes. Deberia estar vacio; se usa para verificar y tras importaciones.
	 */
	public static function descuadrados()
	{
		$ids = array();
		foreach (dbAll(
			"SELECT a.idarticulo FROM articulo a
			 LEFT JOIN (SELECT idarticulo, SUM(stock) AS s FROM stock_almacen GROUP BY idarticulo) x ON x.idarticulo=a.idarticulo
			 WHERE ABS(a.stock - IFNULL(x.s,0)) > 0.0005
			 UNION
			 SELECT v.idarticulo FROM articulo_variante v
			 LEFT JOIN (SELECT idvariante, SUM(stock) AS s FROM stock_almacen WHERE idvariante>0 GROUP BY idvariante) x ON x.idvariante=v.idvariante
			 WHERE v.condicion=1 AND ABS(v.stock - IFNULL(x.s,0)) > 0.0005"
		) as $r) {
			$ids[] = (int)$r['idarticulo'];
		}
		return $ids;
	}

	/** Cuadra un articulo solo si su total no coincide con sus almacenes. */
	public static function asegurar($idarticulo)
	{
		$idarticulo = (int)$idarticulo;
		$des = (int)dbValue(
			"SELECT (SELECT COUNT(*) FROM articulo a WHERE a.idarticulo=? AND ABS(a.stock - IFNULL((SELECT SUM(s.stock) FROM stock_almacen s WHERE s.idarticulo=a.idarticulo),0)) > 0.0005)
			      + (SELECT COUNT(*) FROM articulo_variante v WHERE v.idarticulo=? AND v.condicion=1 AND ABS(v.stock - IFNULL((SELECT SUM(s.stock) FROM stock_almacen s WHERE s.idvariante=v.idvariante),0)) > 0.0005)",
			array($idarticulo, $idarticulo),
			0
		);
		return $des > 0 ? self::cuadrar($idarticulo) : true;
	}

	/** Cuadra todos los articulos descuadrados (la diferencia va al principal). */
	public static function cuadrarDescuadrados()
	{
		foreach (self::descuadrados() as $id) {
			if (!self::cuadrar($id)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Cuadra el stock por almacen con el total del articulo (y de cada talla):
	 * la diferencia va al almacen principal. Para cambios hechos a mano sobre el
	 * total (ficha del articulo, importacion, tallas nuevas). Devuelve true/false.
	 */
	public static function cuadrar($idarticulo)
	{
		$idarticulo = (int)$idarticulo;
		$principal = self::principal();
		if ($principal <= 0) {
			return true;
		}
		$variantes = dbAll("SELECT idvariante, stock FROM articulo_variante WHERE idarticulo=? AND condicion=1", array($idarticulo));
		$objetivos = array();
		if ($variantes) {
			foreach ($variantes as $v) {
				$objetivos[(int)$v['idvariante']] = round((float)$v['stock'], 3);
			}
		} else {
			$objetivos[0] = round((float)dbValue("SELECT stock FROM articulo WHERE idarticulo=?", array($idarticulo), 0), 3);
		}
		// Filas que ya no corresponden (sin talla en un articulo que ahora tiene tallas, o tallas quitadas)
		$enAlm = dbAll("SELECT idalmacen, idvariante, stock FROM stock_almacen WHERE idarticulo=? FOR UPDATE", array($idarticulo));
		$sumas = array();
		foreach ($enAlm as $r) {
			$iv = (int)$r['idvariante'];
			if (!isset($objetivos[$iv])) {
				if (abs((float)$r['stock']) > 0.0005 && !dbExec("UPDATE stock_almacen SET stock=0 WHERE idalmacen=? AND idarticulo=? AND idvariante=?", array((int)$r['idalmacen'], $idarticulo, $iv))) {
					return false;
				}
				continue;
			}
			$sumas[$iv] = (isset($sumas[$iv]) ? $sumas[$iv] : 0) + (float)$r['stock'];
		}
		foreach ($objetivos as $iv => $total) {
			$dif = round($total - (isset($sumas[$iv]) ? $sumas[$iv] : 0), 3);
			if (abs($dif) >= 0.0005 && !self::moverAlmacen($principal, $idarticulo, $iv, $dif)) {
				return false;
			}
		}
		return true;
	}
}
