<?php 
require_once "../modelos/Ingreso.php";
if (strlen(session_id())<1) 
	session_start();

$ingreso=new Ingreso();

$idingreso=isset($_POST["idingreso"])? limpiarCadena($_POST["idingreso"]):"";
$idproveedor=isset($_POST["idproveedor"])? limpiarCadena($_POST["idproveedor"]):"";
$idusuario=$_SESSION["idusuario"];
$tipo_comprobante=isset($_POST["tipo_comprobante"])? limpiarCadena($_POST["tipo_comprobante"]):"";
$serie_comprobante=isset($_POST["serie_comprobante"])? limpiarCadena($_POST["serie_comprobante"]):"";
$num_comprobante=isset($_POST["num_comprobante"])? limpiarCadena($_POST["num_comprobante"]):"";
$fecha_hora=isset($_POST["fecha_hora"])? limpiarCadena($_POST["fecha_hora"]):"";
$impuesto=isset($_POST["impuesto"])? limpiarCadena($_POST["impuesto"]):"";
$total_compra=isset($_POST["total_compra"])? limpiarCadena($_POST["total_compra"]):"";

if (!function_exists('fechaFiltroSeguroIngreso')) {
	function fechaFiltroSeguroIngreso($valor) {
		$valor = trim((string)$valor);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
			return $valor;
		}
		return '';
	}
}


