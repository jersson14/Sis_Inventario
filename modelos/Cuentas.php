<?php
/**
 * Modelo de cuentas por cobrar (CxC) y por pagar (CxP).
 *
 * - Consultas preparadas en todos los metodos.
 * - Los abonos/pagos se hacen dentro de una transaccion, bloqueando la cuenta
 *   con FOR UPDATE, y se reflejan en la caja abierta del usuario (si la hay).
 * - Los metodos de escritura devuelven array('ok'=>bool, 'message'=>string, ...).
 */
require_once "../config/Conexion.php";

class Cuentas
{
    /** Medios de pago aceptados. */
    const MEDIOS_PAGO = array('EFECTIVO', 'DEPOSITO', 'TARJETA', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'OTRO');

    /** Tolerancia para comparar montos decimales. */
    const TOLERANCIA = 0.005;

    public function __construct()
    {
    }

    /** Devuelve el medio de pago normalizado o EFECTIVO si no es valido. */
    public static function medioPagoSeguro($medio)
    {
        $medio = strtoupper(trim((string)$medio));
        return in_array($medio, self::MEDIOS_PAGO, true) ? $medio : 'EFECTIVO';
    }

    // ---------------------------------------------------------------
    // Listas auxiliares
    // ---------------------------------------------------------------

    public function listarClientes()
    {
        return dbQuery("SELECT idpersona, nombre FROM persona WHERE tipo_persona='Cliente' AND condicion=1 ORDER BY nombre ASC");
    }

    public function listarProveedores()
    {
        return dbQuery("SELECT idpersona, nombre FROM persona WHERE tipo_persona='Proveedor' AND condicion=1 ORDER BY nombre ASC");
    }

    /** Verifica que exista una persona activa del tipo indicado. */
    public function personaActiva($idpersona, $tipo)
    {
        $row = dbRow(
            "SELECT idpersona, nombre FROM persona WHERE idpersona=? AND tipo_persona=? AND condicion=1 LIMIT 1",
            array((int)$idpersona, (string)$tipo)
        );
        return $row ? $row : null;
    }

    // ---------------------------------------------------------------
    // Alta de cuentas
    // ---------------------------------------------------------------

    public function insertarCuentaCobrar($idcliente, $fecha_emision, $fecha_vencimiento, $documento_ref, $monto_total, $observacion, $idventa = null)
    {
        $monto = round((float)$monto_total, 2);
        if ($idventa) {
            $sql = "INSERT INTO cuenta_cobrar(idcliente, idventa, fecha_emision, fecha_vencimiento, documento_ref, monto_total, saldo, estado, observacion)
                VALUES(?,?,?,?,?,?,?,'PENDIENTE',?)";
            $params = array((int)$idcliente, (int)$idventa, (string)$fecha_emision, (string)$fecha_vencimiento, (string)$documento_ref, $monto, $monto, (string)$observacion);
        } else {
            $sql = "INSERT INTO cuenta_cobrar(idcliente, idventa, fecha_emision, fecha_vencimiento, documento_ref, monto_total, saldo, estado, observacion)
                VALUES(?,NULL,?,?,?,?,?,'PENDIENTE',?)";
            $params = array((int)$idcliente, (string)$fecha_emision, (string)$fecha_vencimiento, (string)$documento_ref, $monto, $monto, (string)$observacion);
        }
        return dbInsert($sql, $params);
    }

    public function insertarCuentaPagar($idproveedor, $fecha_emision, $fecha_vencimiento, $documento_ref, $monto_total, $observacion, $idingreso = null)
    {
        $monto = round((float)$monto_total, 2);
        if ($idingreso) {
            $sql = "INSERT INTO cuenta_pagar(idproveedor, idingreso, fecha_emision, fecha_vencimiento, documento_ref, monto_total, saldo, estado, observacion)
                VALUES(?,?,?,?,?,?,?,'PENDIENTE',?)";
            $params = array((int)$idproveedor, (int)$idingreso, (string)$fecha_emision, (string)$fecha_vencimiento, (string)$documento_ref, $monto, $monto, (string)$observacion);
        } else {
            $sql = "INSERT INTO cuenta_pagar(idproveedor, idingreso, fecha_emision, fecha_vencimiento, documento_ref, monto_total, saldo, estado, observacion)
                VALUES(?,NULL,?,?,?,?,?,'PENDIENTE',?)";
            $params = array((int)$idproveedor, (string)$fecha_emision, (string)$fecha_vencimiento, (string)$documento_ref, $monto, $monto, (string)$observacion);
        }
        return dbInsert($sql, $params);
    }

