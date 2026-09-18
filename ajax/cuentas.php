<?php
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('cuentas'));   // cobrar y pagar deudas: permiso propio (el vendedor no lo tiene)
require_once "../modelos/Cuentas.php";

$cuentas = new Cuentas();
$idusuario = (int)$_SESSION['idusuario'];

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

/** Badge de estado de una cuenta (VENCIDA si esta pendiente y ya vencio). */
function badgeEstadoCuenta($estado, $dias)
{
    if ($estado === 'PAGADO') {
        return '<span class="label bg-green">PAGADO</span>';
    }
    if ($estado === 'ANULADO') {
        return '<span class="label bg-gray">ANULADO</span>';
    }
    if ((int)$dias < 0) {
        return '<span class="label bg-red">VENCIDA</span>';
    }
    return '<span class="label bg-yellow">PENDIENTE</span>';
}

/** Texto de dias para vencer. */
function textoDiasCuenta($estado, $dias)
{
    if ($estado !== 'PENDIENTE') {
        return '-';
    }
    $dias = (int)$dias;
    if ($dias < 0) {
        return 'Vencida hace ' . abs($dias) . ' d';
    }
    if ($dias === 0) {
        return 'Vence hoy';
    }
    return 'Vence en ' . $dias . ' d';
}

/** Normaliza el filtro de estado de los listados. */
function estadoFiltro()
{
    $estado = isset($_GET['estado']) ? strtoupper(trim((string)$_GET['estado'])) : 'TODOS';
    if (!in_array($estado, array('PENDIENTE', 'PAGADO', 'VENCIDA', 'ANULADO', 'TODOS'), true)) {
        $estado = 'TODOS';
    }
    return $estado;
}

/** Valida los datos comunes de una cuenta nueva. Devuelve '' si todo esta bien. */
function validarDatosCuenta($fecha_emision, $fecha_vencimiento, $monto_total)
{
    if ($fecha_emision === '' || $fecha_vencimiento === '') {
        return 'Las fechas de emision y vencimiento deben ser validas (YYYY-MM-DD).';
    }
    if ($fecha_vencimiento < $fecha_emision) {
        return 'La fecha de vencimiento no puede ser anterior a la de emision.';
    }
    if ($monto_total <= 0) {
        return 'El monto debe ser mayor a cero.';
    }
    return '';
}

$op = isset($_GET['op']) ? (string)$_GET['op'] : '';

