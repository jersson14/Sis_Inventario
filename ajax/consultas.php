<?php 
require_once "../modelos/Consultas.php";

$consulta = new Consultas();

if (!function_exists('fechaSeguro')) {
    function fechaSeguro($valor, $fallback) {
        $valor = trim((string)$valor);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }
        return $fallback;
    }
}

switch ($_GET["op"]) {
	

    case 'comprasfecha':
    $fecha_inicio=$_REQUEST["fecha_inicio"];
    $fecha_fin=$_REQUEST["fecha_fin"];

		$rspta=$consulta->comprasfecha($fecha_inicio,$fecha_fin);
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$data[]=array(
            "0"=>$reg->fecha,
            "1"=>$reg->usuario,
            "2"=>$reg->proveedor,
            "3"=>$reg->tipo_comprobante,
            "4"=>$reg->serie_comprobante.' '.$reg->num_comprobante,
            "5"=>$reg->total_compra,
            "6"=>$reg->impuesto,
            "7"=>($reg->estado=='Aceptado')?'<span class="label bg-green">Aceptado</span>':'<span class="label bg-red">Anulado</span>'
              );
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data); 
		echo json_encode($results);
		break;

     case 'ventasfechacliente':
    $fecha_inicio=$_REQUEST["fecha_inicio"];
    $fecha_fin=$_REQUEST["fecha_fin"];
    $idcliente=$_REQUEST["idcliente"];

        $rspta=$consulta->ventasfechacliente($fecha_inicio,$fecha_fin,$idcliente);
        $data=Array();

        while ($reg=$rspta->fetch_object()) {
            $data[]=array(
            "0"=>$reg->fecha,
            "1"=>$reg->usuario,
            "2"=>$reg->cliente,
            "3"=>$reg->tipo_comprobante,
            "4"=>$reg->serie_comprobante.' '.$reg->num_comprobante,
            "5"=>$reg->total_venta,
            "6"=>$reg->impuesto,
            "7"=>($reg->estado=='Aceptado')?'<span class="label bg-green">Aceptado</span>':'<span class="label bg-red">Anulado</span>'
              );
        }
        $results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data); 
        echo json_encode($results);
        break;

    case 'utilidadperiodo':
        $fecha_inicio = fechaSeguro(isset($_REQUEST["fecha_inicio"]) ? $_REQUEST["fecha_inicio"] : '', date("Y-m-01"));
        $fecha_fin = fechaSeguro(isset($_REQUEST["fecha_fin"]) ? $_REQUEST["fecha_fin"] : '', date("Y-m-d"));

        $rspta = $consulta->utilidadPorPeriodo($fecha_inicio, $fecha_fin);
        $data = array();

        while ($reg = $rspta->fetch_object()) {
            $utilidad = (float)$reg->venta_total - (float)$reg->costo_estimado;
            $margen = ((float)$reg->venta_total > 0) ? ($utilidad / (float)$reg->venta_total) * 100 : 0;
            $data[] = array(
                "0" => $reg->codigo,
                "1" => $reg->articulo,
                "2" => $reg->categoria,
                "3" => number_format((float)$reg->cantidad_vendida, 3),
                "4" => number_format((float)$reg->venta_total, 2),
                "5" => number_format((float)$reg->costo_estimado, 2),
                "6" => number_format($utilidad, 2),
                "7" => number_format($margen, 2) . " %"
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;

    case 'topproductos':
        $fecha_inicio = fechaSeguro(isset($_REQUEST["fecha_inicio"]) ? $_REQUEST["fecha_inicio"] : '', date("Y-m-01"));
        $fecha_fin = fechaSeguro(isset($_REQUEST["fecha_fin"]) ? $_REQUEST["fecha_fin"] : '', date("Y-m-d"));
        $limite = isset($_REQUEST["limite"]) ? (int)$_REQUEST["limite"] : 20;
        $modo = isset($_REQUEST["modo"]) ? strtoupper(trim($_REQUEST["modo"])) : 'MAS';
        if (!in_array($modo, array('MAS', 'MENOS'), true)) {
            $modo = 'MAS';
        }
        if ($limite <= 0) $limite = 20;

        $rspta = $consulta->topProductosPeriodo($fecha_inicio, $fecha_fin, $limite, $modo);
        $data = array();
        $rank = 1;

        while ($reg = $rspta->fetch_object()) {
            $data[] = array(
                "0" => $rank++,
                "1" => $reg->codigo,
                "2" => $reg->articulo,
                "3" => $reg->categoria,
                "4" => number_format((float)$reg->cantidad, 3) . " " . $reg->unidad,
                "5" => number_format((float)$reg->total, 2)
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;

    case 'stockcritico':
        $rspta = $consulta->stockCritico();
        $data = array();

        while ($reg = $rspta->fetch_object()) {
            $badge = '<span class="label bg-green">OK</span>';
            if ($reg->alerta === 'AGOTADO') {
                $badge = '<span class="label bg-red">AGOTADO</span>';
            } elseif ($reg->alerta === 'BAJO MINIMO') {
                $badge = '<span class="label bg-yellow">BAJO MINIMO</span>';
            } elseif ($reg->alerta === 'PROXIMO A AGOTARSE') {
                $badge = '<span class="label bg-orange">PROXIMO A AGOTARSE</span>';
            } elseif ($reg->alerta === 'SIN MOVIMIENTO') {
                $badge = '<span class="label bg-aqua">SIN MOVIMIENTO</span>';
            }

            $data[] = array(
                "0" => $reg->codigo,
                "1" => $reg->articulo,
                "2" => $reg->categoria,
                "3" => number_format((float)$reg->stock, 3) . " " . $reg->unidad,
                "4" => number_format((float)$reg->stock_minimo, 3) . " " . $reg->unidad,
                "5" => $badge,
                "6" => $reg->ultimo_mov,
                "7" => (int)$reg->dias_sin_mov
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;

    case 'kardexvalorizado':
        $fecha_inicio = fechaSeguro(isset($_REQUEST["fecha_inicio"]) ? $_REQUEST["fecha_inicio"] : '', date("Y-m-01"));
        $fecha_fin = fechaSeguro(isset($_REQUEST["fecha_fin"]) ? $_REQUEST["fecha_fin"] : '', date("Y-m-d"));

        $rspta = $consulta->kardexValorizado($fecha_inicio, $fecha_fin);
        $data = array();

        while ($reg = $rspta->fetch_object()) {
            $data[] = array(
                "0" => $reg->codigo,
                "1" => $reg->articulo,
                "2" => number_format((float)$reg->entrada, 3) . " " . $reg->unidad,
                "3" => number_format((float)$reg->salida, 3) . " " . $reg->unidad,
                "4" => number_format((float)$reg->saldo, 3) . " " . $reg->unidad,
                "5" => number_format((float)$reg->costo_promedio, 2),
                "6" => number_format((float)$reg->valor_stock, 2)
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;

    case 'clientesproveedores':
        $fecha_inicio = fechaSeguro(isset($_REQUEST["fecha_inicio"]) ? $_REQUEST["fecha_inicio"] : '', date("Y-m-01"));
        $fecha_fin = fechaSeguro(isset($_REQUEST["fecha_fin"]) ? $_REQUEST["fecha_fin"] : '', date("Y-m-d"));
        $tipo = isset($_REQUEST["tipo"]) ? strtoupper(trim($_REQUEST["tipo"])) : 'TODOS';
        if (!in_array($tipo, array('TODOS', 'CLIENTE', 'PROVEEDOR'), true)) {
            $tipo = 'TODOS';
        }

        $rspta = $consulta->clientesProveedoresPeriodo($fecha_inicio, $fecha_fin, $tipo);
        $data = array();

        while ($reg = $rspta->fetch_object()) {
            $tipoBadge = ($reg->tipo === 'CLIENTE')
                ? '<span class="label bg-green">CLIENTE</span>'
                : '<span class="label bg-aqua">PROVEEDOR</span>';

            $data[] = array(
                "0" => $tipoBadge,
                "1" => $reg->persona,
                "2" => $reg->documento,
                "3" => $reg->telefono,
                "4" => (int)$reg->operaciones,
                "5" => number_format((float)$reg->total, 2),
                "6" => $reg->ultimo_mov
            );
        }

        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($data),
            "iTotalDisplayRecords" => count($data),
            "aaData" => $data
        );
        echo json_encode($results);
        break;
}
 ?>
