<?php
require_once "../config/seguridad.php";
requiereLogin();                 // 401 JSON si no hay sesion; valida CSRF en POST
requierePermiso(array('almacen'));
require_once "../modelos/Unidad.php";

$unidad = new Unidad();

$idunidad    = enteroSeguro(isset($_POST["idunidad"]) ? $_POST["idunidad"] : 0);
$nombre      = isset($_POST["nombre"]) ? limpiarCadena($_POST["nombre"]) : "";
$abreviatura = isset($_POST["abreviatura"]) ? limpiarCadena($_POST["abreviatura"]) : "";
$descripcion = isset($_POST["descripcion"]) ? limpiarCadena($_POST["descripcion"]) : "";
$permite_fraccion = !empty($_POST["permite_fraccion"]) ? 1 : 0;

$op = isset($_GET["op"]) ? $_GET["op"] : '';

switch ($op) {
	case 'guardaryeditar':
		if ($nombre === '') {
			echo "El nombre de la unidad es obligatorio";
			break;
		}
		if ($abreviatura === '') {
			echo "La abreviatura es obligatoria";
			break;
		}
		if (mb_strlen($nombre) > 60) {
			echo "El nombre no puede superar 60 caracteres";
			break;
		}
		if (mb_strlen($abreviatura) > 10) {
			echo "La abreviatura no puede superar 10 caracteres";
			break;
		}
		if ($unidad->existeNombre($nombre, $idunidad)) {
			echo "Ya existe una unidad con ese nombre";
			break;
		}
		if ($unidad->existeAbreviatura($abreviatura, $idunidad)) {
			echo "Ya existe una unidad con esa abreviatura";
			break;
		}
		if ($idunidad <= 0) {
			$rspta = $unidad->insertar($nombre, $abreviatura, $descripcion, $permite_fraccion);
			if ($rspta) {
				registrarAuditoria('almacen', 'crear_unidad', 'Unidad creada: ' . $nombre . ' (' . $abreviatura . ')');
			}
			echo $rspta ? "Unidad registrada correctamente" : "No se pudo registrar la unidad";
		} else {
			$rspta = $unidad->editar($idunidad, $nombre, $abreviatura, $descripcion, $permite_fraccion);
			if ($rspta) {
				registrarAuditoria('almacen', 'editar_unidad', 'Unidad #' . $idunidad . ' editada: ' . $nombre . ' (' . $abreviatura . ')');
			}
			echo $rspta ? "Unidad actualizada correctamente" : "No se pudo actualizar la unidad";
		}
		break;

	case 'desactivar':
		$rspta = ($idunidad > 0) ? $unidad->desactivar($idunidad) : false;
		if ($rspta) {
			registrarAuditoria('almacen', 'desactivar_unidad', 'Unidad #' . $idunidad . ' desactivada');
		}
		echo $rspta ? "Unidad desactivada correctamente" : "No se pudo desactivar la unidad";
		break;

	case 'activar':
		$rspta = ($idunidad > 0) ? $unidad->activar($idunidad) : false;
		if ($rspta) {
			registrarAuditoria('almacen', 'activar_unidad', 'Unidad #' . $idunidad . ' activada');
		}
		echo $rspta ? "Unidad activada correctamente" : "No se pudo activar la unidad";
		break;

	case 'mostrar':
		$rspta = $unidad->mostrar($idunidad);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	case 'listar':
		$rspta = $unidad->listar();
		$data = array();

		if ($rspta instanceof mysqli_result) {
			while ($reg = $rspta->fetch_object()) {
				$id = (int)$reg->idunidad;
				$data[] = array(
					"0" => ($reg->condicion)
						? '<button class="btn btn-warning btn-xs" title="Editar" onclick="mostrar(' . $id . ')"><i class="fa fa-pencil"></i></button> <button class="btn btn-danger btn-xs" title="Desactivar" onclick="desactivar(' . $id . ')"><i class="fa fa-ban"></i></button>'
						: '<button class="btn btn-warning btn-xs" title="Editar" onclick="mostrar(' . $id . ')"><i class="fa fa-pencil"></i></button> <button class="btn btn-success btn-xs" title="Activar" onclick="activar(' . $id . ')"><i class="fa fa-check"></i></button>',
					"1" => e($reg->nombre),
					"2" => e($reg->abreviatura),
					"3" => e($reg->descripcion),
					"4" => ((int)$reg->permite_fraccion === 1) ? '<span class="label bg-aqua" title="Se puede vender 1.5, 0.75…">Sí</span>' : '<span class="label bg-gray" title="Solo cantidades enteras">No</span>',
					"5" => ($reg->condicion) ? '<span class="label bg-green">Activo</span>' : '<span class="label bg-red">Inactivo</span>'
				);
			}
		}

		$results = array(
			"sEcho" => 1,
			"iTotalRecords" => count($data),
			"iTotalDisplayRecords" => count($data),
			"aaData" => $data
		);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($results, JSON_UNESCAPED_UNICODE);
		break;
}
