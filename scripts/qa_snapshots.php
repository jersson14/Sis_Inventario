<?php
/**
 * Herramienta de QA: inicia sesion con un usuario temporal (igual que smoke.php),
 * guarda el HTML renderizado de las vistas en qa_snapshots/ para revisarlas en
 * distintos anchos, y borra el usuario al terminar.
 * Uso: php scripts/qa_snapshots.php
 */
chdir(__DIR__ . '/../ajax');
require_once "../config/Conexion.php";
require_once "../config/seguridad.php";

$base = 'http://127.0.0.1:8099';
$jar = tempnam(sys_get_temp_dir(), 'mtqa');
$csrf = '';

function http($metodo, $url, $data = null) {
	global $jar, $csrf;
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
		CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false,
	));
	if ($csrf !== '') { curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-CSRF-Token: ' . $csrf)); }
	if ($metodo === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); }
	$body = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return array($code, (string)$body);
}

$login = 'qa_snap_' . substr(bin2hex(random_bytes(3)), 0, 5);
$clave = 'Snap' . mt_rand(1000, 9999) . 'x';
$idqa = dbInsert("INSERT INTO usuario(nombre,tipo_documento,num_documento,direccion,telefono,email,cargo,login,clave,imagen,condicion) VALUES('QA Snapshots','DNI','','','','','QA',?,?,'',1)", array($login, password_hash($clave, PASSWORD_BCRYPT)));
dbExec("INSERT INTO usuario_permiso(idusuario,idpermiso) SELECT ?, idpermiso FROM permiso", array($idqa));

$dir = realpath(__DIR__ . '/..') . '/qa_snapshots';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }

try {
	list($c, $b) = http('GET', "$base/vistas/login.php");
	if (preg_match('/id="csrf" value="([a-f0-9]+)"/', $b, $m)) { $csrf = $m[1]; }
	list($c, $b) = http('POST', "$base/ajax/usuario.php?op=verificar", array('logina' => $login, 'clavea' => $clave));
	if (strpos($b, '"ok":true') === false) { echo "login fallido: $b
"; }
	list($c, $b) = http('GET', "$base/vistas/escritorio.php");
	if (preg_match('/appCsrfToken = "([a-f0-9]+)"/', $b, $m)) { $csrf = $m[1]; }

	$vistas = isset($argv[1]) ? explode(',', $argv[1]) : array('escritorio', 'venta', 'ingreso', 'articulo', 'cuentas', 'caja', 'inventario', 'reportes', 'cotizacion', 'usuario');
	foreach ($vistas as $v) {
		list($c, $b) = http('GET', "$base/vistas/$v.php");
		file_put_contents("$dir/$v.html", $b);
		echo str_pad($v, 14) . " code=$c  " . number_format(strlen($b)) . " bytes\n";
	}
} finally {
	dbExec("DELETE FROM auditoria WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM usuario_permiso WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM usuario WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM intento_login WHERE login=?", array($login));
	@unlink($jar);
	echo "usuario temporal eliminado\n";
}
