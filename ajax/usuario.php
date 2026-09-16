<?php
/**
 * Usuarios y autenticacion.
 *  - op=verificar : login (publica, con CSRF y bloqueo por intentos fallidos).
 *  - op=salir     : logout.
 *  - resto        : requieren sesion; las de administracion requieren permiso 'acceso'.
 */
require_once "../config/seguridad.php";
require_once "../modelos/Usuario.php";

$usuario = new Usuario();
$op = isset($_GET['op']) ? $_GET['op'] : '';

// ---------- LOGIN (publica) ----------
if ($op === 'verificar') {
	iniciarSesionSegura();
	enviarCabecerasSeguridad();
	verificarCsrf(true);

	$login = substr(trim((string)(isset($_POST['logina']) ? $_POST['logina'] : '')), 0, 60);
	$clave = (string)(isset($_POST['clavea']) ? $_POST['clavea'] : '');

	if ($login === '' || $clave === '') {
		responderJson(array('ok' => false, 'message' => 'Ingresa usuario y contraseña'));
	}

	$seg = segundosBloqueoLogin($login);
	if ($seg > 0) {
		$min = (int)ceil($seg / 60);
		responderJson(array(
			'ok' => false,
			'bloqueado' => true,
			'segundos' => (int)$seg,
			'message' => "Demasiados intentos fallidos. Intenta de nuevo en " . $min . " minuto(s)."
		));
	}

	$fila = $usuario->obtenerPorLogin($login);
	$ok = false;
	$rehash = false;
	if ($fila) {
		list($ok, $rehash) = verificarClave($clave, $fila['clave']);
	} else {
		// Igualar tiempos de respuesta cuando el usuario no existe.
		password_verify($clave, '$2y$11$abcdefghijklmnopqrstuuXqL3Y8qkQ9hYbWzZ9pV1z9Q7iN5kO1G');
	}

	if (!$ok) {
		registrarIntentoLogin($login, false);
		responderJson(array('ok' => false, 'message' => 'Usuario y/o contraseña incorrectos'));
	}

	$id = (int)$fila['idusuario'];
	if ($rehash) {
		$usuario->actualizarClave($id, hashClave($clave));
	}
	registrarIntentoLogin($login, true);

	$ids = $usuario->idsPermisos($id);
	unset($fila['clave']);
	establecerSesionUsuario($fila, $ids);
	$usuario->registrarAcceso($id);
	registrarAuditoria('acceso', 'login', 'Inicio de sesión');

	responderJson(array(
		'ok' => true,
		'nombre' => $fila['nombre'],
		'redirect' => 'escritorio.php',
		'csrf' => csrfToken()
	));
}

// ---------- LOGOUT ----------
if ($op === 'salir') {
	iniciarSesionSegura();
	if (usuarioAutenticado()) {
		registrarAuditoria('acceso', 'logout', 'Cierre de sesión');
	}
	cerrarSesionUsuario();
	header('Location: ../vistas/login.php?salir=1');
	exit;
}

// ---------- OPERACIONES PROTEGIDAS ----------
requiereLogin();                 // 401 JSON si no hay sesion; valida CSRF en POST

$esAdmin  = usuarioTienePermiso('acceso');
$idSesion = (int)$_SESSION['idusuario'];

$idusuario      = enteroSeguro(isset($_POST["idusuario"]) ? $_POST["idusuario"] : 0);
$nombre         = isset($_POST["nombre"]) ? limpiarCadena($_POST["nombre"]) : "";
$tipo_documento = isset($_POST["tipo_documento"]) ? strtoupper(limpiarCadena($_POST["tipo_documento"])) : "";
$num_documento  = isset($_POST["num_documento"]) ? limpiarCadena($_POST["num_documento"]) : "";
$direccion      = isset($_POST["direccion"]) ? limpiarCadena($_POST["direccion"]) : "";
$telefono       = isset($_POST["telefono"]) ? limpiarCadena($_POST["telefono"]) : "";
$email          = isset($_POST["email"]) ? limpiarCadena($_POST["email"]) : "";
$cargo          = isset($_POST["cargo"]) ? limpiarCadena($_POST["cargo"]) : "";
$login          = isset($_POST["login"]) ? trim((string)$_POST["login"]) : "";
$clave          = isset($_POST["clave"]) ? (string)$_POST["clave"] : "";

