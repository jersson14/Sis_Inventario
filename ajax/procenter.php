<?php
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('procenter', 'almacen'));
require_once "../modelos/ProCenter.php";

$pro = new ProCenter();

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

// Fechas opcionales ('' = sin filtro)
$desde = fechaSegura(isset($_GET['desde']) ? $_GET['desde'] : '', '');
$hasta = fechaSegura(isset($_GET['hasta']) ? $_GET['hasta'] : '', '');
if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    $tmp = $desde;
    $desde = $hasta;
    $hasta = $tmp;
}

switch ($op) {
    case 'selectArticulo':
        $rspta = $pro->articulosActivos();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                echo '<option value="' . (int)$reg->idarticulo . '">' . e($reg->nombre) . ' (' . e($reg->codigo) . ')</option>';
            }
        }
        break;

    case 'kardex':
        $idarticulo = enteroSeguro(isset($_GET['idarticulo']) ? $_GET['idarticulo'] : 0);
        if ($idarticulo <= 0) {
            echo json_encode(array('ok' => false, 'message' => 'Selecciona un articulo.'));
            break;
        }

        $info = $pro->infoArticulo($idarticulo);
        if (!$info) {
            echo json_encode(array('ok' => false, 'message' => 'Articulo no encontrado.'));
            break;
        }

        $totales = $pro->kardexTotales($idarticulo);
        $antes = $pro->kardexAntesDeFecha($idarticulo, $desde);
        $movs = $pro->kardexMovimientos($idarticulo, $desde, $hasta);

        $entradasTotal = (float)$totales['entradas_total'];
        $salidasTotal = (float)$totales['salidas_total'];
        $stockActual = (float)$info['stock'];

        // Saldo inicial global = stock actual - (todo lo que entro - todo lo que salio)
        $saldoInicialGlobal = $stockActual - ($entradasTotal - $salidasTotal);
        $saldoInicialRango = $saldoInicialGlobal + ((float)$antes['entradas_antes'] - (float)$antes['salidas_antes']);

        $saldo = $saldoInicialRango;
        $data = array();
        if ($movs) {
            while ($reg = $movs->fetch_object()) {
                $entrada = (float)$reg->entrada;
                $salida = (float)$reg->salida;
                $saldo += ($entrada - $salida);

                $data[] = array(
                    'fecha' => date('d/m/Y H:i', strtotime($reg->fecha_hora)),
                    'fecha_orden' => date('Y-m-d H:i:s', strtotime($reg->fecha_hora)),
                    'tipo' => $reg->tipo,
                    'documento' => $reg->documento,
                    'tercero' => $reg->tercero,
                    'entrada' => formatearCantidad($entrada),
                    'salida' => formatearCantidad($salida),
                    'saldo' => formatearCantidad($saldo),
                    'costo' => (float)$reg->costo,
                    'precio_ref' => (float)$reg->precio_ref
                );
            }
        }

        echo json_encode(array(
            'ok' => true,
            'articulo' => $info['nombre'],
            'codigo' => $info['codigo'],
            'unidad' => $info['unidad'],
            'stock_actual' => formatearCantidad($stockActual),
            'stock_minimo' => formatearCantidad($info['stock_minimo']),
            'saldo_inicial' => formatearCantidad($saldoInicialRango),
            'movimientos' => $data
        ), JSON_UNESCAPED_UNICODE);
        break;

    case 'alertaStock':
        $rspta = $pro->alertasStockMinimo();
        $data = array();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                $data[] = array(
                    '0' => e($reg->codigo),
                    '1' => e($reg->nombre),
                    '2' => formatearCantidad($reg->stock) . ' ' . e($reg->unidad),
                    '3' => formatearCantidad($reg->stock_minimo) . ' ' . e($reg->unidad),
                    '4' => formatearCantidad($reg->faltante) . ' ' . e($reg->unidad)
                );
            }
        }
        respuestaDataTable($data);
        break;

    case 'alertaSinMov':
        $dias = enteroSeguro(isset($_GET['dias']) ? $_GET['dias'] : 30, 1, 30);
        if ($dias > 3650) {
            $dias = 3650;
        }
        $rspta = $pro->alertasSinMovimiento($dias);
        $data = array();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                $ultimo = empty($reg->ultimo_mov) ? 'Sin movimientos' : date('d/m/Y', strtotime($reg->ultimo_mov));
                $data[] = array(
                    '0' => e($reg->codigo),
                    '1' => e($reg->nombre),
                    '2' => formatearCantidad($reg->stock) . ' ' . e($reg->unidad),
                    '3' => $ultimo
                );
            }
        }
        respuestaDataTable($data);
        break;

    case 'topVendidos':
        $limite = enteroSeguro(isset($_GET['limite']) ? $_GET['limite'] : 10, 1, 10);
        if ($limite > 100) {
            $limite = 100;
        }
        $rspta = $pro->topVendidos($desde, $hasta, $limite);
        $data = array();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                $data[] = array(
                    '0' => e($reg->codigo),
                    '1' => e($reg->nombre),
                    '2' => formatearCantidad($reg->cantidad) . ' ' . e($reg->unidad),
                    '3' => formatearMoneda((float)$reg->total)
                );
            }
        }
        respuestaDataTable($data);
        break;

    case 'utilidad':
        $agrupar = isset($_GET['agrupar']) ? strtolower(trim((string)$_GET['agrupar'])) : 'producto';
        if (!in_array($agrupar, array('producto', 'categoria', 'vendedor'), true)) {
            $agrupar = 'producto';
        }

        $rspta = $pro->utilidad($desde, $hasta, $agrupar);
        $data = array();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                if ($agrupar === 'categoria') {
                    $grupo = e($reg->categoria);
                    $detalle = '-';
                } elseif ($agrupar === 'vendedor') {
                    $grupo = e($reg->vendedor);
                    $detalle = '-';
                } else {
                    $grupo = e($reg->producto);
                    $detalle = e($reg->categoria) . ' / ' . e($reg->vendedor);
                }

                $data[] = array(
                    '0' => $grupo,
                    '1' => $detalle,
                    '2' => formatearCantidad($reg->cantidad),
                    '3' => formatearMoneda((float)$reg->venta),
                    '4' => formatearMoneda((float)$reg->costo),
                    '5' => formatearMoneda((float)$reg->utilidad)
                );
            }
        }
        respuestaDataTable($data);
        break;

    case 'sugerencias':
        $diasAnalisis = enteroSeguro(isset($_GET['dias_analisis']) ? $_GET['dias_analisis'] : 30, 1, 30);
        $diasCobertura = enteroSeguro(isset($_GET['dias_cobertura']) ? $_GET['dias_cobertura'] : 15, 1, 15);
        if ($diasAnalisis > 3650) {
            $diasAnalisis = 3650;
        }
        if ($diasCobertura > 3650) {
            $diasCobertura = 3650;
        }

        $rspta = $pro->comprasSugeridas($diasAnalisis, $diasCobertura);
        $data = array();
        if ($rspta) {
            while ($reg = $rspta->fetch_object()) {
                $data[] = array(
                    '0' => e($reg->codigo),
                    '1' => e($reg->nombre),
                    '2' => formatearCantidad($reg->stock) . ' ' . e($reg->unidad),
                    '3' => formatearCantidad($reg->stock_minimo) . ' ' . e($reg->unidad),
                    '4' => formatearCantidad($reg->vendido_periodo),
                    '5' => number_format((float)$reg->promedio_diario, 2),
                    '6' => formatearCantidad($reg->stock_objetivo),
                    '7' => formatearCantidad($reg->sugerido)
                );
            }
        }
        respuestaDataTable($data);
        break;

    default:
        responderJson(array('ok' => false, 'message' => 'Operacion no valida.'), 400);
        break;
}
