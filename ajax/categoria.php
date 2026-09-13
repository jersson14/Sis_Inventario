<?php
require_once "../config/seguridad.php";
requiereLogin();                 // 401 JSON si no hay sesion; valida CSRF en POST
// listar/mostrar tambien para ventas y compras; escritura solo almacen (se valida en cada case).
requierePermiso(array('almacen', 'ventas', 'compras'));
require_once "../modelos/Categoria.php";

$categoria = new Categoria();

$idcategoria = enteroSeguro(isset($_POST["idcategoria"]) ? $_POST["idcategoria"] : 0);
$nombre      = isset($_POST["nombre"]) ? limpiarCadena($_POST["nombre"]) : "";
$descripcion = isset($_POST["descripcion"]) ? limpiarCadena($_POST["descripcion"]) : "";

$op = isset($_GET["op"]) ? $_GET["op"] : '';

switch ($op) {
	case 'guardaryeditar':
		requierePermiso(array('almacen'));
		if ($nombre === '') {
			echo "El nombre de la categoría es obligatorio";
			break;
		}
		if (mb_strlen($nombre) > 50) {
			echo "El nombre no puede superar 50 caracteres";
			break;
		}
		if ($categoria->existeNombre($nombre, $idcategoria)) {
			echo "Ya existe una categoría con ese nombre";
			break;
		}
		if ($idcategoria <= 0) {
			$rspta = $categoria->insertar($nombre, $descripcion);
			if ($rspta) {
				registrarAuditoria('almacen', 'crear_categoria', 'Categoria creada: ' . $nombre);
			}
			echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
		} else {
			$rspta = $categoria->editar($idcategoria, $nombre, $descripcion);
			if ($rspta) {
				registrarAuditoria('almacen', 'editar_categoria', 'Categoria #' . $idcategoria . ' editada: ' . $nombre);
			}
			echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
		}
		break;

	case 'desactivar':
		requierePermiso(array('almacen'));
		$rspta = ($idcategoria > 0) ? $categoria->desactivar($idcategoria) : false;
		if ($rspta) {
			registrarAuditoria('almacen', 'desactivar_categoria', 'Categoria #' . $idcategoria . ' desactivada');
		}
		echo $rspta ? "Datos desactivados correctamente" : "No se pudo desactivar los datos";
		break;

	case 'activar':
		requierePermiso(array('almacen'));
		$rspta = ($idcategoria > 0) ? $categoria->activar($idcategoria) : false;
		if ($rspta) {
			registrarAuditoria('almacen', 'activar_categoria', 'Categoria #' . $idcategoria . ' activada');
		}
		echo $rspta ? "Datos activados correctamente" : "No se pudo activar los datos";
		break;

	case 'mostrar':
		$rspta = $categoria->mostrar($idcategoria);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	case 'listar':
		$rspta = $categoria->listar();
		$data = array();

		if ($rspta instanceof mysqli_result) {
			while ($reg = $rspta->fetch_object()) {
				$id = (int)$reg->idcategoria;
				$data[] = array(
					"0" => ($reg->condicion)
						? '<button class="btn btn-warning btn-xs" onclick="mostrar(' . $id . ')"><i class="fa fa-pencil"></i></button> <button class="btn btn-danger btn-xs" onclick="desactivar(' . $id . ')"><i class="fa fa-close"></i></button>'
						: '<button class="btn btn-warning btn-xs" onclick="mostrar(' . $id . ')"><i class="fa fa-pencil"></i></button> <button class="btn btn-primary btn-xs" onclick="activar(' . $id . ')"><i class="fa fa-check"></i></button>',
					"1" => e($reg->nombre),
					"2" => e($reg->descripcion),
					"3" => ($reg->condicion) ? '<span class="label bg-green">Activado</span>' : '<span class="label bg-red">Desactivado</span>'
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