    // ---------------------------------------------------------------
    // Listados
    // ---------------------------------------------------------------

    /**
     * Construye el filtro de estado para los listados.
     * Devuelve array(sqlCondicion, params).
     */
    private function filtroEstado($estado, $alias)
    {
        $estado = strtoupper(trim((string)$estado));
        switch ($estado) {
            case 'PENDIENTE':
                return array(" AND $alias.estado='PENDIENTE' AND $alias.fecha_vencimiento>=CURDATE()", array());
            case 'VENCIDA':
                return array(" AND $alias.estado='PENDIENTE' AND $alias.fecha_vencimiento<CURDATE()", array());
            case 'PAGADO':
            case 'ANULADO':
                return array(" AND $alias.estado=?", array($estado));
            default:
                return array('', array());
        }
    }

    public function listarCuentasCobrar($estado = 'TODOS')
    {
        list($cond, $params) = $this->filtroEstado($estado, 'cc');
        $sql = "SELECT cc.idcuenta_cobrar, cc.fecha_emision, cc.fecha_vencimiento, cc.documento_ref, cc.monto_total, cc.saldo, cc.estado,
          p.nombre AS cliente,
          IFNULL(SUM(pc.monto),0) AS pagado,
          COUNT(pc.idpago_cobrar) AS num_pagos,
          DATEDIFF(cc.fecha_vencimiento, CURDATE()) AS dias
        FROM cuenta_cobrar cc
        INNER JOIN persona p ON p.idpersona=cc.idcliente
        LEFT JOIN pago_cuenta_cobrar pc ON pc.idcuenta_cobrar=cc.idcuenta_cobrar
        WHERE 1=1 $cond
        GROUP BY cc.idcuenta_cobrar, cc.fecha_emision, cc.fecha_vencimiento, cc.documento_ref, cc.monto_total, cc.saldo, cc.estado, p.nombre
        ORDER BY (cc.estado='PENDIENTE') DESC, cc.fecha_vencimiento ASC, cc.idcuenta_cobrar DESC";
        return dbQuery($sql, $params);
    }

    public function listarCuentasPagar($estado = 'TODOS')
    {
        list($cond, $params) = $this->filtroEstado($estado, 'cp');
        $sql = "SELECT cp.idcuenta_pagar, cp.fecha_emision, cp.fecha_vencimiento, cp.documento_ref, cp.monto_total, cp.saldo, cp.estado,
          p.nombre AS proveedor,
          IFNULL(SUM(pp.monto),0) AS pagado,
          COUNT(pp.idpago_pagar) AS num_pagos,
          DATEDIFF(cp.fecha_vencimiento, CURDATE()) AS dias
        FROM cuenta_pagar cp
        INNER JOIN persona p ON p.idpersona=cp.idproveedor
        LEFT JOIN pago_cuenta_pagar pp ON pp.idcuenta_pagar=cp.idcuenta_pagar
        WHERE 1=1 $cond
        GROUP BY cp.idcuenta_pagar, cp.fecha_emision, cp.fecha_vencimiento, cp.documento_ref, cp.monto_total, cp.saldo, cp.estado, p.nombre
        ORDER BY (cp.estado='PENDIENTE') DESC, cp.fecha_vencimiento ASC, cp.idcuenta_pagar DESC";
        return dbQuery($sql, $params);
    }

    public function obtenerCuentaCobrar($id)
    {
        return dbRow("SELECT * FROM cuenta_cobrar WHERE idcuenta_cobrar=?", array((int)$id));
    }

    public function obtenerCuentaPagar($id)
    {
        return dbRow("SELECT * FROM cuenta_pagar WHERE idcuenta_pagar=?", array((int)$id));
    }

    /** Historial de abonos de una cuenta por cobrar. */
    public function listarPagosCobrar($idcuenta)
    {
        $sql = "SELECT pc.idpago_cobrar, pc.fecha_hora, pc.monto, IFNULL(pc.medio_pago,'') AS medio_pago,
          IFNULL(pc.observacion,'') AS observacion, IFNULL(u.nombre,'-') AS usuario
        FROM pago_cuenta_cobrar pc
        LEFT JOIN usuario u ON u.idusuario=pc.idusuario
        WHERE pc.idcuenta_cobrar=?
        ORDER BY pc.fecha_hora DESC, pc.idpago_cobrar DESC";
        return dbAll($sql, array((int)$idcuenta));
    }

