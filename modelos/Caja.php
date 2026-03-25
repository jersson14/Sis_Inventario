<?php
require "../config/Conexion.php";

class Caja
{
    public function __construct()
    {
    }

    public function cajaAbiertaUsuario($idusuario)
    {
        $sql = "SELECT * FROM caja_diaria WHERE idusuario='$idusuario' AND estado='ABIERTA' ORDER BY idcaja DESC LIMIT 1";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function abrirCaja($idusuario, $monto_apertura, $observacion)
    {
        $abierta = $this->cajaAbiertaUsuario($idusuario);
        if ($abierta) {
            return false;
        }

        $sql = "INSERT INTO caja_diaria(idusuario,fecha_apertura,monto_apertura,estado,observacion)
        VALUES('$idusuario',NOW(),'$monto_apertura','ABIERTA','$observacion')";
        return ejecutarConsulta($sql);
    }

    public function agregarMovimiento($idcaja, $idusuario, $tipo, $concepto, $monto)
    {
        $sql = "INSERT INTO caja_movimiento(idcaja,idusuario,tipo,concepto,monto,fecha_hora)
        VALUES('$idcaja','$idusuario','$tipo','$concepto','$monto',NOW())";
        return ejecutarConsulta($sql);
    }

    public function resumenCaja($idcaja)
    {
        $sql = "SELECT c.idcaja,c.idusuario,c.fecha_apertura,c.fecha_cierre,c.monto_apertura,c.estado,c.observacion,
          IFNULL(SUM(CASE WHEN m.tipo='INGRESO' THEN m.monto ELSE 0 END),0) AS total_ingresos,
          IFNULL(SUM(CASE WHEN m.tipo='EGRESO' THEN m.monto ELSE 0 END),0) AS total_egresos
        FROM caja_diaria c
        LEFT JOIN caja_movimiento m ON m.idcaja=c.idcaja
        WHERE c.idcaja='$idcaja'
        GROUP BY c.idcaja,c.idusuario,c.fecha_apertura,c.fecha_cierre,c.monto_apertura,c.estado,c.observacion";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function cerrarCaja($idcaja, $monto_cierre_real, $observacion)
    {
        $resumen = $this->resumenCaja($idcaja);
        if (!$resumen || $resumen['estado'] !== 'ABIERTA') {
            return false;
        }

        $cierreSistema = (float)$resumen['monto_apertura'] + (float)$resumen['total_ingresos'] - (float)$resumen['total_egresos'];
        $diferencia = (float)$monto_cierre_real - $cierreSistema;

        $sql = "UPDATE caja_diaria SET
          fecha_cierre=NOW(),
          monto_cierre_sistema='$cierreSistema',
          monto_cierre_real='$monto_cierre_real',
          diferencia='$diferencia',
          estado='CERRADA',
          observacion=CONCAT(IFNULL(observacion,''), ' | Cierre: ', '$observacion')
          WHERE idcaja='$idcaja'";
        return ejecutarConsulta($sql);
    }

    public function movimientosCaja($idcaja)
    {
        $sql = "SELECT m.idmovimiento,m.fecha_hora,m.tipo,m.concepto,m.monto,u.nombre AS usuario
        FROM caja_movimiento m
        INNER JOIN usuario u ON u.idusuario=m.idusuario
        WHERE m.idcaja='$idcaja'
        ORDER BY m.idmovimiento DESC";
        return ejecutarConsulta($sql);
    }

    public function historialCajas($idusuario)
    {
        $sql = "SELECT c.idcaja,c.fecha_apertura,c.fecha_cierre,c.monto_apertura,c.monto_cierre_sistema,c.monto_cierre_real,c.diferencia,c.estado,
          IFNULL(SUM(CASE WHEN m.tipo='INGRESO' THEN m.monto ELSE 0 END),0) AS ingresos,
          IFNULL(SUM(CASE WHEN m.tipo='EGRESO' THEN m.monto ELSE 0 END),0) AS egresos
        FROM caja_diaria c
        LEFT JOIN caja_movimiento m ON m.idcaja=c.idcaja
        WHERE c.idusuario='$idusuario'
        GROUP BY c.idcaja,c.fecha_apertura,c.fecha_cierre,c.monto_apertura,c.monto_cierre_sistema,c.monto_cierre_real,c.diferencia,c.estado
        ORDER BY c.idcaja DESC";
        return ejecutarConsulta($sql);
    }
}
?>
