<?php
require_once "../config/seguridad.php";
requiereLogin();
requierePermiso(array('almacen', 'ventas', 'compras'));
require_once "../modelos/Importacion.php";

$imp = new Importacion();
$op = isset($_GET['op']) ? $_GET['op'] : '';
$tipo = isset($_REQUEST['tipo']) ? strtolower(trim($_REQUEST['tipo'])) : 'articulos';
if (!in_array($tipo, array('articulos', 'clientes', 'proveedores'), true)) { $tipo = 'articulos'; }
$permisoTipo = array('articulos' => 'almacen', 'clientes' => 'ventas', 'proveedores' => 'compras');
requierePermiso(array($permisoTipo[$tipo]));

$dirTmp = __DIR__ . '/../files/importaciones';
if (!is_dir($dirTmp)) { @mkdir($dirTmp, 0775, true); @file_put_contents($dirTmp . '/.htaccess', "Require all denied\n"); }

function validar(Importacion $imp, $tipo, array $filas) {
	if ($tipo === 'articulos') { return $imp->validarArticulos($filas); }
	return $imp->validarPersonas($filas, $tipo === 'clientes' ? 'Cliente' : 'Proveedor');
}

switch ($op) {
	case 'plantilla':
		$formato = (isset($_GET['formato']) && $_GET['formato'] === 'csv') ? 'csv' : 'xlsx';
		$nombre = 'plantilla_' . $tipo . '.' . $formato;
		if ($formato === 'csv') {
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="' . $nombre . '"');
			echo $imp->plantillaCsv($tipo);
		} else {
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="' . $nombre . '"');
			echo $imp->plantillaXlsx($tipo);
		}
		exit;

	case 'previsualizar':
		if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
			responderJson(array('ok' => false, 'message' => 'Selecciona un archivo .xlsx o .csv.'));
		}
		if ((int)$_FILES['archivo']['size'] > 10 * 1024 * 1024) {
			responderJson(array('ok' => false, 'message' => 'El archivo supera los 10 MB.'));
		}
		$nombreOriginal = (string)$_FILES['archivo']['name'];
		$token = bin2hex(random_bytes(12));
		$ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
		$destino = $dirTmp . '/' . $token . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
		if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
			responderJson(array('ok' => false, 'message' => 'No se pudo guardar el archivo temporal.'));
		}
		$lectura = $imp->leer($destino, $nombreOriginal);
		if (empty($lectura['ok'])) { @unlink($destino); responderJson(array('ok' => false, 'message' => $lectura['message'])); }
		$val = validar($imp, $tipo, $lectura['filas']);
		$_SESSION['importacion'] = array('token' => $token, 'ruta' => $destino, 'nombre' => $nombreOriginal, 'tipo' => $tipo, 'creada' => time());
		// Limpieza de temporales antiguos (> 2 horas)
		foreach (glob($dirTmp . '/*.*') as $f) { if (basename($f) !== '.htaccess' && filemtime($f) < time() - 7200) @unlink($f); }
		$val['ok'] = true;
		$val['token'] = $token;
		$val['cabeceras'] = $lectura['cabeceras'];
		$val['truncado'] = !empty($lectura['truncado']);
		$val['total'] = count($val['filas']);
		$val['columnas_esperadas'] = array_keys(Importacion::columnas($tipo));
		responderJson($val);
		break;

	case 'importar':
		$token = isset($_POST['token']) ? preg_replace('/[^a-f0-9]/', '', $_POST['token']) : '';
		$s = isset($_SESSION['importacion']) ? $_SESSION['importacion'] : null;
		if (!$s || $token === '' || $s['token'] !== $token || !file_exists($s['ruta']) || $s['tipo'] !== $tipo) {
			responderJson(array('ok' => false, 'message' => 'La vista previa expiró. Vuelve a subir el archivo.'));
		}
		$lectura = $imp->leer($s['ruta'], $s['nombre']);
		if (empty($lectura['ok'])) { responderJson(array('ok' => false, 'message' => $lectura['message'])); }
		$val = validar($imp, $tipo, $lectura['filas']);
		$opciones = array(
			'crear_categorias' => !empty($_POST['crear_categorias']),
			'actualizar_existentes' => !empty($_POST['actualizar_existentes']),
			'actualizar_stock' => !empty($_POST['actualizar_stock']),
		);
		if ($tipo === 'articulos') {
			$r = $imp->importarArticulos($val, $opciones, (int)$_SESSION['idusuario']);
		} else {
			$r = $imp->importarPersonas($val, $tipo === 'clientes' ? 'Cliente' : 'Proveedor', $opciones);
		}
		@unlink($s['ruta']);
		unset($_SESSION['importacion']);
		if (!empty($r['ok'])) {
			registrarAuditoria('importacion', 'importar_' . $tipo, 'Archivo ' . mb_substr($s['nombre'], 0, 60) . ': ' . (int)$r['creados'] . ' creados, ' . (int)$r['actualizados'] . ' actualizados, ' . (int)$r['omitidos'] . ' omitidos');
			$r['message'] = 'Importación completada: ' . (int)$r['creados'] . ' creados, ' . (int)$r['actualizados'] . ' actualizados, ' . (int)$r['omitidos'] . ' omitidos.';
		}
		responderJson($r);
		break;

	case 'cancelar':
		if (isset($_SESSION['importacion'])) { @unlink($_SESSION['importacion']['ruta']); unset($_SESSION['importacion']); }
		responderJson(array('ok' => true));
		break;

	default:
		responderJson(array('ok' => false, 'message' => 'Operación no válida.'), 400);
}
