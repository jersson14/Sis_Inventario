<?php
require_once "../modelos/Unidad.php";

$unidad=new Unidad();

$idunidad=isset($_POST["idunidad"])? limpiarCadena($_POST["idunidad"]):"";
$nombre=isset($_POST["nombre"])? limpiarCadena($_POST["nombre"]):"";
$abreviatura=isset($_POST["abreviatura"])? limpiarCadena($_POST["abreviatura"]):"";
$descripcion=isset($_POST["descripcion"])? limpiarCadena($_POST["descripcion"]):"";

switch ($_GET["op"]) {
	case 'guardaryeditar':
		if (empty($idunidad)) {
			$rspta=$unidad->insertar($nombre,$abreviatura,$descripcion);
			echo $rspta ? "Unidad registrada correctamente" : "No se pudo registrar la unidad";
		}else{
			$rspta=$unidad->editar($idunidad,$nombre,$abreviatura,$descripcion);
			echo $rspta ? "Unidad actualizada correctamente" : "No se pudo actualizar la unidad";
		}
		break;

	case 'desactivar':
		$rspta=$unidad->desactivar($idunidad);
		echo $rspta ? "Unidad desactivada correctamente" : "No se pudo desactivar la unidad";
		break;

	case 'activar':
		$rspta=$unidad->activar($idunidad);
		echo $rspta ? "Unidad activada correctamente" : "No se pudo activar la unidad";
		break;

	case 'mostrar':
		$rspta=$unidad->mostrar($idunidad);
		echo json_encode($rspta);
		break;

	case 'listar':
		$rspta=$unidad->listar();
		$data=Array();

		while ($reg=$rspta->fetch_object()) {
			$data[]=array(
				"0"=>($reg->condicion)?'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idunidad.')"><i class="fa fa-pencil"></i></button>'.' '.'<button class="btn btn-danger btn-xs" onclick="desactivar('.$reg->idunidad.')"><i class="fa fa-close"></i></button>':'<button class="btn btn-warning btn-xs" onclick="mostrar('.$reg->idunidad.')"><i class="fa fa-pencil"></i></button>'.' '.'<button class="btn btn-primary btn-xs" onclick="activar('.$reg->idunidad.')"><i class="fa fa-check"></i></button>',
				"1"=>$reg->nombre,
				"2"=>$reg->abreviatura,
				"3"=>$reg->descripcion,
				"4"=>($reg->condicion)?'<span class="label bg-green">Activo</span>':'<span class="label bg-red">Inactivo</span>'
			);
		}

		$results=array(
			"sEcho"=>1,
			"iTotalRecords"=>count($data),
			"iTotalDisplayRecords"=>count($data),
			"aaData"=>$data
		);
		echo json_encode($results);
		break;
}
?>
