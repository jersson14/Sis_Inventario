<?php 
require_once "../modelos/Venta.php";
if (strlen(session_id())<1) 
	session_start();

$venta = new Venta();

$idventa=isset($_POST["idventa"])? limpiarCadena($_POST["idventa"]):"";
$idcliente=isset($_POST["idcliente"])? limpiarCadena($_POST["idcliente"]):"";
$idusuario=$_SESSION["idusuario"];
$tipo_comprobante=isset($_POST["tipo_comprobante"])? limpiarCadena($_POST["tipo_comprobante"]):"";
$serie_comprobante=isset($_POST["serie_comprobante"])? limpiarCadena($_POST["serie_comprobante"]):"";
$num_comprobante=isset($_POST["num_comprobante"])? limpiarCadena($_POST["num_comprobante"]):"";
$fecha_hora=isset($_POST["fecha_hora"])? limpiarCadena($_POST["fecha_hora"]):"";
$impuesto=isset($_POST["impuesto"])? limpiarCadena($_POST["impuesto"]):"";
$total_venta=isset($_POST["total_venta"])? limpiarCadena($_POST["total_venta"]):"";





switch ($_GET["op"]) {
	case 'guardaryeditar':
	if (empty($idventa)) {
		$rspta=$venta->insertar($idcliente,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$total_venta,$_POST["idarticulo"],$_POST["cantidad"],$_POST["precio_venta"],$_POST["descuento"]); 
		if (is_array($rspta)) {
			if (!empty($rspta["ok"])) {
				echo json_encode(array(
					"ok"=>true,
					"message"=>"Datos registrados correctamente",
					"alertas"=>isset($rspta["alertas"])?$rspta["alertas"]:array()
				));
			}else{
				echo json_encode(array(
					"ok"=>false,
					"message"=>isset($rspta["message"])?$rspta["message"]:"No se pudo registrar los datos"
				));
			}
		}else{
			echo json_encode(array(
				"ok"=>(bool)$rspta,
				"message"=>$rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos",
				"alertas"=>array()
			));
		}
	}else{
        
	}
		break;
	

	case 'anular':
		$rspta=$venta->anular($idventa);
		echo $rspta ? "Ingreso anulado correctamente" : "No se pudo anular el ingreso";
		break;
	
	case 'mostrar':
		$rspta=$venta->mostrar($idventa);
		echo json_encode($rspta);
		break;

	case 'listarDetalle':
		//recibimos el idventa
		$id=$_GET['id'];

		$rspta=$venta->listarDetalle($id);
		$total=0;
		echo ' <thead style="background-color:#A9D0F5">
        <th>Opciones</th>
        <th>Articulo</th>
        <th>Unidad</th>
        <th>Cantidad</th>
        <th>Precio Venta</th>
        <th>Descuento</th>
        <th>Subtotal</th>
        <th>Actualizar</th>
       </thead>';
		while ($reg=$rspta->fetch_object()) {
			echo '<tr class="filas">
			<td></td>
			<td>'.$reg->nombre.'</td>
			<td>'.$reg->unidad.'</td>
			<td>'.$reg->cantidad.'</td>
			<td>'.$reg->precio_venta.'</td>
			<td>'.$reg->descuento.'</td>
			<td>'.$reg->subtotal.'</td>
			<td></td></tr>';
			$total=$total+($reg->precio_venta*$reg->cantidad-$reg->descuento);
		}
		echo '<tfoot>
         <th>TOTAL</th>
         <th></th>
         <th></th>
         <th></th>
         <th></th>
         <th></th>
         <th></th>
         <th><h4 id="total">'.formatearMoneda($total).'</h4><input type="hidden" name="total_venta" id="total_venta"></th>
       </tfoot>';
		break;

    case 'listar':
		$rspta=$venta->listar();
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
                 if ($reg->tipo_comprobante=='Ticket') {
                 	$url='../reportes/exTicket.php?id=';
                 }else{
                    $url='../reportes/exFactura.php?id=';
                 }

			$data[]=array(
            "0"=>(($reg->estado=='Aceptado')?'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idventa.')"><i class="fa fa-eye"></i></button>'.' '.'<button class="btn btn-danger btn-xs" onclick="anular('.$reg->idventa.')"><i class="fa fa-close"></i></button>':'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idventa.')"><i class="fa fa-eye"></i></button>').
            '<a target="_blank" href="'.$url.$reg->idventa.'"> <button class="btn btn-info btn-xs"><i class="fa fa-file"></i></button></a>',
            "1"=>$reg->fecha,
            "2"=>$reg->cliente,
            "3"=>$reg->usuario,
            "4"=>$reg->tipo_comprobante,
            "5"=>$reg->serie_comprobante. '-' .$reg->num_comprobante,
            "6"=>formatearMoneda((float)$reg->total_venta),
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

		case 'selectCliente':
			require_once "../modelos/Persona.php";
			$persona = new Persona();

			$rspta = $persona->listarc();

			while ($reg = $rspta->fetch_object()) {
				echo '<option value='.$reg->idpersona.'>'.$reg->nombre.'</option>';
			}
			break;

			case 'listarArticulos':
			require_once "../modelos/Articulo.php";
			$articulo=new Articulo();

				$rspta=$articulo->listarActivosVenta();
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$nombrejs = addslashes($reg->nombre);
			$unidadjs = addslashes($reg->abreviatura);
			$precio = is_null($reg->precio_venta) ? 0 : (float)$reg->precio_venta;
			$stock = (float)$reg->stock;
			$stockFmt = number_format($stock,3);
			$stockMinimo = isset($reg->stock_minimo) ? (float)$reg->stock_minimo : 0;
			$umbralBajo = max($stockMinimo, 5);

			if ($stock<=0) {
				$btnAgregar='<button class="btn btn-add-item btn-add-disabled" type="button" disabled title="Sin stock"><i class="fa fa-ban"></i> Sin stock</button>';
				$stockHtml='<span class="stock-pill stock-empty">'.$stockFmt.'</span>';
			} elseif ($stock<=$umbralBajo) {
				$btnAgregar='<button class="btn btn-add-item" type="button" onclick="agregarDetalle('.$reg->idarticulo.',\''.$nombrejs.'\','.$precio.',\''.$unidadjs.'\')"><i class="fa fa-plus-circle"></i> Agregar</button>';
				$stockHtml='<span class="stock-pill stock-low">'.$stockFmt.'</span>';
			} else {
				$btnAgregar='<button class="btn btn-add-item" type="button" onclick="agregarDetalle('.$reg->idarticulo.',\''.$nombrejs.'\','.$precio.',\''.$unidadjs.'\')"><i class="fa fa-plus-circle"></i> Agregar</button>';
				$stockHtml='<span class="stock-pill stock-ok">'.$stockFmt.'</span>';
			}

			$imagen = !empty($reg->imagen) ? $reg->imagen : 'default-50x50.gif';
			$imgHtml = "<img class='catalog-thumb' src='../files/articulos/".$imagen."' onerror=\"this.src='../public/img/default-50x50.gif';\" alt='articulo'>";
			$data[]=array(
            "0"=>$btnAgregar,
            "1"=>$reg->nombre,
            "2"=>$reg->categoria,
            "3"=>$reg->abreviatura,
            "4"=>$reg->codigo,
            "5"=>$stockHtml,
            "6"=>formatearMoneda($precio),
            "7"=>$imgHtml
          
              );
		}
		$results=array(
             "sEcho"=>1,//info para datatables
             "iTotalRecords"=>count($data),//enviamos el total de registros al datatable
             "iTotalDisplayRecords"=>count($data),//enviamos el total de registros a visualizar
             "aaData"=>$data); 
		echo json_encode($results);

				break;

		case 'buscarArticuloCodigo':
			$codigo = isset($_POST['codigo']) ? limpiarCadena($_POST['codigo']) : '';
			require_once "../modelos/Articulo.php";
			$articulo=new Articulo();
			$reg = $articulo->buscarActivoPorCodigo($codigo);
			if (!$reg) {
				echo json_encode(array("ok"=>false,"message"=>"No se encontro un articulo con ese codigo"));
				break;
			}
			if ((float)$reg['stock'] <= 0) {
				echo json_encode(array("ok"=>false,"message"=>"El articulo no tiene stock disponible"));
				break;
			}
			echo json_encode(array(
				"ok"=>true,
				"idarticulo"=>$reg['idarticulo'],
				"nombre"=>$reg['nombre'],
				"precio_venta"=>(float)$reg['precio_venta'],
				"unidad"=>$reg['abreviatura']
			));
			break;
}
 ?>
