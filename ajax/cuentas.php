<?php
if (strlen(session_id()) < 1) {
    session_start();
}
require_once "../modelos/Cuentas.php";

$cuentas = new Cuentas();

switch ($_GET['op']) {
    case 'selectCliente':
        $rspta = $cuentas->listarClientes();
        while ($reg = $rspta->fetch_object()) {
            echo '<option value="' . $reg->idpersona . '">' . $reg->nombre . '</option>';
        }
        break;

    case 'selectProveedor':
        $rspta = $cuentas->listarProveedores();
        while ($reg = $rspta->fetch_object()) {
            echo '<option value="' . $reg->idpersona . '">' . $reg->nombre . '</option>';
        }
        break;

    case 'guardarCobrar':
        $idcliente = limpiarCadena($_POST['idcliente']);
        $fecha_emision = limpiarCadena($_POST['fecha_emision']);
        $fecha_vencimiento = limpiarCadena($_POST['fecha_vencimiento']);
        $documento_ref = limpiarCadena($_POST['documento_ref']);
        $monto_total = limpiarCadena($_POST['monto_total']);
        $observacion = limpiarCadena($_POST['observacion']);

        $rspta = $cuentas->insertarCuentaCobrar($idcliente, $fecha_emision, $fecha_vencimiento, $documento_ref, $monto_total, $observacion);
        echo $rspta ? 'Cuenta por cobrar registrada' : 'No se pudo registrar la cuenta por cobrar';
        break;

    case 'guardarPagar':
        $idproveedor = limpiarCadena($_POST['idproveedor']);
        $fecha_emision = limpiarCadena($_POST['fecha_emision']);
        $fecha_vencimiento = limpiarCadena($_POST['fecha_vencimiento']);
        $documento_ref = limpiarCadena($_POST['documento_ref']);
        $monto_total = limpiarCadena($_POST['monto_total']);
        $observacion = limpiarCadena($_POST['observacion']);

        $rspta = $cuentas->insertarCuentaPagar($idproveedor, $fecha_emision, $fecha_vencimiento, $documento_ref, $monto_total, $observacion);
        echo $rspta ? 'Cuenta por pagar registrada' : 'No se pudo registrar la cuenta por pagar';
        break;

    case 'listarCobrar':
        $rspta = $cuentas->listarCuentasCobrar();
        $data = array();
        while ($reg = $rspta->fetch_object()) {
            $vencida = ($reg->estado !== 'PAGADO' && strtotime($reg->fecha_vencimiento) < strtotime(date('Y-m-d')));
            $badge = $reg->estado === 'PAGADO' ? '<span class="label bg-green">PAGADO</span>' : ($vencida ? '<span class="label bg-red">VENCIDA</span>' : '<span class="label bg-yellow">PENDIENTE</span>');
            $btn = $reg->estado === 'PAGADO' ? '-' : '<button class="btn btn-success btn-xs" onclick="abrirPagoCobrar(' . $reg->idcuenta_cobrar . ',' . $reg->saldo . ')"><i class="fa fa-money"></i> Abonar</button>';
            $data[] = array(
                '0' => $btn,
                '1' => $reg->fecha_emision,
                '2' => $reg->fecha_vencimiento,
                '3' => $reg->cliente,
                '4' => $reg->documento_ref,
                '5' => formatearMoneda((float)$reg->monto_total),
                '6' => formatearMoneda((float)$reg->pagado),
                '7' => formatearMoneda((float)$reg->saldo),
                '8' => $badge
            );
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;

    case 'listarPagar':
        $rspta = $cuentas->listarCuentasPagar();
        $data = array();
        while ($reg = $rspta->fetch_object()) {
            $vencida = ($reg->estado !== 'PAGADO' && strtotime($reg->fecha_vencimiento) < strtotime(date('Y-m-d')));
            $badge = $reg->estado === 'PAGADO' ? '<span class="label bg-green">PAGADO</span>' : ($vencida ? '<span class="label bg-red">VENCIDA</span>' : '<span class="label bg-yellow">PENDIENTE</span>');
            $btn = $reg->estado === 'PAGADO' ? '-' : '<button class="btn btn-info btn-xs" onclick="abrirPagoPagar(' . $reg->idcuenta_pagar . ',' . $reg->saldo . ')"><i class="fa fa-money"></i> Pagar</button>';
            $data[] = array(
                '0' => $btn,
                '1' => $reg->fecha_emision,
                '2' => $reg->fecha_vencimiento,
                '3' => $reg->proveedor,
                '4' => $reg->documento_ref,
                '5' => formatearMoneda((float)$reg->monto_total),
                '6' => formatearMoneda((float)$reg->pagado),
                '7' => formatearMoneda((float)$reg->saldo),
                '8' => $badge
            );
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;

    case 'abonarCobrar':
        $idcuenta = limpiarCadena($_POST['idcuenta']);
        $monto = limpiarCadena($_POST['monto']);
        $medio_pago = limpiarCadena($_POST['medio_pago']);
        $observacion = limpiarCadena($_POST['observacion']);
        $idusuario = $_SESSION['idusuario'];

        $rspta = $cuentas->registrarPagoCobrar($idcuenta, $idusuario, $monto, $medio_pago, $observacion);
        echo $rspta ? 'Pago registrado correctamente' : 'No se pudo registrar el pago (verifica el monto)';
        break;

    case 'abonarPagar':
        $idcuenta = limpiarCadena($_POST['idcuenta']);
        $monto = limpiarCadena($_POST['monto']);
        $medio_pago = limpiarCadena($_POST['medio_pago']);
        $observacion = limpiarCadena($_POST['observacion']);
        $idusuario = $_SESSION['idusuario'];

        $rspta = $cuentas->registrarPagoPagar($idcuenta, $idusuario, $monto, $medio_pago, $observacion);
        echo $rspta ? 'Pago registrado correctamente' : 'No se pudo registrar el pago (verifica el monto)';
        break;
}
?>
