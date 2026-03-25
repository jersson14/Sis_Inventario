<?php
require "../config/Conexion.php";

class Cuentas
{
    public function __construct()
    {
    }

    public function listarClientes()
    {
        $sql = "SELECT idpersona,nombre FROM persona WHERE tipo_persona='Cliente' ORDER BY nombre ASC";
        return ejecutarConsulta($sql);
    }

    public function listarProveedores()
    {
        $sql = "SELECT idpersona,nombre FROM persona WHERE tipo_persona='Proveedor' ORDER BY nombre ASC";
        return ejecutarConsulta($sql);
    }

    public function insertarCuentaCobrar($idcliente, $fecha_emision, $fecha_vencimiento, $documento_ref, $monto_total, $observacion, $idventa = null)
    {
        $idventaSql = $idventa ? "'$idventa'" : "NULL";
        $sql = "INSERT INTO cuenta_cobrar(idcliente,idventa,fecha_emision,fecha_vencimiento,documento_ref,monto_total,saldo,estado,observacion)
        VALUES('$idcliente',$idventaSql,'$fecha_emision','$fecha_vencimiento','$documento_ref','$monto_total','$monto_total','PENDIENTE','$observacion')";
        return ejecutarConsulta($sql);
    }

    public function insertarCuentaPagar($idproveedor, $fecha_emision, $fecha_vencimiento, $documento_ref, $monto_total, $observacion, $idingreso = null)
    {
        $idingresoSql = $idingreso ? "'$idingreso'" : "NULL";
        $sql = "INSERT INTO cuenta_pagar(idproveedor,idingreso,fecha_emision,fecha_vencimiento,documento_ref,monto_total,saldo,estado,observacion)
        VALUES('$idproveedor',$idingresoSql,'$fecha_emision','$fecha_vencimiento','$documento_ref','$monto_total','$monto_total','PENDIENTE','$observacion')";
        return ejecutarConsulta($sql);
    }

    public function listarCuentasCobrar()
    {
        $sql = "SELECT cc.idcuenta_cobrar,cc.fecha_emision,cc.fecha_vencimiento,cc.documento_ref,cc.monto_total,cc.saldo,cc.estado,
          p.nombre AS cliente,
          IFNULL(SUM(pc.monto),0) AS pagado
        FROM cuenta_cobrar cc
        INNER JOIN persona p ON p.idpersona=cc.idcliente
        LEFT JOIN pago_cuenta_cobrar pc ON pc.idcuenta_cobrar=cc.idcuenta_cobrar
        GROUP BY cc.idcuenta_cobrar,cc.fecha_emision,cc.fecha_vencimiento,cc.documento_ref,cc.monto_total,cc.saldo,cc.estado,p.nombre
        ORDER BY cc.fecha_vencimiento ASC, cc.idcuenta_cobrar DESC";
        return ejecutarConsulta($sql);
    }

    public function listarCuentasPagar()
    {
        $sql = "SELECT cp.idcuenta_pagar,cp.fecha_emision,cp.fecha_vencimiento,cp.documento_ref,cp.monto_total,cp.saldo,cp.estado,
          p.nombre AS proveedor,
          IFNULL(SUM(pp.monto),0) AS pagado
        FROM cuenta_pagar cp
        INNER JOIN persona p ON p.idpersona=cp.idproveedor
        LEFT JOIN pago_cuenta_pagar pp ON pp.idcuenta_pagar=cp.idcuenta_pagar
        GROUP BY cp.idcuenta_pagar,cp.fecha_emision,cp.fecha_vencimiento,cp.documento_ref,cp.monto_total,cp.saldo,cp.estado,p.nombre
        ORDER BY cp.fecha_vencimiento ASC, cp.idcuenta_pagar DESC";
        return ejecutarConsulta($sql);
    }

    public function obtenerCuentaCobrar($id)
    {
        $sql = "SELECT * FROM cuenta_cobrar WHERE idcuenta_cobrar='$id'";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function obtenerCuentaPagar($id)
    {
        $sql = "SELECT * FROM cuenta_pagar WHERE idcuenta_pagar='$id'";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function registrarPagoCobrar($idcuenta, $idusuario, $monto, $medio_pago, $observacion)
    {
        $cuenta = $this->obtenerCuentaCobrar($idcuenta);
        if (!$cuenta) {
            return false;
        }

        $saldo = (float)$cuenta['saldo'];
        $monto = (float)$monto;
        if ($monto <= 0 || $monto > $saldo) {
            return false;
        }

        $sqlPago = "INSERT INTO pago_cuenta_cobrar(idcuenta_cobrar,idusuario,monto,medio_pago,observacion)
        VALUES('$idcuenta','$idusuario','$monto','$medio_pago','$observacion')";
        ejecutarConsulta($sqlPago);

        $nuevoSaldo = $saldo - $monto;
        $estado = $nuevoSaldo <= 0.0001 ? 'PAGADO' : 'PENDIENTE';
        $sqlUpd = "UPDATE cuenta_cobrar SET saldo='$nuevoSaldo',estado='$estado' WHERE idcuenta_cobrar='$idcuenta'";
        return ejecutarConsulta($sqlUpd);
    }

    public function registrarPagoPagar($idcuenta, $idusuario, $monto, $medio_pago, $observacion)
    {
        $cuenta = $this->obtenerCuentaPagar($idcuenta);
        if (!$cuenta) {
            return false;
        }

        $saldo = (float)$cuenta['saldo'];
        $monto = (float)$monto;
        if ($monto <= 0 || $monto > $saldo) {
            return false;
        }

        $sqlPago = "INSERT INTO pago_cuenta_pagar(idcuenta_pagar,idusuario,monto,medio_pago,observacion)
        VALUES('$idcuenta','$idusuario','$monto','$medio_pago','$observacion')";
        ejecutarConsulta($sqlPago);

        $nuevoSaldo = $saldo - $monto;
        $estado = $nuevoSaldo <= 0.0001 ? 'PAGADO' : 'PENDIENTE';
        $sqlUpd = "UPDATE cuenta_pagar SET saldo='$nuevoSaldo',estado='$estado' WHERE idcuenta_pagar='$idcuenta'";
        return ejecutarConsulta($sqlUpd);
    }
}
?>
