<?php
require_once "../config/seguridad.php";
requiereLogin();                 // 401 JSON si no hay sesion; valida CSRF en POST
// Clientes -> ventas, Proveedores -> compras. Se afina dentro de cada case segun tipo_persona.
requierePermiso(array('ventas', 'compras'));
require_once "../modelos/Persona.php";

$persona = new Persona();

$idpersona      = enteroSeguro(isset($_POST["idpersona"]) ? $_POST["idpersona"] : 0);
$tipo_persona   = isset($_POST["tipo_persona"]) ? limpiarCadena($_POST["tipo_persona"]) : "";
$nombre         = isset($_POST["nombre"]) ? limpiarCadena($_POST["nombre"]) : "";
$tipo_documento = isset($_POST["tipo_documento"]) ? strtoupper(limpiarCadena($_POST["tipo_documento"])) : "";
$num_documento  = isset($_POST["num_documento"]) ? limpiarCadena($_POST["num_documento"]) : "";
$direccion      = isset($_POST["direccion"]) ? limpiarCadena($_POST["direccion"]) : "";
$telefono       = isset($_POST["telefono"]) ? limpiarCadena($_POST["telefono"]) : "";
$email          = isset($_POST["email"]) ? limpiarCadena($_POST["email"]) : "";

// Permiso requerido segun el tipo de persona.
function permisoPorTipoPersona($tipo) {
	return ($tipo === 'Proveedor') ? 'compras' : 'ventas';
}

// Etiqueta en minusculas para mensajes ("cliente"/"proveedor").
function etiquetaTipoPersona($tipo) {
	return ($tipo === 'Proveedor') ? 'proveedor' : 'cliente';
}

// Fila de opciones (editar + desactivar/activar) para el listado.
function opcionesPersona($id, $activo) {
	$id = (int)$id;
	$editar = '<button class="btn btn-warning btn-xs" title="Editar" onclick="mostrar(' . $id . ')"><i class="fa fa-pencil"></i></button>';
	if ($activo) {
		return $editar . ' <button class="btn btn-danger btn-xs" onclick="eliminar(' . $id . ')" title="Desactivar"><i class="fa fa-ban"></i></button>';
	}
	return $editar . ' <button class="btn btn-success btn-xs" onclick="activar(' . $id . ')" title="Activar"><i class="fa fa-check"></i></button>';
}

// Construye la respuesta aaData de un listado.
function listadoPersonas($rspta) {
	$data = array();
	if ($rspta instanceof mysqli_result) {
		while ($reg = $rspta->fetch_object()) {
			$activo = ((int)$reg->condicion === 1);
			$data[] = array(
				"0" => opcionesPersona($reg->idpersona, $activo),
				"1" => e($reg->nombre),
				"2" => e($reg->tipo_documento),
				"3" => e($reg->num_documento),
				"4" => e($reg->telefono),
				"5" => e($reg->email),
				"6" => $activo ? '<span class="label bg-green">Activo</span>' : '<span class="label bg-red">Inactivo</span>'
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
}

$op = isset($_GET["op"]) ? $_GET["op"] : '';

switch ($op) {
	case 'guardaryeditar':
		if (!in_array($tipo_persona, Persona::tiposPersona(), true)) {
			echo "Tipo de persona no válido";
			break;
		}
		requierePermiso(array(permisoPorTipoPersona($tipo_persona)));

		if ($nombre === '') {
			echo "El nombre es obligatorio";
			break;
		}
		if (mb_strlen($nombre) > 100) {
			echo "El nombre no puede superar 100 caracteres";
			break;
		}
		if (!in_array($tipo_documento, Persona::tiposDocumento(), true)) {
			echo "Tipo de documento no válido";
			break;
		}
		if (mb_strlen($num_documento) > 20 || mb_strlen($telefono) > 20 || mb_strlen($direccion) > 70 || mb_strlen($email) > 50) {
			echo "Uno de los campos supera la longitud permitida";
			break;
		}
		if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			echo "El correo electrónico no es válido";
			break;
		}
		if ($num_documento !== '' && $persona->existeDocumento($tipo_persona, $num_documento, $idpersona)) {
			echo "Ya existe un " . etiquetaTipoPersona($tipo_persona) . " con ese número de documento";
			break;
		}

		if ($idpersona <= 0) {
			$rspta = $persona->insertar($tipo_persona, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email);
			if ($rspta) {
				registrarAuditoria(permisoPorTipoPersona($tipo_persona), 'crear_' . etiquetaTipoPersona($tipo_persona), $tipo_persona . ' creado: ' . $nombre);
			}
			echo $rspta ? "Datos registrados correctamente" : "No se pudo registrar los datos";
		} else {
			// El registro existente debe ser del mismo tipo que el que se esta editando.
			$actual = $persona->mostrar($idpersona);
			if (!$actual || $actual['tipo_persona'] !== $tipo_persona) {
				echo "No se encontró el registro a actualizar";
				break;
			}
			$rspta = $persona->editar($idpersona, $tipo_persona, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email);
			if ($rspta) {
				registrarAuditoria(permisoPorTipoPersona($tipo_persona), 'editar_' . etiquetaTipoPersona($tipo_persona), $tipo_persona . ' #' . $idpersona . ' editado: ' . $nombre);
			}
			echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
		}
		break;

	case 'eliminar': // Baja logica (se mantiene el nombre de la operacion por compatibilidad)
		$actual = ($idpersona > 0) ? $persona->mostrar($idpersona) : null;
		if (!$actual) {
			echo "No se encontró el registro";
			break;
		}
		requierePermiso(array(permisoPorTipoPersona($actual['tipo_persona'])));
		$rspta = $persona->desactivar($idpersona);
		$etiqueta = ucfirst(etiquetaTipoPersona($actual['tipo_persona']));
		if ($rspta) {
			registrarAuditoria(permisoPorTipoPersona($actual['tipo_persona']), 'desactivar_' . etiquetaTipoPersona($actual['tipo_persona']), $etiqueta . ' #' . $idpersona . ' desactivado: ' . $actual['nombre']);
		}
		echo $rspta ? $etiqueta . " desactivado correctamente" : "No se pudo desactivar el registro";
		break;

	case 'activar':
		$actual = ($idpersona > 0) ? $persona->mostrar($idpersona) : null;
		if (!$actual) {
			echo "No se encontró el registro";
			break;
		}
		requierePermiso(array(permisoPorTipoPersona($actual['tipo_persona'])));
		$rspta = $persona->activar($idpersona);
		$etiqueta = ucfirst(etiquetaTipoPersona($actual['tipo_persona']));
		if ($rspta) {
			registrarAuditoria(permisoPorTipoPersona($actual['tipo_persona']), 'activar_' . etiquetaTipoPersona($actual['tipo_persona']), $etiqueta . ' #' . $idpersona . ' activado: ' . $actual['nombre']);
		}
		echo $rspta ? $etiqueta . " activado correctamente" : "No se pudo activar el registro";
		break;

	case 'mostrar':
		$rspta = ($idpersona > 0) ? $persona->mostrar($idpersona) : null;
		if ($rspta) {
			requierePermiso(array(permisoPorTipoPersona($rspta['tipo_persona'])));
		}
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	case 'listarp':
		requierePermiso(array('compras'));
		listadoPersonas($persona->listarp());
		break;

	case 'listarc':
		requierePermiso(array('ventas'));
		listadoPersonas($persona->listarc());
		break;
}