switch ($_GET["op"]) {
	case 'guardaryeditar':
	if (empty($idingreso)) {
		$rspta=$ingreso->insertar($idproveedor,$idusuario,$tipo_comprobante,$serie_comprobante,$num_comprobante,$fecha_hora,$impuesto,$total_compra,$_POST["idarticulo"],$_POST["cantidad"],$_POST["precio_compra"],$_POST["precio_venta"]);
		if (is_array($rspta)) {
			if (!empty($rspta["ok"])) {
				echo json_encode(array(
					"ok"=>true,
					"message"=>"Datos registrados correctamente",
					"serie_comprobante"=>isset($rspta["serie_comprobante"])?$rspta["serie_comprobante"]:"",
					"num_comprobante"=>isset($rspta["num_comprobante"])?$rspta["num_comprobante"]:""
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
				"message"=>$rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos"
			));
		}
	}else{
		echo json_encode(array(
			"ok"=>false,
			"message"=>"Edicion de ingreso no disponible desde este formulario"
		));
	}
		break;

	case 'siguienteCorrelativo':
		$tipo = isset($_GET["tipo_comprobante"]) ? limpiarCadena($_GET["tipo_comprobante"]) : "Boleta";
		$serie = isset($_GET["serie_comprobante"]) ? limpiarCadena($_GET["serie_comprobante"]) : "";
		$rspta = $ingreso->obtenerSiguienteCorrelativo($tipo, $serie);
		echo json_encode($rspta);
		break;
	

	case 'anular':
		$rspta=$ingreso->anular($idingreso);
		echo $rspta ? "Ingreso anulado correctamente" : "No se pudo anular el ingreso";
		break;
	
	case 'mostrar':
		$rspta=$ingreso->mostrar($idingreso);
		echo json_encode($rspta);
		break;

	case 'listarDetalle':
		//recibimos el idingreso
		$id=$_GET['id'];

		$rspta=$ingreso->listarDetalle($id);
		$total=0;
		echo ' <thead style="background-color:#A9D0F5">
        <th>Opciones</th>
        <th>Articulo</th>
        <th>Unidad</th>
        <th>Cantidad</th>
        <th>Precio Compra</th>
        <th>Precio Venta</th>
        <th>Subtotal</th>
        <th>Actualizar</th>
       </thead>';
		while ($reg=$rspta->fetch_object()) {
			$subtotal = (float)$reg->precio_compra * (float)$reg->cantidad;
			echo '<tr class="filas">
			<td></td>
			<td>'.$reg->nombre.'</td>
			<td>'.$reg->unidad.'</td>
			<td>'.number_format((float)$reg->cantidad,0).'</td>
			<td>'.number_format((float)$reg->precio_compra,2).'</td>
			<td>'.number_format((float)$reg->precio_venta,2).'</td>
			<td>'.number_format($subtotal,2).'</td>
			<td></td>
			</tr>';
			$total=$total+$subtotal;
		}
		echo '<tfoot>
         <th>TOTAL</th>
         <th></th>
         <th></th>
         <th></th>
         <th></th>
         <th></th>
         <th></th>
         <th><h4 id="total">'.formatearMoneda($total).'</h4><input type="hidden" name="total_compra" id="total_compra"></th>
       </tfoot>';
		break;

    case 'listar':
		$fecha_inicio = fechaFiltroSeguroIngreso(isset($_GET["fecha_inicio"]) ? $_GET["fecha_inicio"] : '');
		$fecha_fin = fechaFiltroSeguroIngreso(isset($_GET["fecha_fin"]) ? $_GET["fecha_fin"] : '');
		$rspta=$ingreso->listarPorFecha($fecha_inicio, $fecha_fin);
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$url='../reportes/exIngreso.php?id=';
			$data[]=array(
            "0"=>(($reg->estado=='Aceptado')?'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idingreso.')"><i class="fa fa-eye"></i></button>'.' '.'<button class="btn btn-danger btn-xs" onclick="anular('.$reg->idingreso.')"><i class="fa fa-close"></i></button>':'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idingreso.')"><i class="fa fa-eye"></i></button>').'<a target="_blank" href="'.$url.$reg->idingreso.'"> <button class="btn btn-info btn-xs"><i class="fa fa-file"></i></button></a>',
            "1"=>$reg->fecha,
            "2"=>$reg->proveedor,
            "3"=>$reg->usuario,
            "4"=>$reg->tipo_comprobante,
            "5"=>$reg->serie_comprobante. '-' .$reg->num_comprobante,
            "6"=>formatearMoneda((float)$reg->total_compra),
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

		case 'selectProveedor':
			require_once "../modelos/Persona.php";
			$persona = new Persona();

			$rspta = $persona->listarp();

			while ($reg = $rspta->fetch_object()) {
				echo '<option value='.$reg->idpersona.'>'.$reg->nombre.'</option>';
			}
			break;

		case 'crearProveedorRapido':
			require_once "../modelos/Persona.php";
			$persona = new Persona();

			$nombreProveedor = isset($_POST['nombre']) ? trim(limpiarCadena($_POST['nombre'])) : '';
			$tipoDocumento = isset($_POST['tipo_documento']) ? trim(limpiarCadena($_POST['tipo_documento'])) : 'DNI';
			$numDocumento = isset($_POST['num_documento']) ? trim(limpiarCadena($_POST['num_documento'])) : '';
			$direccionProveedor = isset($_POST['direccion']) ? trim(limpiarCadena($_POST['direccion'])) : '';
			$telefonoProveedor = isset($_POST['telefono']) ? trim(limpiarCadena($_POST['telefono'])) : '';
			$emailProveedor = isset($_POST['email']) ? trim(limpiarCadena($_POST['email'])) : '';

			if ($nombreProveedor === '') {
				echo json_encode(array("ok"=>false, "message"=>"El nombre del proveedor es obligatorio"));
				break;
			}

			$tiposDocumentoPermitidos = array("DNI", "RUC", "CEDULA");
			if (!in_array($tipoDocumento, $tiposDocumentoPermitidos, true)) {
				$tipoDocumento = "DNI";
			}

			$idProveedorNuevo = $persona->insertarRetornarId("Proveedor", $nombreProveedor, $tipoDocumento, $numDocumento, $direccionProveedor, $telefonoProveedor, $emailProveedor);
			if (!$idProveedorNuevo) {
				echo json_encode(array("ok"=>false, "message"=>"No se pudo registrar el proveedor"));
				break;
			}

			echo json_encode(array(
				"ok"=>true,
				"message"=>"Proveedor registrado correctamente",
				"idproveedor"=>(int)$idProveedorNuevo,
				"nombre"=>$nombreProveedor
			));
			break;

			case 'listarArticulos':
			require_once "../modelos/Articulo.php";
			$articulo=new Articulo();

				$rspta=$articulo->listarActivos();
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$nombrejs = addslashes($reg->nombre);
			$unidadjs = addslashes($reg->abreviatura);
			$stock = (float)$reg->stock;
			$stockFmt = number_format($stock,0);
			$precioCompraRef = is_null($reg->precio_compra_ref) ? 0 : (float)$reg->precio_compra_ref;
			$btnAgregar = '<button class="btn btn-add-item" type="button" onclick="agregarDetalle('.$reg->idarticulo.',\''.$nombrejs.'\',\''.$unidadjs.'\','.$precioCompraRef.')"><i class="fa fa-plus-circle"></i> Agregar</button>';

			if ($stock<=0) {
				$stockHtml='<span class="stock-pill stock-empty">'.$stockFmt.'</span>';
			} elseif ($stock<=5) {
				$stockHtml='<span class="stock-pill stock-low">'.$stockFmt.'</span>';
			} else {
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
            "6"=>formatearMoneda((float)$precioCompraRef),
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
}
 ?>
