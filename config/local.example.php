<?php
/**
 * Copia este archivo como config/local.php y ajusta los valores.
 * config/local.php esta en .gitignore: nunca se sube al repositorio.
 */
return array(
	'DB_HOST'     => 'localhost',
	'DB_NAME'     => 'mi_tienda',
	'DB_USERNAME' => 'root',
	'DB_PASSWORD' => '',
	'DB_PORT'     => 0,

	'PRO_NOMBRE'  => 'Mi Tienda',
	'APP_ENV'     => 'development',

	'SESSION_TTL_MINUTOS'   => 480,
	'LOGIN_MAX_INTENTOS'    => 5,
	'LOGIN_BLOQUEO_MINUTOS' => 15,
);