    /** Historial de pagos de una cuenta por pagar. */
    public function listarPagosPagar($idcuenta)
    {
        $sql = "SELECT pp.idpago_pagar, pp.fecha_hora, pp.monto, IFNULL(pp.medio_pago,'') AS medio_pago,
          IFNULL(pp.observacion,'') AS observacion, IFNULL(u.nombre,'-') AS usuario
        FROM pago_cuenta_pagar pp
        LEFT JOIN usuario u ON u.idusuario=pp.idusuario
        WHERE pp.idcuenta_pagar=?
        ORDER BY pp.fecha_hora DESC, pp.idpago_pagar DESC";
        return dbAll($sql, array((int)$idcuenta));
    }

    /** Totales globales de CxC / CxP. */
    public function resumen()
    {
        $sql = "SELECT
          (SELECT IFNULL(SUM(saldo),0) FROM cuenta_cobrar WHERE estado='PENDIENTE') AS cxc_pendiente,
          (SELECT IFNULL(SUM(saldo),0) FROM cuenta_cobrar WHERE estado='PENDIENTE' AND fecha_vencimiento<CURDATE()) AS cxc_vencido,
          (SELECT COUNT(*) FROM cuenta_cobrar WHERE estado='PENDIENTE' AND fecha_vencimiento<CURDATE()) AS cxc_vencidas_cantidad,
          (SELECT COUNT(*) FROM cuenta_cobrar WHERE estado='PENDIENTE') AS cxc_pendientes_cantidad,
          (SELECT IFNULL(SUM(saldo),0) FROM cuenta_pagar WHERE estado='PENDIENTE') AS cxp_pendiente,
          (SELECT IFNULL(SUM(saldo),0) FROM cuenta_pagar WHERE estado='PENDIENTE' AND fecha_vencimiento<CURDATE()) AS cxp_vencido,
          (SELECT COUNT(*) FROM cuenta_pagar WHERE estado='PENDIENTE' AND fecha_vencimiento<CURDATE()) AS cxp_vencidas_cantidad,
          (SELECT COUNT(*) FROM cuenta_pagar WHERE estado='PENDIENTE') AS cxp_pendientes_cantidad";
        $row = dbRow($sql);
        if (!$row) {
            $row = array(
                'cxc_pendiente' => 0, 'cxc_vencido' => 0, 'cxc_vencidas_cantidad' => 0, 'cxc_pendientes_cantidad' => 0,
                'cxp_pendiente' => 0, 'cxp_vencido' => 0, 'cxp_vencidas_cantidad' => 0, 'cxp_pendientes_cantidad' => 0
            );
        }
        foreach (array('cxc_pendiente', 'cxc_vencido', 'cxp_pendiente', 'cxp_vencido') as $k) {
            $row[$k] = round((float)$row[$k], 2);
        }
        foreach (array('cxc_vencidas_cantidad', 'cxc_pendientes_cantidad', 'cxp_vencidas_cantidad', 'cxp_pendientes_cantidad') as $k) {
            $row[$k] = (int)$row[$k];
        }
        $row['vencidas_cantidad'] = $row['cxc_vencidas_cantidad'] + $row['cxp_vencidas_cantidad'];
        return $row;
    }

    // ---------------------------------------------------------------
    // Abonos / pagos
    // ---------------------------------------------------------------

    /** Caja ABIERTA del usuario (id) o 0 si no tiene. */
    private function idCajaAbierta($idusuario)
    {
        return (int)dbValue(
            "SELECT idcaja FROM caja_diaria WHERE idusuario=? AND estado='ABIERTA' ORDER BY idcaja DESC LIMIT 1",
            array((int)$idusuario),
            0
        );
    }

