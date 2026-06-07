<?php
if (strlen(session_id()) < 1) {
    session_start();
}
require_once "../modelos/Caja.php";

$caja = new Caja();
$idusuario = $_SESSION['idusuario'];

switch ($_GET['op']) {
    case 'estado':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        if (!$abierta) {
            echo json_encode(array('abierta' => false));
            break;
        }
        $res = $caja->resumenCaja($abierta['idcaja']);
        echo json_encode(array(
            'abierta' => true,
            'idcaja' => $abierta['idcaja'],
            'fecha_apertura' => $abierta['fecha_apertura'],
            'monto_apertura' => number_format((float)$abierta['monto_apertura'], 2),
            'ingresos' => number_format((float)$res['total_ingresos'], 2),
            'egresos' => number_format((float)$res['total_egresos'], 2),
            'sistema' => number_format(((float)$abierta['monto_apertura'] + (float)$res['total_ingresos'] - (float)$res['total_egresos']), 2)
        ));
        break;

    case 'abrir':
        $monto_apertura = limpiarCadena($_POST['monto_apertura']);
        $observacion = limpiarCadena($_POST['observacion']);
        $rspta = $caja->abrirCaja($idusuario, $monto_apertura, $observacion);
        echo $rspta ? 'Caja abierta correctamente' : 'Ya tienes una caja abierta';
        break;

    case 'movimiento':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        if (!$abierta) {
            echo 'No hay caja abierta';
            break;
        }
        $tipo = limpiarCadena($_POST['tipo']);
        $concepto = limpiarCadena($_POST['concepto']);
        $monto = limpiarCadena($_POST['monto']);
        $rspta = $caja->agregarMovimiento($abierta['idcaja'], $idusuario, $tipo, $concepto, $monto);
        echo $rspta ? 'Movimiento registrado' : 'No se pudo registrar el movimiento';
        break;

    case 'cerrar':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        if (!$abierta) {
            echo 'No hay caja abierta';
            break;
        }
        $monto_cierre_real = limpiarCadena($_POST['monto_cierre_real']);
        $observacion = limpiarCadena($_POST['observacion']);
        $rspta = $caja->cerrarCaja($abierta['idcaja'], $monto_cierre_real, $observacion);
        echo $rspta ? 'Caja cerrada correctamente' : 'No se pudo cerrar la caja';
        break;

    case 'listarMovimientos':
        $abierta = $caja->cajaAbiertaUsuario($idusuario);
        $data = array();
        if ($abierta) {
            $rspta = $caja->movimientosCaja($abierta['idcaja']);
            while ($reg = $rspta->fetch_object()) {
                $data[] = array(
                    '0' => date('d/m/Y H:i', strtotime($reg->fecha_hora)),
                    '1' => $reg->tipo,
                    '2' => $reg->concepto,
                    '3' => formatearMoneda((float)$reg->monto),
                    '4' => $reg->usuario
                );
            }
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;

    case 'historial':
        $rspta = $caja->historialCajas($idusuario);
        $data = array();
        while ($reg = $rspta->fetch_object()) {
            $estado = $reg->estado === 'ABIERTA' ? '<span class="label bg-green">ABIERTA</span>' : '<span class="label bg-aqua">CERRADA</span>';
            $data[] = array(
                '0' => $reg->idcaja,
                '1' => date('d/m/Y H:i', strtotime($reg->fecha_apertura)),
                '2' => empty($reg->fecha_cierre) ? '-' : date('d/m/Y H:i', strtotime($reg->fecha_cierre)),
                '3' => formatearMoneda((float)$reg->monto_apertura),
                '4' => formatearMoneda((float)$reg->ingresos),
                '5' => formatearMoneda((float)$reg->egresos),
                '6' => formatearMoneda((float)$reg->monto_cierre_sistema),
                '7' => formatearMoneda((float)$reg->monto_cierre_real),
                '8' => formatearMoneda((float)$reg->diferencia),
                '9' => $estado
            );
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;
}
?>
