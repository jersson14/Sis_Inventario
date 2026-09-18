<?php
/**
 * Modelo ProCenter: kardex, alertas, top vendidos, utilidad y compras sugeridas.
 *
 * Todas las consultas con datos externos usan sentencias preparadas.
 * El kardex sale de la vista kardex_movimiento (compras, ventas, devoluciones
 * y ajustes); ventas, utilidad y sugeridos de venta_linea (netos de notas de credito).
 */
require_once "../config/Conexion.php";

class ProCenter
{
    public function __construct()
    {
    }

    public function articulosActivos()
    {
        $sql = "SELECT a.idarticulo, a.nombre, IFNULL(a.codigo,'') AS codigo, IFNULL(u.abreviatura,'und') AS unidad
        FROM articulo a
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        WHERE a.condicion=1
        ORDER BY a.nombre ASC";
        return dbQuery($sql);
    }

    public function infoArticulo($idarticulo)
    {
        $sql = "SELECT a.idarticulo, a.nombre, a.codigo, a.stock, a.stock_minimo, a.precio_compra, IFNULL(u.abreviatura,'und') AS unidad
        FROM articulo a
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        WHERE a.idarticulo=? LIMIT 1";
        return dbRow($sql, array((int)$idarticulo));
    }

    /**
     * Entradas y salidas historicas totales (compras, ventas, devoluciones y
     * ajustes: vista kardex_movimiento).
     */
    /**
     * Filtro de almacen para el kardex. Con un almacen: solo sus movimientos
     * (los traslados cuentan como entrada o salida). Sin almacen (todos): los
     * traslados se omiten porque solo cambian el stock de lugar.
     */
    private function filtroAlmacen($idalmacen, &$params, $alias = '')
    {
        if ((int)$idalmacen > 0) {
            $params[] = (int)$idalmacen;
            return " AND {$alias}idalmacen=?";
        }
        return " AND {$alias}tipo NOT LIKE 'TRASLADO%'";
    }

    public function kardexTotales($idarticulo, $idalmacen = 0)
    {
        $params = array((int)$idarticulo);
        $filtro = $this->filtroAlmacen($idalmacen, $params);
        $row = dbRow(
            "SELECT IFNULL(SUM(entrada),0) AS entradas_total, IFNULL(SUM(salida),0) AS salidas_total
             FROM kardex_movimiento WHERE idarticulo=?" . $filtro,
            $params
        );
        return $row ? $row : array('entradas_total' => 0, 'salidas_total' => 0);
    }

    /** Entradas y salidas anteriores a una fecha (para el saldo inicial del rango). */
    public function kardexAntesDeFecha($idarticulo, $fechaDesde, $idalmacen = 0)
    {
        $fechaDesde = trim((string)$fechaDesde);
        if ($fechaDesde === '') {
            return array('entradas_antes' => 0, 'salidas_antes' => 0);
        }
        $params = array((int)$idarticulo, $fechaDesde);
        $filtro = $this->filtroAlmacen($idalmacen, $params);
        $row = dbRow(
            "SELECT IFNULL(SUM(entrada),0) AS entradas_antes, IFNULL(SUM(salida),0) AS salidas_antes
             FROM kardex_movimiento WHERE idarticulo=? AND DATE(fecha_hora) < ?" . $filtro,
            $params
        );
        return $row ? $row : array('entradas_antes' => 0, 'salidas_antes' => 0);
    }