switch ($op) {
	case 'guardaryeditar':
		$esEdicion = ($idusuario > 0);

		// Un usuario sin permiso de acceso solo puede editar su propio perfil.
		if (!$esAdmin && (!$esEdicion || $idusuario !== $idSesion)) {
			echo "No tienes permiso para realizar esta acción";
			break;
		}

		$actual = null;
		if ($esEdicion) {
			$actual = $usuario->mostrar($idusuario);
			if (!$actual) {
				echo "No se encontró el usuario a actualizar";
				break;
			}
			if (!$esAdmin) {
				// No puede cambiar su login: se conserva el actual.
				$login = (string)$actual['login'];
			}
		}

		// Validaciones de campos
		if ($nombre === '') {
			echo "El nombre es obligatorio";
			break;
		}
		if (mb_strlen($nombre) > 100) {
			echo "El nombre no puede superar 100 caracteres";
			break;
		}
		if (!in_array($tipo_documento, Usuario::tiposDocumento(), true)) {
			echo "Tipo de documento no válido";
			break;
		}
		if (mb_strlen($num_documento) > 20 || mb_strlen($telefono) > 20 || mb_strlen($direccion) > 70 || mb_strlen($email) > 50 || mb_strlen($cargo) > 20) {
			echo "Uno de los campos supera la longitud permitida";
			break;
		}
		if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			echo "El correo electrónico no es válido";
			break;
		}
		if ($login === '') {
			echo "El nombre de usuario (login) es obligatorio";
			break;
		}
		$loginCambia = !$esEdicion || $login !== (string)$actual['login'];
		if ($loginCambia && !preg_match('/^[A-Za-z0-9._@-]{3,20}$/', $login)) {
			echo "El nombre de usuario debe tener entre 3 y 20 caracteres (letras, números, punto, guion, guion bajo o @)";
			break;
		}
		if ($usuario->existeLogin($login, $idusuario)) {
			echo "El nombre de usuario ya existe";
			break;
		}

		// Clave: obligatoria al crear; opcional al editar.
		$claveHash = '';
		if (!$esEdicion && $clave === '') {
			echo "La contraseña es obligatoria";
			break;
		}
		if ($clave !== '') {
			$msgClave = validarFortalezaClave($clave);
			if ($msgClave !== '') {
				echo $msgClave;
				break;
			}
			$claveHash = hashClave($clave);
		}

		// Permisos: solo admin puede modificarlos; se validan contra la tabla permiso.
		if ($esAdmin) {
			$recibidos = isset($_POST['permiso']) && is_array($_POST['permiso']) ? $_POST['permiso'] : array();
			$validos = $usuario->permisosValidos();
			$permisos = array();
			foreach ($recibidos as $p) {
				$p = enteroSeguro($p);
				if ($p > 0 && in_array($p, $validos, true) && !in_array($p, $permisos, true)) {
					$permisos[] = $p;
				}
			}
			// Un admin no puede quitarse a si mismo el permiso de acceso.
			if ($esEdicion && $idusuario === $idSesion && !in_array((int)mapaPermisos()['acceso'], $permisos, true)) {
				$permisos[] = (int)mapaPermisos()['acceso'];
			}
		} else {
			$permisos = $usuario->idsPermisos($idusuario);
		}

		// Imagen: si se sube archivo se valida; si no, se conserva la actual.
		$hayArchivo = isset($_FILES['imagen']) && is_array($_FILES['imagen'])
			&& isset($_FILES['imagen']['error']) && !is_array($_FILES['imagen']['error'])
			&& (int)$_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE;
		if ($hayArchivo) {
			list($okImg, $resImg) = guardarImagenSubida('imagen', '../files/usuarios');
			if (!$okImg) {
				echo $resImg;
				break;
			}
			$imagen = $resImg;
		} else {
			$imagen = nombreArchivoSeguro(isset($_POST["imagenactual"]) ? $_POST["imagenactual"] : '');
			if ($imagen === '' && $esEdicion) {
				$imagen = nombreArchivoSeguro($actual['imagen']);
			}
		}

		if (!$esEdicion) {
			$idNuevo = dbTransaccion(function () use ($usuario, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email, $cargo, $login, $claveHash, $imagen, $permisos) {
				$id = $usuario->insertar($nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email, $cargo, $login, $claveHash, $imagen);
				if ($id <= 0) {
					return false;
				}
				if (!$usuario->reemplazarPermisos($id, $permisos)) {
					return false;
				}
				return $id;
			});
			if ($idNuevo) {
				registrarAuditoria('acceso', 'crear_usuario', 'Usuario creado #' . (int)$idNuevo . ': ' . $login);
			}
			echo $idNuevo ? "Datos registrados correctamente" : "No se pudo registrar todos los datos del usuario";
		} else {
			$rspta = dbTransaccion(function () use ($usuario, $idusuario, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email, $cargo, $login, $claveHash, $imagen, $permisos) {
				if (!$usuario->editar($idusuario, $nombre, $tipo_documento, $num_documento, $direccion, $telefono, $email, $cargo, $login, $claveHash, $imagen)) {
					return false;
				}
				if (!$usuario->reemplazarPermisos($idusuario, $permisos)) {
					return false;
				}
				return true;
			});
			if ($rspta) {
				if ($idusuario === $idSesion) {
					$_SESSION['nombre'] = $nombre;
					$_SESSION['imagen'] = $imagen;
					$_SESSION['cargo']  = $cargo;
				}
				registrarAuditoria('acceso', 'editar_usuario', 'Usuario #' . $idusuario . ' editado: ' . $login . ($claveHash !== '' ? ' (clave cambiada)' : ''));
			}
			echo $rspta ? "Datos actualizados correctamente" : "No se pudo actualizar los datos";
		}
		break;

	case 'desactivar':
		requierePermiso(array('acceso'));
		if ($idusuario === $idSesion) {
			echo "No puedes desactivar tu propio usuario";
			break;
		}
		$rspta = ($idusuario > 0) ? $usuario->desactivar($idusuario) : false;
		if ($rspta) {
			registrarAuditoria('acceso', 'desactivar_usuario', 'Usuario #' . $idusuario . ' desactivado');
		}
		echo $rspta ? "Datos desactivados correctamente" : "No se pudo desactivar los datos";
		break;

	case 'activar':
		requierePermiso(array('acceso'));
		$rspta = ($idusuario > 0) ? $usuario->activar($idusuario) : false;
		if ($rspta) {
			registrarAuditoria('acceso', 'activar_usuario', 'Usuario #' . $idusuario . ' activado');
		}
		echo $rspta ? "Datos activados correctamente" : "No se pudo activar los datos";
		break;

	case 'cambiarClave':
		$claveActual   = isset($_POST['clave_actual']) ? (string)$_POST['clave_actual'] : '';
		$claveNueva    = isset($_POST['clave_nueva']) ? (string)$_POST['clave_nueva'] : '';
		$claveConfirma = isset($_POST['clave_confirma']) ? (string)$_POST['clave_confirma'] : '';

		if ($claveActual === '' || $claveNueva === '' || $claveConfirma === '') {
			responderJson(array('ok' => false, 'message' => 'Completa todos los campos'));
		}
		if ($claveNueva !== $claveConfirma) {
			responderJson(array('ok' => false, 'message' => 'La nueva contraseña y su confirmación no coinciden'));
		}
		$msgClave = validarFortalezaClave($claveNueva);
		if ($msgClave !== '') {
			responderJson(array('ok' => false, 'message' => $msgClave));
		}
		list($okActual, $sinUso) = verificarClave($claveActual, $usuario->obtenerHashClave($idSesion));
		if (!$okActual) {
			responderJson(array('ok' => false, 'message' => 'La contraseña actual es incorrecta'));
		}
		if ($claveActual === $claveNueva) {
			responderJson(array('ok' => false, 'message' => 'La nueva contraseña debe ser distinta a la actual'));
		}
		if (!$usuario->actualizarClave($idSesion, hashClave($claveNueva))) {
			responderJson(array('ok' => false, 'message' => 'No se pudo actualizar la contraseña'));
		}
		registrarAuditoria('acceso', 'cambiar_clave', 'Cambio de contraseña propio');
		responderJson(array('ok' => true, 'message' => 'Contraseña actualizada correctamente'));
		break;

	case 'mostrar':
		if (!$esAdmin && $idusuario !== $idSesion) {
			requierePermiso(array('acceso'));
		}
		$rspta = ($idusuario > 0) ? $usuario->mostrar($idusuario) : null;
		if (is_array($rspta)) {
			unset($rspta['clave']);
		}
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($rspta, JSON_UNESCAPED_UNICODE);
		break;

	case 'listar':
		requierePermiso(array('acceso'));
		$rspta = $usuario->listar();
		$data = array();

		if ($rspta instanceof mysqli_result) {
			while ($reg = $rspta->fetch_object()) {
				$id = (int)$reg->idusuario;
				$editar = '<button class="btn btn-warning btn-xs" title="Editar" onclick="mostrar(' . $id . ')"><i class="fa fa-pencil"></i></button>';
				if ($reg->condicion) {
					// No se muestra el boton de desactivar para el propio usuario.
					$opciones = ($id === $idSesion) ? $editar : $editar . ' <button class="btn btn-danger btn-xs" title="Desactivar" onclick="desactivar(' . $id . ')"><i class="fa fa-ban"></i></button>';
				} else {
					$opciones = $editar . ' <button class="btn btn-success btn-xs" title="Activar" onclick="activar(' . $id . ')"><i class="fa fa-check"></i></button>';
				}
				$img = nombreArchivoSeguro($reg->imagen);
				$srcImg = ($img !== '') ? '../files/usuarios/' . e($img) : '../public/img/avatar.png';
				$ultimo = '-';
				if (!empty($reg->ultimo_acceso)) {
					$ts = strtotime((string)$reg->ultimo_acceso);
					if ($ts !== false) {
						$ultimo = date('d/m/Y H:i', $ts);
					}
				}
				$data[] = array(
					"0" => $opciones,
					"1" => e($reg->nombre),
					"2" => e($reg->tipo_documento),
					"3" => e($reg->num_documento),
					"4" => e($reg->telefono),
					"5" => e($reg->email),
					"6" => e($reg->login),
					"7" => e($reg->cargo),
					"8" => "<img src='" . $srcImg . "' height='50px' width='50px'>",
					"9" => $ultimo,
					"10" => ($reg->condicion) ? '<span class="label bg-green">Activado</span>' : '<span class="label bg-red">Desactivado</span>'
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

	case 'permisos':
		$id = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);
		if (!$esAdmin && $id !== $idSesion) {
			requierePermiso(array('acceso'));
		}
		require_once "../modelos/Permiso.php";
		$permiso = new Permiso();
		$rspta = $permiso->listar();
		$valores = ($id > 0) ? $usuario->idsPermisos($id) : array();
		$disabled = $esAdmin ? '' : ' disabled';

		if ($rspta instanceof mysqli_result) {
			while ($reg = $rspta->fetch_object()) {
				$sw = in_array((int)$reg->idpermiso, $valores, true) ? 'checked' : '';
				echo '<li><input type="checkbox" ' . $sw . $disabled . ' name="permiso[]" value="' . (int)$reg->idpermiso . '">' . e($reg->nombre) . '</li>';
			}
		}
		break;

	case 'resumenPerfil':
		$fila = $usuario->mostrar($idSesion);
		if (!$fila) {
			responderJson(array('ok' => false, 'message' => 'Usuario no encontrado'), 404);
		}
		responderJson(array(
			'ok' => true,
			'idusuario' => (int)$fila['idusuario'],
			'nombre' => $fila['nombre'],
			'login' => $fila['login'],
			'cargo' => $fila['cargo'],
			'imagen' => $fila['imagen'],
			'ultimo_acceso' => $fila['ultimo_acceso'],
			'permisos' => $usuario->nombresPermisos($idSesion)
		));
		break;
}
