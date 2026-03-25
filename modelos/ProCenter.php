<?php
require "../config/Conexion.php";

class ProCenter
{
    public function __construct()
    {
    }

    public function articulosActivos()
    {
        $sql = "SELECT a.idarticulo,a.nombre,IFNULL(a.codigo,'') AS codigo,IFNULL(u.abreviatura,'und') AS unidad
        FROM articulo a
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        WHERE a.condicion=1
        ORDER BY a.nombre ASC";
        return ejecutarConsulta($sql);
    }

    public function infoArticulo($idarticulo)
    {
        $sql = "SELECT a.idarticulo,a.nombre,a.codigo,a.stock,a.stock_minimo,IFNULL(u.abreviatura,'und') AS unidad
        FROM articulo a
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        WHERE a.idarticulo='$idarticulo' LIMIT 1";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function kardexTotales($idarticulo)
    {
        $sql = "SELECT
          IFNULL((SELECT SUM(di.cantidad)
              FROM detalle_ingreso di
              INNER JOIN ingreso i ON i.idingreso=di.idingreso
              WHERE di.idarticulo='$idarticulo' AND i.estado='Aceptado'),0) AS entradas_total,
          IFNULL((SELECT SUM(dv.cantidad)
              FROM detalle_venta dv
              INNER JOIN venta v ON v.idventa=dv.idventa
              WHERE dv.idarticulo='$idarticulo' AND v.estado='Aceptado'),0) AS salidas_total";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function kardexAntesDeFecha($idarticulo, $fechaDesde)
    {
        $fechaDesde = trim((string)$fechaDesde);
        if ($fechaDesde === '') {
            return array('entradas_antes' => 0, 'salidas_antes' => 0);
        }

        $sql = "SELECT
          IFNULL((SELECT SUM(di.cantidad)
              FROM detalle_ingreso di
              INNER JOIN ingreso i ON i.idingreso=di.idingreso
              WHERE di.idarticulo='$idarticulo' AND i.estado='Aceptado' AND DATE(i.fecha_hora) < '$fechaDesde'),0) AS entradas_antes,
          IFNULL((SELECT SUM(dv.cantidad)
              FROM detalle_venta dv
              INNER JOIN venta v ON v.idventa=dv.idventa
              WHERE dv.idarticulo='$idarticulo' AND v.estado='Aceptado' AND DATE(v.fecha_hora) < '$fechaDesde'),0) AS salidas_antes";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function kardexMovimientos($idarticulo, $fechaDesde, $fechaHasta)
    {
        $whereIngreso = "di.idarticulo='$idarticulo' AND i.estado='Aceptado'";
        $whereVenta = "dv.idarticulo='$idarticulo' AND v.estado='Aceptado'";

        if ($fechaDesde !== '') {
            $whereIngreso .= " AND DATE(i.fecha_hora) >= '$fechaDesde'";
            $whereVenta .= " AND DATE(v.fecha_hora) >= '$fechaDesde'";
        }
        if ($fechaHasta !== '') {
            $whereIngreso .= " AND DATE(i.fecha_hora) <= '$fechaHasta'";
            $whereVenta .= " AND DATE(v.fecha_hora) <= '$fechaHasta'";
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
            (SELECT di2.precio_compra
              FROM detalle_ingreso di2
              INNER JOIN ingreso i2 ON i2.idingreso=di2.idingreso
              WHERE di2.idarticulo=dv.idarticulo AND i2.estado='Aceptado' AND i2.fecha_hora<=v.fecha_hora
              ORDER BY i2.fecha_hora DESC, di2.iddetalle_ingreso DESC LIMIT 1) AS costo,
            dv.precio_venta AS precio_ref
          FROM detalle_venta dv
          INNER JOIN venta v ON v.idventa=dv.idventa
          LEFT JOIN persona p ON p.idpersona=v.idcliente
          WHERE $whereVenta
        ) k
        ORDER BY k.fecha_hora ASC";

        return ejecutarConsulta($sql);
    }

    public function alertasStockMinimo()
    {
        $sql = "SELECT a.idarticulo,a.nombre,a.codigo,a.stock,a.stock_minimo,IFNULL(u.abreviatura,'und') AS unidad,
        (a.stock_minimo-a.stock) AS faltante
        FROM articulo a
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        WHERE a.condicion=1 AND a.stock<=a.stock_minimo
        ORDER BY faltante DESC, a.nombre ASC";
        return ejecutarConsulta($sql);
    }

    public function alertasSinMovimiento($dias)
    {
        $dias = (int)$dias;
        if ($dias <= 0) {
            $dias = 30;
        }

        $sql = "SELECT a.idarticulo,a.nombre,a.codigo,a.stock,IFNULL(u.abreviatura,'und') AS unidad,
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
        ) m ON m.idarticulo=a.idarticulo
        WHERE a.condicion=1
        GROUP BY a.idarticulo,a.nombre,a.codigo,a.stock,u.abreviatura
        HAVING ultimo_mov IS NULL OR ultimo_mov < DATE_SUB(NOW(), INTERVAL $dias DAY)
        ORDER BY ultimo_mov ASC";
        return ejecutarConsulta($sql);
    }

    public function topVendidos($desde, $hasta, $limit = 10)
    {
        $limit = (int)$limit;
        if ($limit <= 0) {
            $limit = 10;
        }

        $where = "v.estado='Aceptado'";
        if ($desde !== '') {
            $where .= " AND DATE(v.fecha_hora)>='$desde'";
        }
        if ($hasta !== '') {
            $where .= " AND DATE(v.fecha_hora)<='$hasta'";
        }

        $sql = "SELECT a.nombre,a.codigo,IFNULL(u.abreviatura,'und') AS unidad,
          SUM(dv.cantidad) AS cantidad,
          SUM((dv.cantidad*dv.precio_venta)-dv.descuento) AS total
        FROM detalle_venta dv
        INNER JOIN venta v ON v.idventa=dv.idventa
        INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        WHERE $where
        GROUP BY a.idarticulo,a.nombre,a.codigo,u.abreviatura
        ORDER BY cantidad DESC
        LIMIT $limit";
        return ejecutarConsulta($sql);
    }

    public function utilidad($desde, $hasta, $agrupar)
    {
        $where = "v.estado='Aceptado'";
        if ($desde !== '') {
            $where .= " AND DATE(v.fecha_hora)>='$desde'";
        }
        if ($hasta !== '') {
            $where .= " AND DATE(v.fecha_hora)<='$hasta'";
        }

        $base = "SELECT
            v.idventa,
            DATE(v.fecha_hora) AS fecha,
            u.nombre AS vendedor,
            c.nombre AS categoria,
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
            ),0) AS costo_unit
          FROM detalle_venta dv
          INNER JOIN venta v ON v.idventa=dv.idventa
          INNER JOIN articulo a ON a.idarticulo=dv.idarticulo
          INNER JOIN categoria c ON c.idcategoria=a.idcategoria
          INNER JOIN usuario u ON u.idusuario=v.idusuario
          WHERE $where";

        if ($agrupar === 'categoria') {
            $sql = "SELECT categoria,
              SUM(cantidad) AS cantidad,
              SUM((cantidad*precio_venta)-descuento) AS venta,
              SUM(cantidad*costo_unit) AS costo,
              SUM(((cantidad*precio_venta)-descuento)-(cantidad*costo_unit)) AS utilidad
            FROM ($base) t
            GROUP BY categoria
            ORDER BY utilidad DESC";
            return ejecutarConsulta($sql);
        }

        if ($agrupar === 'vendedor') {
            $sql = "SELECT vendedor,
              SUM(cantidad) AS cantidad,
              SUM((cantidad*precio_venta)-descuento) AS venta,
              SUM(cantidad*costo_unit) AS costo,
              SUM(((cantidad*precio_venta)-descuento)-(cantidad*costo_unit)) AS utilidad
            FROM ($base) t
            GROUP BY vendedor
            ORDER BY utilidad DESC";
            return ejecutarConsulta($sql);
        }

        $sql = "SELECT producto,categoria,vendedor,
          SUM(cantidad) AS cantidad,
          SUM((cantidad*precio_venta)-descuento) AS venta,
          SUM(cantidad*costo_unit) AS costo,
          SUM(((cantidad*precio_venta)-descuento)-(cantidad*costo_unit)) AS utilidad
        FROM ($base) t
        GROUP BY producto,categoria,vendedor
        ORDER BY utilidad DESC";
        return ejecutarConsulta($sql);
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
          ROUND(IFNULL(vt.cantidad_vendida,0)/$diasAnalisis,3) AS promedio_diario,
          ROUND(((IFNULL(vt.cantidad_vendida,0)/$diasAnalisis)*$diasCobertura) + a.stock_minimo,3) AS stock_objetivo,
          ROUND(GREATEST((((IFNULL(vt.cantidad_vendida,0)/$diasAnalisis)*$diasCobertura) + a.stock_minimo) - a.stock,0),3) AS sugerido
        FROM articulo a
        LEFT JOIN unidad_medida u ON u.idunidad=a.idunidad
        LEFT JOIN (
          SELECT dv.idarticulo, SUM(dv.cantidad) AS cantidad_vendida
          FROM detalle_venta dv
          INNER JOIN venta v ON v.idventa=dv.idventa
          WHERE v.estado='Aceptado'
            AND DATE(v.fecha_hora) >= DATE_SUB(CURDATE(), INTERVAL $diasAnalisis DAY)
          GROUP BY dv.idarticulo
        ) vt ON vt.idarticulo=a.idarticulo
        WHERE a.condicion=1
        ORDER BY sugerido DESC, promedio_diario DESC";
        return ejecutarConsulta($sql);
    }
}
?>