    /**
     * Movimientos del kardex ordenados por fecha: compras, ventas, devoluciones
     * (notas de credito) y ajustes. $fechaDesde / $fechaHasta pueden ir vacios.
     */
    public function kardexMovimientos($idarticulo, $fechaDesde, $fechaHasta, $idalmacen = 0)
    {
        $where = "k.idarticulo=?";
        $params = array((int)$idarticulo);
        $fechaDesde = trim((string)$fechaDesde);
        $fechaHasta = trim((string)$fechaHasta);
        if ($fechaDesde !== '') {
            $where .= " AND DATE(k.fecha_hora) >= ?";
            $params[] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $where .= " AND DATE(k.fecha_hora) <= ?";
            $params[] = $fechaHasta;
        }
        $where .= $this->filtroAlmacen($idalmacen, $params, 'k.');
        $sql = "SELECT k.fecha_hora, k.tipo,
            CONCAT(k.documento, IFNULL(CONCAT(' · ', NULLIF(CONCAT_WS(' / ', NULLIF(v.talla,''), NULLIF(v.color,'')),'')),'')) AS documento,
            k.tercero, k.entrada, k.salida, k.costo, k.precio_ref, IFNULL(al.nombre,'') AS almacen
          FROM kardex_movimiento k
          LEFT JOIN articulo_variante v ON v.idvariante=k.idvariante
          LEFT JOIN almacen al ON al.idalmacen=k.idalmacen
          WHERE $where
          ORDER BY DATE_FORMAT(k.fecha_hora, '%Y-%m-%d %H:%i') ASC, (k.entrada > 0) DESC, k.fecha_hora ASC";
        return dbQuery($sql, $params);
    }

    public function alertasStockMinimo()
    {
        $sql = "SELECT a.idarticulo, a.nombre, a.codigo, a.stock, a.stock_minimo, IFNULL(u.abreviatura,'und') AS unidad,
        (a.stock_minimo-a.stock) AS faltante
        FROM articulo a
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        WHERE a.condicion=1 AND a.stock<=a.stock_minimo
        ORDER BY faltante DESC, a.nombre ASC";
        return dbQuery($sql);
    }

    /** Articulos sin movimiento (ingreso, venta o ajuste) en los ultimos $dias dias. */
    public function alertasSinMovimiento($dias)
    {
        $dias = (int)$dias;
        if ($dias <= 0) {
            $dias = 30;
        }

        $sql = "SELECT a.idarticulo, a.nombre, a.codigo, a.stock, IFNULL(u.abreviatura,'und') AS unidad,
          MAX(m.fecha_hora) AS ultimo_mov
        FROM articulo a
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        LEFT JOIN (
            SELECT idarticulo, fecha_hora FROM kardex_movimiento
        ) m ON m.idarticulo=a.idarticulo
        WHERE a.condicion=1
        GROUP BY a.idarticulo, a.nombre, a.codigo, a.stock, u.abreviatura
        HAVING ultimo_mov IS NULL OR ultimo_mov < DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY ultimo_mov ASC";
        return dbQuery($sql, array($dias));
    }

    public function topVendidos($desde, $hasta, $limit = 10)
    {
        $limit = (int)$limit;
        if ($limit <= 0) {
            $limit = 10;
        }
        $desde = trim((string)$desde);
        $hasta = trim((string)$hasta);

        // venta_linea: lo vendido (+) y lo devuelto en notas de credito (-), cada uno en su fecha
        $where = "dv.estado='Aceptado'";
        $params = array();
        if ($desde !== '') {
            $where .= " AND DATE(dv.fecha_hora)>=?";
            $params[] = $desde;
        }
        if ($hasta !== '') {
            $where .= " AND DATE(dv.fecha_hora)<=?";
            $params[] = $hasta;
        }

        $sql = "SELECT a.nombre, a.codigo, IFNULL(u.abreviatura,'und') AS unidad,
          SUM(dv.cantidad*dv.factor) AS cantidad,
          SUM((dv.cantidad*dv.precio_venta)-dv.descuento) AS total
        FROM venta_linea dv
        INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        WHERE $where
        GROUP BY a.idarticulo, a.nombre, a.codigo, u.abreviatura
        ORDER BY cantidad DESC
        LIMIT " . (int)$limit;
        return dbQuery($sql, $params);
    }

