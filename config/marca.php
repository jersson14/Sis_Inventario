<?php
/**
 * Marca de la empresa (Configuracion > Empresa y marca) para todo el sistema:
 * landing, login, panel, tickets y PDF. Un solo lugar decide que logo, nombre
 * y colores se usan, en vez de repetir la busqueda en cada pagina.
 *
 *   marcaEmpresa()               datos ya decodificados (cacheados por peticion)
 *   marcaUrlLogo($prefijo)       URL del logo para <img>, con ?v= para refrescar cache
 *   marcaRutaLogoPdf()           ruta en disco apta para FPDF (JPG/PNG) o ''
 *   marcaOscurecer($hex, $f)     variante oscura de un color
 */
require_once __DIR__ . "/Conexion.php";

if (!function_exists('marcaEmpresa')) {

	/** Logo generico del producto (cuando la empresa no subio el suyo). */
	define('MARCA_LOGO_GENERICO', 'public/img/brand-store.svg');

	function marcaTexto($texto) {
		$t = trim((string)$texto);
		for ($i = 0; $i < 3; $i++) {
			$d = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if ($d === $t) {
				break;
			}
			$t = $d;
		}
		return trim($t);
	}

	function marcaColor($hex, $defecto) {
		$hex = trim((string)$hex);
		return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtolower($hex) : $defecto;
	}

	function marcaOscurecer($hex, $factor = 0.22) {
		$rgb = sscanf(ltrim((string)$hex, '#'), "%02x%02x%02x");
		if (!$rgb || count($rgb) !== 3 || in_array(null, $rgb, true)) {
			return '#0b4f4a';
		}
		return sprintf("#%02x%02x%02x",
			max(0, (int)round($rgb[0] * (1 - $factor))),
			max(0, (int)round($rgb[1] * (1 - $factor))),
			max(0, (int)round($rgb[2] * (1 - $factor))));
	}

	function marcaSuave($hex, $alfa = 0.12) {
		$rgb = sscanf(ltrim((string)$hex, '#'), "%02x%02x%02x");
		return sprintf('rgba(%d,%d,%d,%s)', (int)$rgb[0], (int)$rgb[1], (int)$rgb[2], $alfa);
	}

	/**
	 * Archivo del logo relativo a la raiz del proyecto ('files/empresa/x.png'),
	 * o '' si no hay. Acepta tambien los logos heredados guardados en vistas/.
	 */
	function marcaArchivoLogo($logo) {
		$logo = basename(trim((string)$logo));
		if ($logo === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $logo)) {
			return '';
		}
		$raiz = realpath(__DIR__ . '/..');
		if (is_file($raiz . '/files/empresa/' . $logo)) {
			return 'files/empresa/' . $logo;
		}
		if (is_file($raiz . '/vistas/' . $logo)) {
			return 'vistas/' . $logo;
		}
		return '';
	}

	function marcaEmpresa() {
		static $marca = null;
		if ($marca !== null) {
			return $marca;
		}
		$cfg = null;
		try {
			$cfg = dbRow("SELECT nombre_comercial, razon_social, logo, color_primario, color_secundario FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1");
		} catch (Throwable $e) {
			$cfg = null;   // instalacion sin BD todavia
		}
		$nombre = $cfg && !empty($cfg['nombre_comercial']) ? marcaTexto($cfg['nombre_comercial']) : PRO_NOMBRE;
		$razon = $cfg ? marcaTexto($cfg['razon_social']) : '';
		$primario = marcaColor($cfg ? $cfg['color_primario'] : '', '#0f766e');
		$secundario = marcaColor($cfg ? $cfg['color_secundario'] : '', '#f59e0b');
		$logo = $cfg ? marcaArchivoLogo($cfg['logo']) : '';
		$marca = array(
			'nombre' => $nombre,
			'razon_social' => $razon,
			'sub' => ($razon !== '' && strcasecmp($razon, $nombre) !== 0) ? $razon : '',
			'logo' => $logo,
			'tiene_logo' => $logo !== '',
			'primario' => $primario,
			'primario_oscuro' => marcaOscurecer($primario, 0.25),
			'primario_suave' => marcaSuave($primario, 0.12),
			'secundario' => $secundario,
			'secundario_oscuro' => marcaOscurecer($secundario, 0.15),
		);
		return $marca;
	}

	/**
	 * URL del logo para usar en <img>/<link rel=icon>. $prefijo es la ruta
	 * hasta la raiz del proyecto: '' desde index.php, '../' desde vistas/.
	 * El ?v= cambia cuando se sube otro archivo, asi el navegador no muestra
	 * el logo anterior.
	 */
	function marcaUrlLogo($prefijo = '../') {
		$m = marcaEmpresa();
		if (!$m['tiene_logo']) {
			return $prefijo . MARCA_LOGO_GENERICO;
		}
		$ts = @filemtime(realpath(__DIR__ . '/..') . '/' . $m['logo']);
		return $prefijo . $m['logo'] . ($ts ? '?v=' . $ts : '');
	}

	/**
	 * Ruta absoluta del logo para FPDF, que solo lee JPG, PNG y GIF. Un logo
	 * WEBP se convierte una vez a PNG (si GD lo permite); si no se puede, los
	 * PDF salen sin logo en vez de fallar.
	 */
	function marcaRutaLogoPdf() {
		$m = marcaEmpresa();
		if (!$m['tiene_logo']) {
			return '';
		}
		$ruta = realpath(__DIR__ . '/..') . '/' . $m['logo'];
		$ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
		if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif'), true)) {
			return $ruta;
		}
		if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
			$png = dirname($ruta) . '/_pdf_' . pathinfo($ruta, PATHINFO_FILENAME) . '.png';
			if (!is_file($png) || filemtime($png) < filemtime($ruta)) {
				$img = @imagecreatefromwebp($ruta);
				if (!$img) {
					return '';
				}
				imagesavealpha($img, true);
				$ok = @imagepng($img, $png);
				imagedestroy($img);
				if (!$ok) {
					return '';
				}
			}
			return $png;
		}
		return '';
	}
}
