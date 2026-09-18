<?php
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('caja', 'ventas'));
require_once "../modelos/Caja.php";

$caja = new Caja();
$idusuario = (int)$_SESSION['idusuario'];
$esAdmin = usuarioTienePermiso('acceso');
// Arqueo ciego: quien no es administrador no recibe totales, efectivo
// esperado ni diferencia de su caja; cuenta el cajon sin saber cuanto "debe" haber
$arqueoCiego = !$esAdmin && Caja::arqueoCiegoConfigurado();
$DATOS_ARQUEO = array('ingresos', 'egresos', 'sistema', 'otros_medios', 'medios', 'total_ingresos', 'total_egresos',
    'efectivo_ingresos', 'efectivo_egresos', 'monto_cierre_sistema', 'diferencia');

/** Contrato DataTables. */
function respuestaDataTable(array $data)
{
    echo json_encode(array(
        'sEcho' => 1,
        'iTotalRecords' => count($data),
        'iTotalDisplayRecords' => count($data),
        'aaData' => $data
    ), JSON_UNESCAPED_UNICODE);
}

$op = isset($_GET['op']) ? (string)$_GET['op'] : '';

switch ($op) {
    case 'estado':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        if (!$abierta) {
            responderJson(array('abierta' => false, 'usuario' => isset($_SESSION['nombre']) ? $_SESSION['nombre'] : ''));
        }
        $res = $caja->resumenCaja($abierta['idcaja']);
        $ingresos = $res ? (float)$res['total_ingresos'] : 0.0;
        $egresos = $res ? (float)$res['total_egresos'] : 0.0;
        $estado = array(
            'abierta' => true,
            'arqueo_ciego' => $arqueoCiego,
            'idcaja' => (int)$abierta['idcaja'],
            'usuario' => $res ? $res['usuario'] : (isset($_SESSION['nombre']) ? $_SESSION['nombre'] : ''),
            'fecha_apertura' => $abierta['fecha_apertura'],
            'monto_apertura' => number_format((float)$abierta['monto_apertura'], 2, '.', ''),
            'ingresos' => number_format($ingresos, 2, '.', ''),
            'egresos' => number_format($egresos, 2, '.', ''),
            // Efectivo esperado en el cajon: solo movimientos en EFECTIVO
            'sistema' => number_format($res ? Caja::efectivoEsperado($res) : (float)$abierta['monto_apertura'], 2, '.', ''),
            // Yape, tarjeta, transferencias y depositos: neto que no pasa por el cajon
            'otros_medios' => number_format($res ? round($ingresos - $egresos - (float)$res['efectivo_ingresos'] + (float)$res['efectivo_egresos'], 2) : 0, 2, '.', ''),
            'num_movimientos' => $res ? (int)$res['num_movimientos'] : 0,
            'medios' => $caja->resumenPorMedioPago($abierta['idcaja'])
        );
        if ($arqueoCiego) {
            foreach ($DATOS_ARQUEO as $k) { unset($estado[$k]); }
        }
        responderJson($estado);
        break;

    case 'abrir':
        $monto_apertura = decimalSeguro(isset($_POST['monto_apertura']) ? $_POST['monto_apertura'] : 0);
        $observacion = limpiarCadena(isset($_POST['observacion']) ? $_POST['observacion'] : '');
        $res = $caja->abrirCaja($idusuario, $monto_apertura, $observacion);
        if (!empty($res['ok'])) {
            registrarAuditoria('caja', 'abrir', 'Caja #' . $res['idcaja'] . ' apertura ' . number_format($monto_apertura, 2));
        }
        echo $res['message'];
        break;

    case 'movimiento':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        if (!$abierta) {
            echo 'No hay caja abierta';
            break;
        }
        $tipo = strtoupper(trim((string)(isset($_POST['tipo']) ? $_POST['tipo'] : '')));
        $concepto = limpiarCadena(isset($_POST['concepto']) ? $_POST['concepto'] : '');
        $monto = decimalSeguro(isset($_POST['monto']) ? $_POST['monto'] : 0);
        $medio_pago = Caja::medioPagoSeguro(isset($_POST['medio_pago']) ? $_POST['medio_pago'] : 'EFECTIVO');
        $referencia = limpiarCadena(isset($_POST['referencia']) ? $_POST['referencia'] : '');

        $res = $caja->agregarMovimiento((int)$abierta['idcaja'], $idusuario, $tipo, $concepto, $monto, $medio_pago, $referencia);
        if (!empty($res['ok'])) {
            registrarAuditoria('caja', 'movimiento', 'Caja #' . (int)$abierta['idcaja'] . ' ' . $tipo . ' ' . number_format($monto, 2) . ' ' . $medio_pago . ' - ' . $concepto);
        }
        echo $res['message'];
        break;

    case 'cerrar':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        if (!$abierta) {
            echo 'No hay caja abierta';
            break;
        }
        $monto_cierre_real = decimalSeguro(isset($_POST['monto_cierre_real']) ? $_POST['monto_cierre_real'] : 0);
        $observacion = limpiarCadena(isset($_POST['observacion']) ? $_POST['observacion'] : '');
        $res = $caja->cerrarCaja((int)$abierta['idcaja'], $monto_cierre_real, $observacion);
        if (!empty($res['ok'])) {
            registrarAuditoria('caja', 'cerrar', 'Caja #' . (int)$abierta['idcaja'] . ' sistema ' . number_format((float)$res['sistema'], 2) . ' real ' . number_format((float)$res['real'], 2) . ' dif ' . number_format((float)$res['diferencia'], 2));
        }
        // Con arqueo ciego el resultado del cuadre no se le muestra a quien conto
        echo ($arqueoCiego && !empty($res['ok'])) ? 'Caja cerrada correctamente. Tu conteo quedó registrado; el administrador revisa el cuadre.' : $res['message'];
        break;

    case 'listarMovimientos':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        $data = array();
        if ($abierta) {
            $rspta = $caja->movimientosCaja($abierta['idcaja']);
            if ($rspta) {
                while ($reg = $rspta->fetch_object()) {
                    $tipoBadge = ($reg->tipo === 'INGRESO')
                        ? '<span class="label bg-green">INGRESO</span>'
                        : '<span class="label bg-red">EGRESO</span>';
                    $data[] = array(
                        '0' => date('d/m/Y H:i', strtotime($reg->fecha_hora)),
                        '1' => $tipoBadge,
                        '2' => e($reg->concepto),
                        '3' => formatearMoneda((float)$reg->monto),
                        '4' => e($reg->usuario),
                        '5' => e($reg->medio_pago),
                        '6' => e($reg->referencia)
                    );
                }
            }
        }
        respuestaDataTable($data);
        break;

    case 'historial':
        $todos = $esAdmin && isset($_GET['todos']) && (string)$_GET['todos'] === '1';
        $rspta = $caja->historialCajas($idusuario, $todos);
        $data = array();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                $id = (int)$reg->idcaja;
                $estado = $reg->estado === 'ABIERTA' ? '<span class="label bg-green">ABIERTA</span>' : '<span class="label bg-aqua">CERRADA</span>';
                $data[] = array(
                    '0' => $id,
                    '1' => date('d/m/Y H:i', strtotime($reg->fecha_apertura)),
                    '2' => empty($reg->fecha_cierre) ? '-' : date('d/m/Y H:i', strtotime($reg->fecha_cierre)),
                    '3' => formatearMoneda((float)$reg->monto_apertura),
                    '4' => $arqueoCiego ? '—' : formatearMoneda((float)$reg->ingresos),
                    '5' => $arqueoCiego ? '—' : formatearMoneda((float)$reg->egresos),
                    '6' => ($arqueoCiego || $reg->monto_cierre_sistema === null) ? '-' : formatearMoneda((float)$reg->monto_cierre_sistema),
                    '7' => $reg->monto_cierre_real === null ? '-' : formatearMoneda((float)$reg->monto_cierre_real),
                    '8' => ($arqueoCiego || $reg->diferencia === null) ? '-' : formatearMoneda((float)$reg->diferencia),
                    '9' => $estado,
                    '10' => e($reg->usuario),
                    '11' => '<button class="btn btn-default btn-xs" title="Ver arqueo" onclick="verDetalleCaja(' . $id . ')"><i class="fa fa-eye"></i></button>'
                );
            }
        }
        respuestaDataTable($data);
        break;

    case 'detalle':
        $idcaja = enteroSeguro(isset($_GET['idcaja']) ? $_GET['idcaja'] : 0);
        $det = $caja->detalleCaja($idcaja, $idusuario, $esAdmin);
        if (!$det) {
            responderJson(array('ok' => false, 'message' => 'Caja no encontrada o sin acceso.'), 404);
        }
        $det['ok'] = true;
        $det['arqueo_ciego'] = $arqueoCiego;
        if ($arqueoCiego) {
            foreach ($DATOS_ARQUEO as $k) { unset($det[$k]); }
        }
        responderJson($det);
        break;

    default:
        responderJson(array('ok' => false, 'message' => 'Operacion no valida.'), 400);
        break;
}