    /**
     * Registra un abono a una cuenta por cobrar.
     * Devuelve array(ok, message, idpago, nuevo_saldo, estado).
     */
    public function registrarPagoCobrar($idcuenta, $idusuario, $monto, $medio_pago, $observacion)
    {
        $idcuenta = (int)$idcuenta;
        $idusuario = (int)$idusuario;
        $monto = round((float)$monto, 2);
        $medio_pago = self::medioPagoSeguro($medio_pago);
        $observacion = substr((string)$observacion, 0, 150);

        if ($monto <= 0) {
            return array('ok' => false, 'message' => 'El monto debe ser mayor a cero.');
        }

        $res = dbTransaccion(function ($cx) use ($idcuenta, $idusuario, $monto, $medio_pago, $observacion) {
            $cuenta = dbRow(
                "SELECT cc.*, p.nombre AS cliente FROM cuenta_cobrar cc
                 INNER JOIN persona p ON p.idpersona=cc.idcliente
                 WHERE cc.idcuenta_cobrar=? FOR UPDATE",
                array($idcuenta)
            );
            if (!$cuenta) {
                return array('ok' => false, 'message' => 'La cuenta no existe.');
            }
            if ($cuenta['estado'] !== 'PENDIENTE') {
                return array('ok' => false, 'message' => 'La cuenta no esta pendiente (estado ' . $cuenta['estado'] . ').');
            }
            $saldo = (float)$cuenta['saldo'];
            if ($monto > $saldo + Cuentas::TOLERANCIA) {
                return array('ok' => false, 'message' => 'El monto supera el saldo pendiente (' . number_format($saldo, 2) . ').');
            }

            $idpago = dbInsert(
                "INSERT INTO pago_cuenta_cobrar(idcuenta_cobrar, idusuario, monto, medio_pago, observacion) VALUES(?,?,?,?,?)",
                array($idcuenta, $idusuario, $monto, $medio_pago, $observacion)
            );
            if ($idpago <= 0) {
                return false;
            }

            $nuevoSaldo = round($saldo - $monto, 2);
            if ($nuevoSaldo <= Cuentas::TOLERANCIA) {
                $nuevoSaldo = 0.0;
            }
            $estado = $nuevoSaldo <= 0 ? 'PAGADO' : 'PENDIENTE';
            if (!dbExec("UPDATE cuenta_cobrar SET saldo=?, estado=? WHERE idcuenta_cobrar=?", array($nuevoSaldo, $estado, $idcuenta))) {
                return false;
            }

            // Reflejar en la caja abierta del usuario
            $idcaja = $this->idCajaAbierta($idusuario);
            if ($idcaja > 0) {
                $concepto = substr('Cobro cuenta #' . $idcuenta . ' ' . (string)$cuenta['cliente'], 0, 120);
                $ok = dbExec(
                    "INSERT INTO caja_movimiento(idcaja, idusuario, tipo, concepto, referencia, medio_pago, monto, fecha_hora)
                     VALUES(?,?,'INGRESO',?,?,?,?,NOW())",
                    array($idcaja, $idusuario, $concepto, 'CC-' . $idpago, $medio_pago, $monto)
                );
                if (!$ok) {
                    return false;
                }
            }

            return array('ok' => true, 'message' => 'Pago registrado correctamente', 'idpago' => $idpago, 'nuevo_saldo' => $nuevoSaldo, 'estado' => $estado, 'en_caja' => $idcaja > 0);
        });

        if ($res === false) {
            return array('ok' => false, 'message' => 'No se pudo registrar el pago.');
        }
        return $res;
    }

    /**
     * Registra un pago a una cuenta por pagar.
     * Devuelve array(ok, message, idpago, nuevo_saldo, estado).
     */
    public function registrarPagoPagar($idcuenta, $idusuario, $monto, $medio_pago, $observacion)
    {
        $idcuenta = (int)$idcuenta;
        $idusuario = (int)$idusuario;
        $monto = round((float)$monto, 2);
        $medio_pago = self::medioPagoSeguro($medio_pago);
        $observacion = substr((string)$observacion, 0, 150);

        if ($monto <= 0) {
            return array('ok' => false, 'message' => 'El monto debe ser mayor a cero.');
        }

        $res = dbTransaccion(function ($cx) use ($idcuenta, $idusuario, $monto, $medio_pago, $observacion) {
            $cuenta = dbRow(
                "SELECT cp.*, p.nombre AS proveedor FROM cuenta_pagar cp
                 INNER JOIN persona p ON p.idpersona=cp.idproveedor
                 WHERE cp.idcuenta_pagar=? FOR UPDATE",
                array($idcuenta)
            );
            if (!$cuenta) {
                return array('ok' => false, 'message' => 'La cuenta no existe.');
            }
            if ($cuenta['estado'] !== 'PENDIENTE') {
                return array('ok' => false, 'message' => 'La cuenta no esta pendiente (estado ' . $cuenta['estado'] . ').');
            }
            $saldo = (float)$cuenta['saldo'];
            if ($monto > $saldo + Cuentas::TOLERANCIA) {
                return array('ok' => false, 'message' => 'El monto supera el saldo pendiente (' . number_format($saldo, 2) . ').');
            }

            $idpago = dbInsert(
                "INSERT INTO pago_cuenta_pagar(idcuenta_pagar, idusuario, monto, medio_pago, observacion) VALUES(?,?,?,?,?)",
                array($idcuenta, $idusuario, $monto, $medio_pago, $observacion)
            );
            if ($idpago <= 0) {
                return false;
            }

            $nuevoSaldo = round($saldo - $monto, 2);
            if ($nuevoSaldo <= Cuentas::TOLERANCIA) {
                $nuevoSaldo = 0.0;
            }
            $estado = $nuevoSaldo <= 0 ? 'PAGADO' : 'PENDIENTE';
            if (!dbExec("UPDATE cuenta_pagar SET saldo=?, estado=? WHERE idcuenta_pagar=?", array($nuevoSaldo, $estado, $idcuenta))) {
                return false;
            }

            $idcaja = $this->idCajaAbierta($idusuario);
            if ($idcaja > 0) {
                $concepto = substr('Pago cuenta #' . $idcuenta . ' ' . (string)$cuenta['proveedor'], 0, 120);
                $ok = dbExec(
                    "INSERT INTO caja_movimiento(idcaja, idusuario, tipo, concepto, referencia, medio_pago, monto, fecha_hora)
                     VALUES(?,?,'EGRESO',?,?,?,?,NOW())",
                    array($idcaja, $idusuario, $concepto, 'CP-' . $idpago, $medio_pago, $monto)
                );
                if (!$ok) {
                    return false;
                }
            }

            return array('ok' => true, 'message' => 'Pago registrado correctamente', 'idpago' => $idpago, 'nuevo_saldo' => $nuevoSaldo, 'estado' => $estado, 'en_caja' => $idcaja > 0);
        });

        if ($res === false) {
            return array('ok' => false, 'message' => 'No se pudo registrar el pago.');
        }
        return $res;
    }

