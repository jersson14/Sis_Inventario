<?php
/**
 * Configuracion global del sistema.
 *
 * Para personalizar sin tocar este archivo (y sin subirlo a git), crea
 * config/local.php devolviendo un array con las claves que quieras
 * sobreescribir. Ejemplo en config/local.example.php.
 */

$__cfg = array(
	// Base de datos
	'DB_HOST'     => 'localhost',
	'DB_NAME'     => 'mi_tienda',
	'DB_USERNAME' => 'root',
	'DB_PASSWORD' => '',
	'DB_ENCODE'   => 'utf8mb4',
	'DB_PORT'     => 0, // 0 = usar el puerto por defecto de php.ini (mysqli.default_port)

	// Aplicacion
	'PRO_NOMBRE'      => 'Mi Tienda',
	'APP_VERSION'     => '2.0.4',
	'APP_ENV'         => 'development', // development | production
	'APP_TIMEZONE'    => 'America/Lima',

	// Sesion y seguridad
	'SESSION_NAME'          => 'mitienda_sid',
	'SESSION_TTL_MINUTOS'   => 480,   // inactividad maxima antes de cerrar sesion
	'LOGIN_MAX_INTENTOS'    => 5,     // intentos fallidos permitidos
	'LOGIN_BLOQUEO_MINUTOS' => 15,    // ventana de bloqueo tras exceder intentos
	'UPLOAD_MAX_BYTES'      => 3 * 1024 * 1024, // 3 MB por imagen
);

$__localFile = __DIR__ . '/local.php';
if (is_file($__localFile)) {
	$__local = include $__localFile;
	if (is_array($__local)) {
		$__cfg = array_merge($__cfg, $__local);
	}
}

foreach ($__cfg as $__k => $__v) {
	if (!defined($__k)) {
		define($__k, $__v);
	}
}
unset($__cfg, $__local, $__localFile, $__k, $__v);

date_default_timezone_set(APP_TIMEZONE);

if (APP_ENV === 'production') {
	ini_set('display_errors', '0');
	ini_set('log_errors', '1');
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
} else {
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
}

if (!function_exists('appLog')) {
	/**
	 * Escribe una linea en logs/app.log (carpeta ignorada por git).
	 */
	function appLog($nivel, $mensaje, $contexto = array()) {
		$dir = dirname(__DIR__) . '/logs';
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}
		$linea = '[' . date('Y-m-d H:i:s') . '] ' . strtoupper((string)$nivel) . ': ' . (string)$mensaje;
		if (!empty($contexto)) {
			$linea .= ' ' . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		@file_put_contents($dir . '/app.log', $linea . PHP_EOL, FILE_APPEND | LOCK_EX);
	}
}