    /**
     * Utilidad por producto / categoria / vendedor. Costo unitario: ultimo
     * ingreso aceptado anterior a la venta o articulo.precio_compra.
     */
    public function utilidad($desde, $hasta, $agrupar)
    {
        $desde = trim((string)$desde);
        $hasta = trim((string)$hasta);
        $agrupar = strtolower(trim((string)$agrupar));
        if (!in_array($agrupar, array('producto', 'categoria', 'vendedor'), true)) {
            $agrupar = 'producto';
        }

        // venta_linea: lo vendido (+) y lo devuelto en notas de credito (-), cada uno en su fecha
        $where = "dv.estado='Aceptado'";
        $params = array();
        if ($desde !== '') {
            $where .= " AND DATE(dv.fecha_hora)>=?";
            $params[] = $desde;
        }
        if ($hasta !== '') {
            $where .= " AND DATE(dv.fecha_hora)<=?";
            $params[] = $hasta;
        }

        $base = "SELECT
            dv.idventa,
            DATE(dv.fecha_hora) AS fecha,
            u.nombre AS vendedor,
            IFNULL(c.nombre,'SIN CATEGORIA') AS categoria,
            a.nombre AS producto,
            dv.cantidad,
            dv.cantidad_costo,
            dv.factor,
            dv.precio_venta,
            dv.descuento,
            IFNULL((
              SELECT di2.precio_compra/di2.factor
              FROM detalle_ingreso di2
              INNER JOIN ingreso i2 ON i2.idingreso=di2.idingreso
              WHERE di2.idarticulo=dv.idarticulo
                AND i2.estado='Aceptado'
                AND i2.fecha_hora<=dv.fecha_hora
              ORDER BY i2.fecha_hora DESC, di2.iddetalle_ingreso DESC
              LIMIT 1
            ), a.precio_compra) AS costo_unit
          FROM venta_linea dv
          INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
          LEFT JOIN categoria c ON c.idcategoria=a.idcategoria
          INNER JOIN usuario u ON u.idusuario=dv.idusuario
          WHERE $where";

        $agregados = "SUM(cantidad*factor) AS cantidad,
              SUM((cantidad*precio_venta)-descuento) AS venta,
              SUM(cantidad_costo*factor*costo_unit) AS costo,
              SUM(((cantidad*precio_venta)-descuento)-(cantidad_costo*factor*costo_unit)) AS utilidad";

        if ($agrupar === 'categoria') {
            $sql = "SELECT categoria, $agregados FROM ($base) t GROUP BY categoria ORDER BY utilidad DESC";
            return dbQuery($sql, $params);
        }
        if ($agrupar === 'vendedor') {
            $sql = "SELECT vendedor, $agregados FROM ($base) t GROUP BY vendedor ORDER BY utilidad DESC";
            return dbQuery($sql, $params);
        }
        $sql = "SELECT producto, categoria, vendedor, $agregados FROM ($base) t GROUP BY producto, categoria, vendedor ORDER BY utilidad DESC";
        return dbQuery($sql, $params);
    }

    public function comprasSugeridas($diasAnalisis, $diasCobertura)
    {
        $diasAnalisis = (int)$diasAnalisis;
        $diasCobertura = (int)$diasCobertura;
        if ($diasAnalisis <= 0) {
            $diasAnalisis = 30;
        }
        if ($diasCobertura <= 0) {
            $diasCobertura = 15;
        }

        $sql = "SELECT
          a.idarticulo,
          a.nombre,
          a.codigo,
          IFNULL(u.abreviatura,'und') AS unidad,
          a.stock,
          a.stock_minimo,
          IFNULL(vt.cantidad_vendida,0) AS vendido_periodo,
          ROUND(IFNULL(vt.cantidad_vendida,0)/?,3) AS promedio_diario,
          ROUND(((IFNULL(vt.cantidad_vendida,0)/?)*?) + a.stock_minimo,3) AS stock_objetivo,
          ROUND(GREATEST((((IFNULL(vt.cantidad_vendida,0)/?)*?) + a.stock_minimo) - a.stock,0),3) AS sugerido
        FROM articulo a
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        LEFT JOIN (
          SELECT dv.idarticulo, SUM(dv.cantidad*dv.factor) AS cantidad_vendida
          FROM venta_linea dv
          WHERE dv.estado='Aceptado'
            AND DATE(dv.fecha_hora) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
          GROUP BY dv.idarticulo
        ) vt ON vt.idarticulo=a.idarticulo
        WHERE a.condicion=1
        ORDER BY sugerido DESC, promedio_diario DESC";
        return dbQuery($sql, array($diasAnalisis, $diasAnalisis, $diasCobertura, $diasAnalisis, $diasCobertura, $diasAnalisis));
    }
}