    // ---------------------------------------------------------------
    // Anulacion
    // ---------------------------------------------------------------

    /** Anula una cuenta por cobrar sin pagos. Devuelve array(ok, message). */
    public function anularCuentaCobrar($id)
    {
        $id = (int)$id;
        $res = dbTransaccion(function ($cx) use ($id) {
            $cuenta = dbRow("SELECT * FROM cuenta_cobrar WHERE idcuenta_cobrar=? FOR UPDATE", array($id));
            if (!$cuenta) {
                return array('ok' => false, 'message' => 'La cuenta no existe.');
            }
            if ($cuenta['estado'] === 'ANULADO') {
                return array('ok' => false, 'message' => 'La cuenta ya esta anulada.');
            }
            $pagos = (int)dbValue("SELECT COUNT(*) FROM pago_cuenta_cobrar WHERE idcuenta_cobrar=?", array($id), 0);
            if ($pagos > 0) {
                return array('ok' => false, 'message' => 'No se puede anular: la cuenta ya tiene abonos registrados.');
            }
            if (!dbExec("UPDATE cuenta_cobrar SET estado='ANULADO', saldo=0 WHERE idcuenta_cobrar=?", array($id))) {
                return false;
            }
            return array('ok' => true, 'message' => 'Cuenta anulada correctamente');
        });
        if ($res === false) {
            return array('ok' => false, 'message' => 'No se pudo anular la cuenta.');
        }
        return $res;
    }

    /** Anula una cuenta por pagar sin pagos. Devuelve array(ok, message). */
    public function anularCuentaPagar($id)
    {
        $id = (int)$id;
        $res = dbTransaccion(function ($cx) use ($id) {
            $cuenta = dbRow("SELECT * FROM cuenta_pagar WHERE idcuenta_pagar=? FOR UPDATE", array($id));
            if (!$cuenta) {
                return array('ok' => false, 'message' => 'La cuenta no existe.');
            }
            if ($cuenta['estado'] === 'ANULADO') {
                return array('ok' => false, 'message' => 'La cuenta ya esta anulada.');
            }
            $pagos = (int)dbValue("SELECT COUNT(*) FROM pago_cuenta_pagar WHERE idcuenta_pagar=?", array($id), 0);
            if ($pagos > 0) {
                return array('ok' => false, 'message' => 'No se puede anular: la cuenta ya tiene pagos registrados.');
            }
            if (!dbExec("UPDATE cuenta_pagar SET estado='ANULADO', saldo=0 WHERE idcuenta_pagar=?", array($id))) {
                return false;
            }
            return array('ok' => true, 'message' => 'Cuenta anulada correctamente');
        });
        if ($res === false) {
            return array('ok' => false, 'message' => 'No se pudo anular la cuenta.');
        }
        return $res;
    }
}
