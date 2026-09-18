<?php
/**
 * Enlace publico del comprobante (el que lleva el QR del ticket y del PDF).
 *
 *   urlBaseSistema()                 raiz del sistema vista desde fuera
 *   urlComprobantePublico($codigo)   .../comprobante.php?c=CODIGO
 *   codigoPublicoValido($codigo)     formato de la clave (16 letras/numeros)
 *
 * La direccion sale de Empresa > Ticket > "Dirección pública" y, si esta vacia,
 * de la peticion actual (sirve en red local; con "localhost" el celular del
 * cliente no podra abrirla).
 */
require_once __DIR__ . "/seguridad.php";

if (!function_exists('urlBaseSistema')) {

	/** URL publica configurada, validada (sin barra final) o ''. */
	function urlPublicaConfigurada() {
		try {
			$url = trim((string)dbValue("SELECT url_publica FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1", array(), ''));
		} catch (Throwable $e) {
			$url = '';
		}
		return normalizarUrlPublica($url);
	}

	/** Deja solo http(s)://host[:puerto][/ruta]; cualquier otra cosa devuelve ''. */
	function normalizarUrlPublica($url) {
		$url = rtrim(trim((string)$url), '/');
		if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
			return '';
		}
		$p = parse_url($url);
		if (!$p || empty($p['host']) || !in_array(strtolower(isset($p['scheme']) ? $p['scheme'] : ''), array('http', 'https'), true)
			|| isset($p['query']) || isset($p['fragment']) || isset($p['user'])) {
			return '';
		}
		return $url;
	}

	function urlBaseSistema() {
		$configurada = urlPublicaConfigurada();
		if ($configurada !== '') {
			return $configurada;
		}
		$host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'localhost';
		if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$|^\[[0-9a-fA-F:]+\](:\d+)?$/', $host)) {
			$host = 'localhost';
		}
		// Carpeta del proyecto dentro del servidor: al SCRIPT_NAME se le quita la
		// parte que corresponde al archivo actual dentro del proyecto.
		$ruta = '';
		$raiz = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
		$script = str_replace('\\', '/', (string)realpath(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : ''));
		$nombre = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
		if ($raiz !== '' && stripos($script, $raiz . '/') === 0) {
			$rel = substr($script, strlen($raiz));
			if (strcasecmp(substr($nombre, -strlen($rel)), $rel) === 0) {
				$ruta = substr($nombre, 0, -strlen($rel));
			}
		}
		return (esHttps() ? 'https' : 'http') . '://' . $host . rtrim($ruta, '/');
	}

	function urlComprobantePublico($codigo) {
		return urlBaseSistema() . '/comprobante.php?c=' . rawurlencode((string)$codigo);
	}

	function codigoPublicoValido($codigo) {
		return is_string($codigo) && preg_match('/^[A-Za-z0-9]{16}$/', $codigo) === 1;
	}

	/** true si el enlace apunta a esta misma PC y no abrira en el celular del cliente. */
	function urlEsSoloLocal($url) {
		$host = strtolower((string)parse_url($url, PHP_URL_HOST));
		return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || $host === '[::1]';
	}
}
