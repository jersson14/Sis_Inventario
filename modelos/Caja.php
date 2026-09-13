<?php
/**
 * Modelo de caja diaria y sus movimientos.
 *
 * Consultas preparadas. Los metodos de escritura devuelven
 * array('ok'=>bool, 'message'=>string, ...).
 */
require_once "../config/Conexion.php";

class Caja
{
    const TIPOS = array('INGRESO', 'EGRESO');
    const MEDIOS_PAGO = array('EFECTIVO', 'TARJETA', 'TRANSFERENCIA', 'YAPE', 'PLIN', 'OTRO');

    public function __construct()
    {
    }

    public static function medioPagoSeguro($medio)
    {
        $medio = strtoupper(trim((string)$medio));
        return in_array($medio, self::MEDIOS_PAGO, true) ? $medio : 'EFECTIVO';
    }

    /** Caja ABIERTA del usuario o null. */
    public function cajaAbiertaUsuario($idusuario)
    {
        return dbRow(
            "SELECT * FROM caja_diaria WHERE idusuario=? AND estado='ABIERTA' ORDER BY idcaja DESC LIMIT 1",
            array((int)$idusuario)
        );
    }

    /** Abre una caja para el usuario. Devuelve array(ok, message, idcaja). */
    public function abrirCaja($idusuario, $monto_apertura, $observacion)
    {
        $idusuario = (int)$idusuario;
        $monto_apertura = round((float)$monto_apertura, 2);
        $observacion = substr((string)$observacion, 0, 200);

        if ($monto_apertura < 0) {
            return array('ok' => false, 'message' => 'El monto de apertura no puede ser negativo.');
        }
        if ($this->cajaAbiertaUsuario($idusuario)) {
            return array('ok' => false, 'message' => 'Ya tienes una caja abierta');
        }

        $id = dbInsert(
            "INSERT INTO caja_diaria(idusuario, fecha_apertura, monto_apertura, estado, observacion) VALUES(?,NOW(),?,'ABIERTA',?)",
            array($idusuario, $monto_apertura, $observacion)
        );
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'No se pudo abrir la caja.');
        }
        return array('ok' => true, 'message' => 'Caja abierta correctamente', 'idcaja' => $id);
    }

    /** Registra un movimiento manual. Devuelve array(ok, message, idmovimiento). */
    public function agregarMovimiento($idcaja, $idusuario, $tipo, $concepto, $monto, $medio_pago = 'EFECTIVO', $referencia = '')
    {
        $tipo = strtoupper(trim((string)$tipo));
        if (!in_array($tipo, self::TIPOS, true)) {
            return array('ok' => false, 'message' => 'Tipo de movimiento no valido (INGRESO o EGRESO).');
        }
        $monto = round((float)$monto, 2);
        if ($monto <= 0) {
            return array('ok' => false, 'message' => 'El monto debe ser mayor a cero.');
        }
        $concepto = trim((string)$concepto);
        if ($concepto === '') {
            return array('ok' => false, 'message' => 'El concepto es obligatorio.');
        }
        $concepto = substr($concepto, 0, 120);
        $medio_pago = self::medioPagoSeguro($medio_pago);
        $referencia = substr(trim((string)$referencia), 0, 40);

        $caja = dbRow("SELECT idcaja, estado FROM caja_diaria WHERE idcaja=?", array((int)$idcaja));
        if (!$caja || $caja['estado'] !== 'ABIERTA') {
            return array('ok' => false, 'message' => 'La caja no esta abierta.');
        }

        if ($referencia === '') {
            $sql = "INSERT INTO caja_movimiento(idcaja, idusuario, tipo, concepto, referencia, medio_pago, monto, fecha_hora)
                VALUES(?,?,?,?,NULL,?,?,NOW())";
            $params = array((int)$idcaja, (int)$idusuario, $tipo, $concepto, $medio_pago, $monto);
        } else {
            $sql = "INSERT INTO caja_movimiento(idcaja, idusuario, tipo, concepto, referencia, medio_pago, monto, fecha_hora)
                VALUES(?,?,?,?,?,?,?,NOW())";
            $params = array((int)$idcaja, (int)$idusuario, $tipo, $concepto, $referencia, $medio_pago, $monto);
        }
        $id = dbInsert($sql, $params);
        if ($id <= 0) {
            return array('ok' => false, 'message' => 'No se pudo registrar el movimiento');
        }
        return array('ok' => true, 'message' => 'Movimiento registrado', 'idmovimiento' => $id);
    }

    /** Cabecera de la caja con totales de ingresos/egresos. */
    public function resumenCaja($idcaja)
    {
        $sql = "SELECT c.idcaja, c.idusuario, c.fecha_apertura, c.fecha_cierre, c.monto_apertura, c.monto_cierre_sistema,
          c.monto_cierre_real, c.diferencia, c.estado, c.observacion, u.nombre AS usuario,
          IFNULL(SUM(CASE WHEN m.tipo='INGRESO' THEN m.monto ELSE 0 END),0) AS total_ingresos,
          IFNULL(SUM(CASE WHEN m.tipo='EGRESO' THEN m.monto ELSE 0 END),0) AS total_egresos,
          COUNT(m.idmovimiento) AS num_movimientos
        FROM caja_diaria c
        INNER JOIN usuario u ON u.idusuario=c.idusuario
        LEFT JOIN caja_movimiento m ON m.idcaja=c.idcaja
        WHERE c.idcaja=?
        GROUP BY c.idcaja, c.idusuario, c.fecha_apertura, c.fecha_cierre, c.monto_apertura, c.monto_cierre_sistema,
          c.monto_cierre_real, c.diferencia, c.estado, c.observacion, u.nombre";
        return dbRow($sql, array((int)$idcaja));
    }

    /** Ingresos/egresos agrupados por medio de pago. */
    public function resumenPorMedioPago($idcaja)
    {
        $sql = "SELECT m.medio_pago,
          IFNULL(SUM(CASE WHEN m.tipo='INGRESO' THEN m.monto ELSE 0 END),0) AS ingresos,
          IFNULL(SUM(CASE WHEN m.tipo='EGRESO' THEN m.monto ELSE 0 END),0) AS egresos
        FROM caja_movimiento m
        WHERE m.idcaja=?
        GROUP BY m.medio_pago
        ORDER BY ingresos DESC, m.medio_pago ASC";
        $rows = dbAll($sql, array((int)$idcaja));
        foreach ($rows as &$r) {
            $r['ingresos'] = round((float)$r['ingresos'], 2);
            $r['egresos'] = round((float)$r['egresos'], 2);
            $r['neto'] = round($r['ingresos'] - $r['egresos'], 2);
        }
        unset($r);
        return $rows;
    }

    /** Cierra la caja. Devuelve array(ok, message, sistema, real, diferencia). */
    public function cerrarCaja($idcaja, $monto_cierre_real, $observacion)
    {
        $monto_cierre_real = round((float)$monto_cierre_real, 2);
        if ($monto_cierre_real < 0) {
            return array('ok' => false, 'message' => 'El monto de cierre no puede ser negativo.');
        }
        $observacion = substr(trim((string)$observacion), 0, 120);

        $resumen = $this->resumenCaja($idcaja);
        if (!$resumen || $resumen['estado'] !== 'ABIERTA') {
            return array('ok' => false, 'message' => 'La caja no esta abierta.');
        }

        $cierreSistema = round((float)$resumen['monto_apertura'] + (float)$resumen['total_ingresos'] - (float)$resumen['total_egresos'], 2);
        $diferencia = round($monto_cierre_real - $cierreSistema, 2);

        $ok = dbExec(
            "UPDATE caja_diaria SET
              fecha_cierre=NOW(),
              monto_cierre_sistema=?,
              monto_cierre_real=?,
              diferencia=?,
              estado='CERRADA',
              observacion=LEFT(CONCAT(IFNULL(observacion,''), ' | Cierre: ', ?), 200)
             WHERE idcaja=? AND estado='ABIERTA'",
            array($cierreSistema, $monto_cierre_real, $diferencia, $observacion, (int)$idcaja)
        );
        if (!$ok || dbAfectadas() < 1) {
            return array('ok' => false, 'message' => 'No se pudo cerrar la caja');
        }
        return array('ok' => true, 'message' => 'Caja cerrada correctamente', 'sistema' => $cierreSistema, 'real' => $monto_cierre_real, 'diferencia' => $diferencia);
    }

    public function movimientosCaja($idcaja)
    {
        $sql = "SELECT m.idmovimiento, m.fecha_hora, m.tipo, m.concepto, IFNULL(m.referencia,'') AS referencia,
          m.medio_pago, m.monto, u.nombre AS usuario
        FROM caja_movimiento m
        INNER JOIN usuario u ON u.idusuario=m.idusuario
        WHERE m.idcaja=?
        ORDER BY m.idmovimiento DESC";
        return dbQuery($sql, array((int)$idcaja));
    }

    /**
     * Historial de cajas. Si $todos es true (admin) devuelve todas las cajas
     * con el nombre del usuario; si no, solo las del usuario.
     */
    public function historialCajas($idusuario, $todos = false)
    {
        $where = $todos ? "1=1" : "c.idusuario=?";
        $params = $todos ? array() : array((int)$idusuario);
        $sql = "SELECT c.idcaja, c.idusuario, u.nombre AS usuario, c.fecha_apertura, c.fecha_cierre, c.monto_apertura,
          c.monto_cierre_sistema, c.monto_cierre_real, c.diferencia, c.estado,
          IFNULL(SUM(CASE WHEN m.tipo='INGRESO' THEN m.monto ELSE 0 END),0) AS ingresos,
          IFNULL(SUM(CASE WHEN m.tipo='EGRESO' THEN m.monto ELSE 0 END),0) AS egresos
        FROM caja_diaria c
        INNER JOIN usuario u ON u.idusuario=c.idusuario
        LEFT JOIN caja_movimiento m ON m.idcaja=c.idcaja
        WHERE $where
        GROUP BY c.idcaja, c.idusuario, u.nombre, c.fecha_apertura, c.fecha_cierre, c.monto_apertura,
          c.monto_cierre_sistema, c.monto_cierre_real, c.diferencia, c.estado
        ORDER BY c.idcaja DESC";
        return dbQuery($sql, $params);
    }

    /**
     * Detalle completo de una caja (cabecera + medios + movimientos) para imprimir.
     * Solo si la caja pertenece al usuario o si $esAdmin. Devuelve array o null.
     */
    public function detalleCaja($idcaja, $idusuario, $esAdmin = false)
    {
        $cab = $this->resumenCaja($idcaja);
        if (!$cab) {
            return null;
        }
        if (!$esAdmin && (int)$cab['idusuario'] !== (int)$idusuario) {
            return null;
        }

        $movs = array();
        $rs = $this->movimientosCaja($idcaja);
        if ($rs) {
            while ($m = $rs->fetch_assoc()) {
                $m['monto'] = round((float)$m['monto'], 2);
                $movs[] = $m;
            }
        }

        $sistema = round((float)$cab['monto_apertura'] + (float)$cab['total_ingresos'] - (float)$cab['total_egresos'], 2);

        return array(
            'idcaja' => (int)$cab['idcaja'],
            'idusuario' => (int)$cab['idusuario'],
            'usuario' => $cab['usuario'],
            'estado' => $cab['estado'],
            'fecha_apertura' => $cab['fecha_apertura'],
            'fecha_cierre' => $cab['fecha_cierre'],
            'monto_apertura' => round((float)$cab['monto_apertura'], 2),
            'total_ingresos' => round((float)$cab['total_ingresos'], 2),
            'total_egresos' => round((float)$cab['total_egresos'], 2),
            'sistema' => $sistema,
            'monto_cierre_sistema' => $cab['monto_cierre_sistema'] === null ? null : round((float)$cab['monto_cierre_sistema'], 2),
            'monto_cierre_real' => $cab['monto_cierre_real'] === null ? null : round((float)$cab['monto_cierre_real'], 2),
            'diferencia' => $cab['diferencia'] === null ? null : round((float)$cab['diferencia'], 2),
            'observacion' => (string)$cab['observacion'],
            'num_movimientos' => (int)$cab['num_movimientos'],
            'medios' => $this->resumenPorMedioPago($idcaja),
            'movimientos' => $movs
        );
    }
}