switch ($op) {
    case 'selectCliente':
        $rspta = $cuentas->listarClientes();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                echo '<option value="' . (int)$reg->idpersona . '">' . e($reg->nombre) . '</option>';
            }
        }
        break;

    case 'selectProveedor':
        $rspta = $cuentas->listarProveedores();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                echo '<option value="' . (int)$reg->idpersona . '">' . e($reg->nombre) . '</option>';
            }
        }
        break;

    // ---------------------------------------------------------------
    // Cuentas por cobrar
    // ---------------------------------------------------------------

    case 'guardarCobrar':
        requierePermiso(array('cuentas', 'ventas'));
        $idcliente = enteroSeguro(isset($_POST['idcliente']) ? $_POST['idcliente'] : 0);
        $fecha_emision = fechaSegura(isset($_POST['fecha_emision']) ? $_POST['fecha_emision'] : '', '');
        $fecha_vencimiento = fechaSegura(isset($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : '', '');
        $documento_ref = substr(limpiarCadena(isset($_POST['documento_ref']) ? $_POST['documento_ref'] : ''), 0, 40);
        $monto_total = decimalSeguro(isset($_POST['monto_total']) ? $_POST['monto_total'] : 0);
        $observacion = substr(limpiarCadena(isset($_POST['observacion']) ? $_POST['observacion'] : ''), 0, 200);

        $cliente = $cuentas->personaActiva($idcliente, 'Cliente');
        if (!$cliente) {
            echo 'Selecciona un cliente activo.';
            break;
        }
        $err = validarDatosCuenta($fecha_emision, $fecha_vencimiento, $monto_total);
        if ($err !== '') {
            echo $err;
            break;
        }

        $id = $cuentas->insertarCuentaCobrar($idcliente, $fecha_emision, $fecha_vencimiento, $documento_ref, $monto_total, $observacion);
        if ($id > 0) {
            registrarAuditoria('cuentas', 'crear_cxc', 'CxC #' . $id . ' cliente ' . $cliente['nombre'] . ' monto ' . number_format($monto_total, 2));
            echo 'Cuenta por cobrar registrada';
        } else {
            echo 'No se pudo registrar la cuenta por cobrar';
        }
        break;

    case 'listarCobrar':
        $estado = estadoFiltro();
        $rspta = $cuentas->listarCuentasCobrar($estado);
        $data = array();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                $id = (int)$reg->idcuenta_cobrar;
                $btn = '';
                if ($reg->estado === 'PENDIENTE') {
                    $btn .= '<button class="btn btn-success btn-xs" title="Abonar" onclick="abrirPagoCobrar(' . $id . ',' . round((float)$reg->saldo, 2) . ')"><i class="fa fa-money"></i> Abonar</button> ';
                }
                $btn .= '<button class="btn btn-default btn-xs" title="Historial de abonos" onclick="verPagosCobrar(' . $id . ')"><i class="fa fa-list"></i></button> ';
                if ($reg->estado === 'PENDIENTE' && (int)$reg->num_pagos === 0) {
                    $btn .= '<button class="btn btn-danger btn-xs" title="Anular" onclick="anularCobrar(' . $id . ')"><i class="fa fa-ban"></i></button>';
                }
                $data[] = array(
                    '0' => trim($btn),
                    '1' => e($reg->fecha_emision),
                    '2' => e($reg->fecha_vencimiento),
                    '3' => e($reg->cliente),
                    '4' => e($reg->documento_ref),
                    '5' => formatearMoneda((float)$reg->monto_total),
                    '6' => formatearMoneda((float)$reg->pagado),
                    '7' => formatearMoneda((float)$reg->saldo),
                    '8' => badgeEstadoCuenta($reg->estado, $reg->dias),
                    '9' => textoDiasCuenta($reg->estado, $reg->dias),
                    '10' => $id
                );
            }
        }
        respuestaDataTable($data);
        break;

    case 'abonarCobrar':
        requierePermiso(array('cuentas', 'ventas'));
        $idcuenta = enteroSeguro(isset($_POST['idcuenta']) ? $_POST['idcuenta'] : 0);
        $monto = decimalSeguro(isset($_POST['monto']) ? $_POST['monto'] : 0);
        $medio_pago = Cuentas::medioPagoSeguro(isset($_POST['medio_pago']) ? $_POST['medio_pago'] : 'EFECTIVO');
        $observacion = limpiarCadena(isset($_POST['observacion']) ? $_POST['observacion'] : '');

        $res = $cuentas->registrarPagoCobrar($idcuenta, $idusuario, $monto, $medio_pago, $observacion);
        if (!empty($res['ok'])) {
            registrarAuditoria('cuentas', 'abono_cxc', 'CxC #' . $idcuenta . ' abono ' . number_format($monto, 2) . ' ' . $medio_pago . ' saldo ' . number_format((float)$res['nuevo_saldo'], 2));
        }
        echo $res['message'];
        break;

    case 'anularCobrar':
        requierePermiso(array('cuentas', 'ventas'));
        $idcuenta = enteroSeguro(isset($_POST['idcuenta']) ? $_POST['idcuenta'] : 0);
        $res = $cuentas->anularCuentaCobrar($idcuenta);
        if (!empty($res['ok'])) {
            registrarAuditoria('cuentas', 'anular_cxc', 'CxC #' . $idcuenta . ' anulada');
        }
        echo $res['message'];
        break;

    case 'pagosCobrar':
        $idcuenta = enteroSeguro(isset($_GET['idcuenta']) ? $_GET['idcuenta'] : 0);
        $lista = array();
        foreach ($cuentas->listarPagosCobrar($idcuenta) as $p) {
            $lista[] = array(
                'idpago' => (int)$p['idpago_cobrar'],
                'fecha' => date('d/m/Y H:i', strtotime($p['fecha_hora'])),
                'monto' => round((float)$p['monto'], 2),
                'monto_txt' => formatearMoneda((float)$p['monto']),
                'medio_pago' => $p['medio_pago'],
                'observacion' => $p['observacion'],
                'usuario' => $p['usuario']
            );
        }
        responderJson($lista);
        break;

    // ---------------------------------------------------------------
    // Cuentas por pagar
    // ---------------------------------------------------------------

    case 'guardarPagar':
        requierePermiso(array('cuentas', 'compras'));
        $idproveedor = enteroSeguro(isset($_POST['idproveedor']) ? $_POST['idproveedor'] : 0);
        $fecha_emision = fechaSegura(isset($_POST['fecha_emision']) ? $_POST['fecha_emision'] : '', '');
        $fecha_vencimiento = fechaSegura(isset($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : '', '');
        $documento_ref = substr(limpiarCadena(isset($_POST['documento_ref']) ? $_POST['documento_ref'] : ''), 0, 40);
        $monto_total = decimalSeguro(isset($_POST['monto_total']) ? $_POST['monto_total'] : 0);
        $observacion = substr(limpiarCadena(isset($_POST['observacion']) ? $_POST['observacion'] : ''), 0, 200);

        $proveedor = $cuentas->personaActiva($idproveedor, 'Proveedor');
        if (!$proveedor) {
            echo 'Selecciona un proveedor activo.';
            break;
        }
        $err = validarDatosCuenta($fecha_emision, $fecha_vencimiento, $monto_total);
        if ($err !== '') {
            echo $err;
            break;
        }

        $id = $cuentas->insertarCuentaPagar($idproveedor, $fecha_emision, $fecha_vencimiento, $documento_ref, $monto_total, $observacion);
        if ($id > 0) {
            registrarAuditoria('cuentas', 'crear_cxp', 'CxP #' . $id . ' proveedor ' . $proveedor['nombre'] . ' monto ' . number_format($monto_total, 2));
            echo 'Cuenta por pagar registrada';
        } else {
            echo 'No se pudo registrar la cuenta por pagar';
        }
        break;

    case 'listarPagar':
        $estado = estadoFiltro();
        $rspta = $cuentas->listarCuentasPagar($estado);
        $data = array();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                $id = (int)$reg->idcuenta_pagar;
                $btn = '';
                if ($reg->estado === 'PENDIENTE') {
                    $btn .= '<button class="btn btn-info btn-xs" title="Pagar" onclick="abrirPagoPagar(' . $id . ',' . round((float)$reg->saldo, 2) . ')"><i class="fa fa-money"></i> Pagar</button> ';
                }
                $btn .= '<button class="btn btn-default btn-xs" title="Historial de pagos" onclick="verPagosPagar(' . $id . ')"><i class="fa fa-list"></i></button> ';
                if ($reg->estado === 'PENDIENTE' && (int)$reg->num_pagos === 0) {
                    $btn .= '<button class="btn btn-danger btn-xs" title="Anular" onclick="anularPagar(' . $id . ')"><i class="fa fa-ban"></i></button>';
                }
                $data[] = array(
                    '0' => trim($btn),
                    '1' => e($reg->fecha_emision),
                    '2' => e($reg->fecha_vencimiento),
                    '3' => e($reg->proveedor),
                    '4' => e($reg->documento_ref),
                    '5' => formatearMoneda((float)$reg->monto_total),
                    '6' => formatearMoneda((float)$reg->pagado),
                    '7' => formatearMoneda((float)$reg->saldo),
                    '8' => badgeEstadoCuenta($reg->estado, $reg->dias),
                    '9' => textoDiasCuenta($reg->estado, $reg->dias),
                    '10' => $id
                );
            }
        }
        respuestaDataTable($data);
        break;

    case 'abonarPagar':
        requierePermiso(array('cuentas', 'compras'));
        $idcuenta = enteroSeguro(isset($_POST['idcuenta']) ? $_POST['idcuenta'] : 0);
        $monto = decimalSeguro(isset($_POST['monto']) ? $_POST['monto'] : 0);
        $medio_pago = Cuentas::medioPagoSeguro(isset($_POST['medio_pago']) ? $_POST['medio_pago'] : 'EFECTIVO');
        $observacion = limpiarCadena(isset($_POST['observacion']) ? $_POST['observacion'] : '');

        $res = $cuentas->registrarPagoPagar($idcuenta, $idusuario, $monto, $medio_pago, $observacion);
        if (!empty($res['ok'])) {
            registrarAuditoria('cuentas', 'pago_cxp', 'CxP #' . $idcuenta . ' pago ' . number_format($monto, 2) . ' ' . $medio_pago . ' saldo ' . number_format((float)$res['nuevo_saldo'], 2));
        }
        echo $res['message'];
        break;

    case 'anularPagar':
        requierePermiso(array('cuentas', 'compras'));
        $idcuenta = enteroSeguro(isset($_POST['idcuenta']) ? $_POST['idcuenta'] : 0);
        $res = $cuentas->anularCuentaPagar($idcuenta);
        if (!empty($res['ok'])) {
            registrarAuditoria('cuentas', 'anular_cxp', 'CxP #' . $idcuenta . ' anulada');
        }
        echo $res['message'];
        break;

    case 'pagosPagar':
        $idcuenta = enteroSeguro(isset($_GET['idcuenta']) ? $_GET['idcuenta'] : 0);
        $lista = array();
        foreach ($cuentas->listarPagosPagar($idcuenta) as $p) {
            $lista[] = array(
                'idpago' => (int)$p['idpago_pagar'],
                'fecha' => date('d/m/Y H:i', strtotime($p['fecha_hora'])),
                'monto' => round((float)$p['monto'], 2),
                'monto_txt' => formatearMoneda((float)$p['monto']),
                'medio_pago' => $p['medio_pago'],
                'observacion' => $p['observacion'],
                'usuario' => $p['usuario']
            );
        }
        responderJson($lista);
        break;

    // ---------------------------------------------------------------
    // Resumen
    // ---------------------------------------------------------------

    case 'resumen':
        $r = $cuentas->resumen();
        $r['cxc_pendiente_txt'] = formatearMoneda($r['cxc_pendiente']);
        $r['cxc_vencido_txt'] = formatearMoneda($r['cxc_vencido']);
        $r['cxp_pendiente_txt'] = formatearMoneda($r['cxp_pendiente']);
        $r['cxp_vencido_txt'] = formatearMoneda($r['cxp_vencido']);
        responderJson($r);
        break;

    default:
        responderJson(array('ok' => false, 'message' => 'Operacion no valida.'), 400);
        break;
}
