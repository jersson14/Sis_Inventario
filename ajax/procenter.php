<?php
require_once "../modelos/ProCenter.php";

$pro = new ProCenter();

switch ($_GET['op']) {
    case 'selectArticulo':
        $rspta = $pro->articulosActivos();
        while ($reg = $rspta->fetch_object()) {
            echo '<option value="' . $reg->idarticulo . '">' . $reg->nombre . ' (' . $reg->codigo . ')</option>';
        }
        break;

    case 'kardex':
        $idarticulo = isset($_GET['idarticulo']) ? limpiarCadena($_GET['idarticulo']) : '';
        $desde = isset($_GET['desde']) ? limpiarCadena($_GET['desde']) : '';
        $hasta = isset($_GET['hasta']) ? limpiarCadena($_GET['hasta']) : '';

        if ($idarticulo === '') {
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

        $saldoInicialGlobal = $stockActual - ($entradasTotal - $salidasTotal);
        $saldoInicialRango = $saldoInicialGlobal + ((float)$antes['entradas_antes'] - (float)$antes['salidas_antes']);

        $saldo = $saldoInicialRango;
        $data = array();
        while ($reg = $movs->fetch_object()) {
            $entrada = (float)$reg->entrada;
            $salida = (float)$reg->salida;
            $saldo += ($entrada - $salida);

            $data[] = array(
                'fecha' => date('d/m/Y H:i', strtotime($reg->fecha_hora)),
                'tipo' => $reg->tipo,
                'documento' => $reg->documento,
                'tercero' => $reg->tercero,
                'entrada' => number_format($entrada, 3),
                'salida' => number_format($salida, 3),
                'saldo' => number_format($saldo, 3),
                'costo' => number_format((float)$reg->costo, 2),
                'precio_ref' => number_format((float)$reg->precio_ref, 2)
            );
        }

        echo json_encode(array(
            'ok' => true,
            'articulo' => $info['nombre'],
            'codigo' => $info['codigo'],
            'unidad' => $info['unidad'],
            'stock_actual' => number_format($stockActual, 3),
            'stock_minimo' => number_format((float)$info['stock_minimo'], 3),
            'saldo_inicial' => number_format($saldoInicialRango, 3),
            'movimientos' => $data
        ));
        break;

    case 'alertaStock':
        $rspta = $pro->alertasStockMinimo();
        $data = array();
        while ($reg = $rspta->fetch_object()) {
            $data[] = array(
                '0' => $reg->codigo,
                '1' => $reg->nombre,
                '2' => number_format((float)$reg->stock, 3) . ' ' . $reg->unidad,
                '3' => number_format((float)$reg->stock_minimo, 3) . ' ' . $reg->unidad,
                '4' => number_format((float)$reg->faltante, 3) . ' ' . $reg->unidad
            );
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;

    case 'alertaSinMov':
        $dias = isset($_GET['dias']) ? limpiarCadena($_GET['dias']) : '30';
        $rspta = $pro->alertasSinMovimiento($dias);
        $data = array();

        while ($reg = $rspta->fetch_object()) {
            $ultimo = empty($reg->ultimo_mov) ? 'Sin movimientos' : date('d/m/Y', strtotime($reg->ultimo_mov));
            $data[] = array(
                '0' => $reg->codigo,
                '1' => $reg->nombre,
                '2' => number_format((float)$reg->stock, 3) . ' ' . $reg->unidad,
                '3' => $ultimo
            );
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;

    case 'topVendidos':
        $desde = isset($_GET['desde']) ? limpiarCadena($_GET['desde']) : '';
        $hasta = isset($_GET['hasta']) ? limpiarCadena($_GET['hasta']) : '';
        $rspta = $pro->topVendidos($desde, $hasta, 10);

        $data = array();
        while ($reg = $rspta->fetch_object()) {
            $data[] = array(
                '0' => $reg->codigo,
                '1' => $reg->nombre,
                '2' => number_format((float)$reg->cantidad, 3) . ' ' . $reg->unidad,
                '3' => 'S/ ' . number_format((float)$reg->total, 2)
            );
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;

    case 'utilidad':
        $desde = isset($_GET['desde']) ? limpiarCadena($_GET['desde']) : '';
        $hasta = isset($_GET['hasta']) ? limpiarCadena($_GET['hasta']) : '';
        $agrupar = isset($_GET['agrupar']) ? limpiarCadena($_GET['agrupar']) : 'producto';

        $rspta = $pro->utilidad($desde, $hasta, $agrupar);
        $data = array();

        while ($reg = $rspta->fetch_object()) {
            if ($agrupar === 'categoria') {
                $grupo = $reg->categoria;
                $detalle = '-';
            } elseif ($agrupar === 'vendedor') {
                $grupo = $reg->vendedor;
                $detalle = '-';
            } else {
                $grupo = $reg->producto;
                $detalle = $reg->categoria . ' / ' . $reg->vendedor;
            }

            $data[] = array(
                '0' => $grupo,
                '1' => $detalle,
                '2' => number_format((float)$reg->cantidad, 3),
                '3' => 'S/ ' . number_format((float)$reg->venta, 2),
                '4' => 'S/ ' . number_format((float)$reg->costo, 2),
                '5' => 'S/ ' . number_format((float)$reg->utilidad, 2)
            );
        }

        echo json_encode(array(
            'sEcho' => 1,
            'iTotalRecords' => count($data),
            'iTotalDisplayRecords' => count($data),
            'aaData' => $data
        ));
        break;

    case 'sugerencias':
        $diasAnalisis = isset($_GET['dias_analisis']) ? limpiarCadena($_GET['dias_analisis']) : '30';
        $diasCobertura = isset($_GET['dias_cobertura']) ? limpiarCadena($_GET['dias_cobertura']) : '15';

        $rspta = $pro->comprasSugeridas($diasAnalisis, $diasCobertura);
        $data = array();
        while ($reg = $rspta->fetch_object()) {
            $data[] = array(
                '0' => $reg->codigo,
                '1' => $reg->nombre,
                '2' => number_format((float)$reg->stock, 3) . ' ' . $reg->unidad,
                '3' => number_format((float)$reg->stock_minimo, 3) . ' ' . $reg->unidad,
                '4' => number_format((float)$reg->vendido_periodo, 3),
                '5' => number_format((float)$reg->promedio_diario, 3),
                '6' => number_format((float)$reg->stock_objetivo, 3),
                '7' => number_format((float)$reg->sugerido, 3)
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
