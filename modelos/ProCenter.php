<?php
/**
 * Modelo ProCenter: kardex, alertas, top vendidos, utilidad y compras sugeridas.
 *
 * Todas las consultas con datos externos usan sentencias preparadas.
 * Los kardex unen ingresos + ventas + ajustes de inventario.
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

    /** Entradas y salidas historicas totales (ingresos + ventas + ajustes). */
    public function kardexTotales($idarticulo)
    {
        $id = (int)$idarticulo;
        $sql = "SELECT
          IFNULL((SELECT SUM(di.cantidad)
              FROM detalle_ingreso di
              INNER JOIN ingreso i ON i.idingreso=di.idingreso
              WHERE di.idarticulo=? AND i.estado='Aceptado'),0)
          + IFNULL((SELECT SUM(aj.cantidad) FROM ajuste_inventario aj WHERE aj.idarticulo=? AND aj.tipo='ENTRADA'),0) AS entradas_total,
          IFNULL((SELECT SUM(dv.cantidad)
              FROM detalle_venta dv
              INNER JOIN venta v ON v.idventa=dv.idventa
              WHERE dv.idarticulo=? AND v.estado='Aceptado'),0)
          + IFNULL((SELECT SUM(aj.cantidad) FROM ajuste_inventario aj WHERE aj.idarticulo=? AND aj.tipo='SALIDA'),0) AS salidas_total";
        $row = dbRow($sql, array($id, $id, $id, $id));
        return $row ? $row : array('entradas_total' => 0, 'salidas_total' => 0);
    }

    /** Entradas y salidas anteriores a una fecha (para el saldo inicial del rango). */
    public function kardexAntesDeFecha($idarticulo, $fechaDesde)
    {
        $fechaDesde = trim((string)$fechaDesde);
        if ($fechaDesde === '') {
            return array('entradas_antes' => 0, 'salidas_antes' => 0);
        }
        $id = (int)$idarticulo;
        $sql = "SELECT
          IFNULL((SELECT SUM(di.cantidad)
              FROM detalle_ingreso di
              INNER JOIN ingreso i ON i.idingreso=di.idingreso
              WHERE di.idarticulo=? AND i.estado='Aceptado' AND DATE(i.fecha_hora) < ?),0)
          + IFNULL((SELECT SUM(aj.cantidad) FROM ajuste_inventario aj
              WHERE aj.idarticulo=? AND aj.tipo='ENTRADA' AND DATE(aj.fecha_hora) < ?),0) AS entradas_antes,
          IFNULL((SELECT SUM(dv.cantidad)
              FROM detalle_venta dv
              INNER JOIN venta v ON v.idventa=dv.idventa
              WHERE dv.idarticulo=? AND v.estado='Aceptado' AND DATE(v.fecha_hora) < ?),0)
          + IFNULL((SELECT SUM(aj.cantidad) FROM ajuste_inventario aj
              WHERE aj.idarticulo=? AND aj.tipo='SALIDA' AND DATE(aj.fecha_hora) < ?),0) AS salidas_antes";
        $row = dbRow($sql, array($id, $fechaDesde, $id, $fechaDesde, $id, $fechaDesde, $id, $fechaDesde));
        return $row ? $row : array('entradas_antes' => 0, 'salidas_antes' => 0);
    }

    /**
     * Movimientos del kardex (ingresos, ventas y ajustes) ordenados por fecha.
     * $fechaDesde / $fechaHasta pueden ir vacios (sin filtro).
     */
    public function kardexMovimientos($idarticulo, $fechaDesde, $fechaHasta)
    {
        $id = (int)$idarticulo;
        $fechaDesde = trim((string)$fechaDesde);
        $fechaHasta = trim((string)$fechaHasta);

        $whereIngreso = "di.idarticulo=? AND i.estado='Aceptado'";
        $whereVenta = "dv.idarticulo=? AND v.estado='Aceptado'";
        $whereAjuste = "aj.idarticulo=?";
        $pIngreso = array($id);
        $pVenta = array($id);
        $pAjuste = array($id);

        if ($fechaDesde !== '') {
            $whereIngreso .= " AND DATE(i.fecha_hora) >= ?";
            $whereVenta .= " AND DATE(v.fecha_hora) >= ?";
            $whereAjuste .= " AND DATE(aj.fecha_hora) >= ?";
            $pIngreso[] = $fechaDesde;
            $pVenta[] = $fechaDesde;
            $pAjuste[] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $whereIngreso .= " AND DATE(i.fecha_hora) <= ?";
            $whereVenta .= " AND DATE(v.fecha_hora) <= ?";
            $whereAjuste .= " AND DATE(aj.fecha_hora) <= ?";
            $pIngreso[] = $fechaHasta;
            $pVenta[] = $fechaHasta;
            $pAjuste[] = $fechaHasta;
        }

        $sql = "SELECT * FROM (
          SELECT i.fecha_hora,
            'INGRESO' AS tipo,
            CONCAT(i.tipo_comprobante,' ',i.serie_comprobante,'-',i.num_comprobante) AS documento,
            IFNULL(p.nombre,'-') AS tercero,
            di.cantidad AS entrada,
            0.000 AS salida,
            di.precio_compra AS costo,
            di.precio_venta AS precio_ref
          FROM detalle_ingreso di
          INNER JOIN ingreso i ON i.idingreso=di.idingreso
          LEFT JOIN persona p ON p.idpersona=i.idproveedor
          WHERE $whereIngreso

          UNION ALL

          SELECT v.fecha_hora,
            'VENTA' AS tipo,
            CONCAT(v.tipo_comprobante,' ',v.serie_comprobante,'-',v.num_comprobante) AS documento,
            IFNULL(p.nombre,'-') AS tercero,
            0.000 AS entrada,
            dv.cantidad AS salida,
            IFNULL((SELECT di2.precio_compra
              FROM detalle_ingreso di2
              INNER JOIN ingreso i2 ON i2.idingreso=di2.idingreso
              WHERE di2.idarticulo=dv.idarticulo AND i2.estado='Aceptado' AND i2.fecha_hora<=v.fecha_hora
              ORDER BY i2.fecha_hora DESC, di2.iddetalle_ingreso DESC LIMIT 1), a.precio_compra) AS costo,
            dv.precio_venta AS precio_ref
          FROM detalle_venta dv
          INNER JOIN venta v ON v.idventa=dv.idventa
          INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
          LEFT JOIN persona p ON p.idpersona=v.idcliente
          WHERE $whereVenta

          UNION ALL

          SELECT aj.fecha_hora,
            IF(aj.tipo='ENTRADA','AJUSTE +','AJUSTE -') AS tipo,
            CONCAT('AJUSTE #',aj.idajuste,' ',aj.motivo) AS documento,
            IFNULL(u.nombre,'-') AS tercero,
            IF(aj.tipo='ENTRADA',aj.cantidad,0) AS entrada,
            IF(aj.tipo='SALIDA',aj.cantidad,0) AS salida,
            aj.costo_unitario AS costo,
            0 AS precio_ref
          FROM ajuste_inventario aj
          LEFT JOIN usuario u ON u.idusuario=aj.idusuario
          WHERE $whereAjuste
        ) k
        ORDER BY k.fecha_hora ASC";

        return dbQuery($sql, array_merge($pIngreso, $pVenta, $pAjuste));
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
            SELECT di.idarticulo, i.fecha_hora
            FROM detalle_ingreso di
            INNER JOIN ingreso i ON i.idingreso=di.idingreso
            WHERE i.estado='Aceptado'
            UNION ALL
            SELECT dv.idarticulo, v.fecha_hora
            FROM detalle_venta dv
            INNER JOIN venta v ON v.idventa=dv.idventa
            WHERE v.estado='Aceptado'
            UNION ALL
            SELECT aj.idarticulo, aj.fecha_hora
            FROM ajuste_inventario aj
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

        $where = "v.estado='Aceptado'";
        $params = array();
        if ($desde !== '') {
            $where .= " AND DATE(v.fecha_hora)>=?";
            $params[] = $desde;
        }
        if ($hasta !== '') {
            $where .= " AND DATE(v.fecha_hora)<=?";
            $params[] = $hasta;
        }

        $sql = "SELECT a.nombre, a.codigo, IFNULL(u.abreviatura,'und') AS unidad,
          SUM(dv.cantidad) AS cantidad,
          SUM((dv.cantidad*dv.precio_venta)-dv.descuento) AS total
        FROM detalle_venta dv
        INNER JOIN venta v ON v.idventa=dv.idventa
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

        $where = "v.estado='Aceptado'";
        $params = array();
        if ($desde !== '') {
            $where .= " AND DATE(v.fecha_hora)>=?";
            $params[] = $desde;
        }
        if ($hasta !== '') {
            $where .= " AND DATE(v.fecha_hora)<=?";
            $params[] = $hasta;
        }

        $base = "SELECT
            v.idventa,
            DATE(v.fecha_hora) AS fecha,
            u.nombre AS vendedor,
            IFNULL(c.nombre,'SIN CATEGORIA') AS categoria,
            a.nombre AS producto,
            dv.cantidad,
            dv.precio_venta,
            dv.descuento,
            IFNULL((
              SELECT di2.precio_compra
              FROM detalle_ingreso di2
              INNER JOIN ingreso i2 ON i2.idingreso=di2.idingreso
              WHERE di2.idarticulo=dv.idarticulo
                AND i2.estado='Aceptado'
                AND i2.fecha_hora<=v.fecha_hora
              ORDER BY i2.fecha_hora DESC, di2.iddetalle_ingreso DESC
              LIMIT 1
            ), a.precio_compra) AS costo_unit
          FROM detalle_venta dv
          INNER JOIN venta v ON v.idventa=dv.idventa
          INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
          LEFT JOIN categoria c ON c.idcategoria=a.idcategoria
          INNER JOIN usuario u ON u.idusuario=v.idusuario
          WHERE $where";

        $agregados = "SUM(cantidad) AS cantidad,
              SUM((cantidad*precio_venta)-descuento) AS venta,
              SUM(cantidad*costo_unit) AS costo,
              SUM(((cantidad*precio_venta)-descuento)-(cantidad*costo_unit)) AS utilidad";

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
          SELECT dv.idarticulo, SUM(dv.cantidad) AS cantidad_vendida
          FROM detalle_venta dv
          INNER JOIN venta v ON v.idventa=dv.idventa
          WHERE v.estado='Aceptado'
            AND DATE(v.fecha_hora) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
          GROUP BY dv.idarticulo
        ) vt ON vt.idarticulo=a.idarticulo
        WHERE a.condicion=1
        ORDER BY sugerido DESC, promedio_diario DESC";
        return dbQuery($sql, array($diasAnalisis, $diasAnalisis, $diasCobertura, $diasAnalisis, $diasCobertura, $diasAnalisis));
    }
}
