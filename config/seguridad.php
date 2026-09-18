<?php
/**
 * Seguridad transversal: sesion endurecida, cabeceras HTTP, CSRF,
 * autenticacion/autorizacion por permisos, bloqueo de fuerza bruta,
 * auditoria y validacion de archivos subidos.
 *
 * Uso tipico en un endpoint AJAX:
 *   require_once "../config/seguridad.php";
 *   requiereLogin();               // 401 JSON si no hay sesion
 *   requierePermiso('almacen');    // 403 JSON si no tiene el permiso
 *
 * Uso en una vista:
 *   require_once "../config/seguridad.php";
 *   requiereLogin(false);          // redirige a login.php si no hay sesion
 */
require_once __DIR__ . "/Conexion.php";
require_once __DIR__ . "/negocio.php";   // perfil de negocio: perfilNegocio(), negocioTiene()
require_once __DIR__ . "/marca.php";     // logo, nombre y colores de la empresa

if (!function_exists('iniciarSesionSegura')) {

	/** Mapa clave de permiso => idpermiso (tabla permiso). */
	function mapaPermisos() {
		return array(
			'escritorio' => 1,
			'almacen'    => 2,
			'compras'    => 3,
			'ventas'     => 4,
			'acceso'     => 5,
			'consultac'  => 6,
			'consultav'  => 7,
			'gestion'    => 8,
			'empresa'    => 9,
			'procenter'  => 10,
			'cuentas'    => 11,
			'backup'     => 12,
			'reportes'   => 13,
			'caja'       => 14,
			'inventario' => 15,
			'anular'     => 16,
			'precios'    => 17,
		);
	}

	/** Que habilita cada permiso (se muestra al asignarlos en Usuarios). */
	function descripcionesPermisos() {
		return array(
			'escritorio' => 'Ver el escritorio con ventas, utilidad y alertas del negocio.',
			'almacen'    => 'Crear y editar artículos, categorías, unidades, etiquetas e importar artículos.',
			'compras'    => 'Registrar compras y proveedores.',
			'ventas'     => 'Usar el punto de venta, cotizaciones y clientes. Sin "Consulta ventas" solo ve sus propias ventas.',
			'acceso'     => 'Administrador: todo el sistema, usuarios, permisos, eliminar documentos y ver todas las cajas.',
			'consultac'  => 'Ver todas las compras y los reportes de compras.',
			'consultav'  => 'Ver las ventas de todos los vendedores y los reportes de ventas.',
			'gestion'    => 'Sin uso por ahora (reservado).',
			'empresa'    => 'Cambiar datos de la empresa, logo, colores, series y ticket.',
			'procenter'  => 'Ver kardex y alertas de stock.',
			'cuentas'    => 'Cuentas por cobrar y por pagar: registrar abonos y pagos.',
			'backup'     => 'Crear y descargar copias de seguridad.',
			'reportes'   => 'Centro de reportes (ventas, compras, utilidad, inventario).',
			'caja'       => 'Abrir, registrar movimientos y cerrar su propia caja.',
			'inventario' => 'Ajustes de inventario (entradas y salidas) y vencimientos.',
			'anular'     => 'Anular ventas y compras (el stock se revierte). Recomendado solo para encargados.',
			'precios'    => 'Cambiar el precio y poner descuentos en ventas y cotizaciones. Sin este permiso se vende al precio de lista.',
		);
	}

	/**
	 * Plantillas de rol para el formulario de usuarios: marcan los permisos
	 * tipicos de cada puesto. Luego se pueden ajustar uno por uno.
	 */
	function plantillasRol() {
		return array(
			'vendedor' => array(
				'nombre' => 'Vendedor / cajero',
				'cargo' => 'Vendedor',
				'icono' => 'fa-shopping-cart',
				'descripcion' => 'Solo punto de venta y su propia caja. Ve únicamente sus ventas; vende a precio de lista; anula solo con clave de un encargado.',
				'permisos' => array('ventas', 'caja'),
			),
			'supervisor' => array(
				'nombre' => 'Encargado de tienda',
				'cargo' => 'Encargado',
				'icono' => 'fa-user-circle',
				'descripcion' => 'Vende, compra, ajusta stock, cambia precios, anula y autoriza anulaciones; ve todas las ventas, cuentas y reportes. No administra usuarios.',
				'permisos' => array('escritorio', 'almacen', 'compras', 'ventas', 'consultac', 'consultav', 'procenter', 'cuentas', 'reportes', 'caja', 'inventario', 'anular', 'precios'),
			),
			'almacenero' => array(
				'nombre' => 'Almacenero',
				'cargo' => 'Almacenero',
				'icono' => 'fa-cubes',
				'descripcion' => 'Artículos, ajustes de inventario, vencimientos y kardex.',
				'permisos' => array('almacen', 'inventario', 'procenter'),
			),
			'compras' => array(
				'nombre' => 'Compras',
				'cargo' => 'Compras',
				'icono' => 'fa-truck',
				'descripcion' => 'Registra compras y proveedores, paga cuentas por pagar y ve reportes de compras.',
				'permisos' => array('compras', 'consultac', 'cuentas'),
			),
			'admin' => array(
				'nombre' => 'Administrador',
				'cargo' => 'Administrador',
				'icono' => 'fa-shield',
				'descripcion' => 'Acceso total, incluidos usuarios, permisos, empresa y copias de seguridad.',
				'permisos' => array_keys(mapaPermisos()),
			),
		);
	}

	/**
	 * Primera pagina que puede abrir el usuario: el escritorio si lo tiene; si
	 * no, la de su trabajo (un vendedor entra directo al punto de venta).
	 * $prefijo es la ruta hasta vistas/ ('' desde vistas, 'vistas/' desde la raiz).
	 */
	function paginaInicioUsuario($prefijo = '') {
		$orden = array(
			'escritorio' => 'escritorio.php',
			'ventas'     => 'venta.php?nuevo=1',
			'compras'    => 'ingreso.php',
			'almacen'    => 'articulo.php',
			'inventario' => 'inventario.php',
			'caja'       => 'caja.php',
			'cuentas'    => 'cuentas.php',
			'consultav'  => 'reportes.php',
			'consultac'  => 'reportes.php',
			'procenter'  => 'procenter.php',
			'empresa'    => 'empresa.php',
			'backup'     => 'backup.php',
		);
		foreach ($orden as $permiso => $pagina) {
			if (usuarioTienePermiso($permiso)) {
				return $prefijo . $pagina;
			}
		}
		return $prefijo . 'usuario.php?perfil=1';
	}

	/**
	 * Autorizacion de un encargado con su usuario y clave (ej. anular desde el
	 * POS de un vendedor). El encargado debe estar activo y tener $permiso o ser
	 * administrador. Los fallos cuentan como intentos de login: la cuenta se
	 * bloquea igual que en la pantalla de acceso.
	 * Devuelve array(ok, mensaje, array usuario|null).
	 */
	function autorizacionEncargado($login, $clave, $permiso) {
		$login = substr(trim((string)$login), 0, 60);
		$clave = (string)$clave;
		if ($login === '' || $clave === '') {
			return array(false, 'Se necesita el usuario y la clave de un encargado.', null);
		}
		if (segundosBloqueoLogin($login) > 0) {
			return array(false, 'Ese usuario está bloqueado por intentos fallidos. Espera unos minutos.', null);
		}
		$fila = dbRow("SELECT idusuario, nombre, login, clave FROM usuario WHERE login=? AND condicion=1", array($login));
		list($ok) = $fila ? verificarClave($clave, $fila['clave']) : array(false);
		if (!$ok) {
			registrarIntentoLogin($login, false);
			return array(false, 'Usuario o clave del encargado incorrectos.', null);
		}
		$mapa = mapaPermisos();
		$tiene = (int)dbValue(
			"SELECT COUNT(*) FROM usuario_permiso WHERE idusuario=? AND idpermiso IN (?, ?)",
			array((int)$fila['idusuario'], (int)$mapa[$permiso], (int)$mapa['acceso']),
			0
		);
		if ($tiene === 0) {
			return array(false, $fila['nombre'] . ' no tiene permiso para autorizar esta acción.', null);
		}
		unset($fila['clave']);
		return array(true, '', $fila);
	}

	/** Ve las ventas de todos (encargado, admin) o solo las propias (vendedor). */
	function puedeVerTodasLasVentas() {
		return usuarioTienePermiso('acceso') || usuarioTienePermiso('consultav');
	}

	function esHttps() {
		if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
			return true;
		}
		if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
			return true;
		}
		return false;
	}

	function iniciarSesionSegura() {
		if (session_status() === PHP_SESSION_ACTIVE) {
			return;
		}
		if (headers_sent()) {
			@session_start();
			return;
		}
		session_name(SESSION_NAME);
		$params = array(
			'lifetime' => 0,
			'path'     => '/',
			'domain'   => '',
			'secure'   => esHttps(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		session_set_cookie_params($params);
		ini_set('session.use_strict_mode', '1');
		ini_set('session.use_only_cookies', '1');
		ini_set('session.cookie_httponly', '1');
		session_start();

		// Expiracion por inactividad
		$ttl = (int)SESSION_TTL_MINUTOS * 60;
		if ($ttl > 0 && isset($_SESSION['ultima_actividad']) && (time() - (int)$_SESSION['ultima_actividad']) > $ttl) {
			$_SESSION = array();
			session_destroy();
			session_start();
			$_SESSION['sesion_expirada'] = 1;
		}
		$_SESSION['ultima_actividad'] = time();

		// Regenerar id periodicamente (cada 30 min) para mitigar fijacion de sesion
		if (!isset($_SESSION['creada_en'])) {
			$_SESSION['creada_en'] = time();
		} elseif (time() - (int)$_SESSION['creada_en'] > 1800) {
			session_regenerate_id(true);
			$_SESSION['creada_en'] = time();
		}
	}

	function enviarCabecerasSeguridad() {
		if (headers_sent()) {
			return;
		}
		header('X-Frame-Options: SAMEORIGIN');
		header('X-Content-Type-Options: nosniff');
		header('Referrer-Policy: strict-origin-when-cross-origin');
		header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
		header('X-XSS-Protection: 0');
		if (esHttps()) {
			header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
		}
	}

	// ---------- CSRF ----------

	function csrfToken() {
		if (empty($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['csrf_token'];
	}

	function csrfTokenRecibido() {
		if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
			return (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
		}
		if (isset($_POST['_csrf'])) {
			return (string)$_POST['_csrf'];
		}
		return '';
	}

	/** Verifica CSRF en peticiones que modifican estado (POST/PUT/PATCH/DELETE). */
	function verificarCsrf($modoAjax = true) {
		$metodo = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
		if (!in_array($metodo, array('POST', 'PUT', 'PATCH', 'DELETE'), true)) {
			return true;
		}
		$esperado = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
		$recibido = csrfTokenRecibido();
		if ($esperado === '' || $recibido === '' || !hash_equals($esperado, $recibido)) {
			appLog('warning', 'CSRF invalido', array('uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
			responderNoAutorizado(419, 'Token de seguridad invalido o expirado. Recarga la pagina e intenta de nuevo.', $modoAjax);
		}
		return true;
	}

	// ---------- Respuestas ----------

	function esPeticionAjax() {
		return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
			|| (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
	}

	function urlLogin() {
		// Las vistas y los ajax estan un nivel por debajo de la raiz.
		return '../vistas/login.php';
	}

	function responderNoAutorizado($codigo, $mensaje, $modoAjax = true) {
		if ($modoAjax || esPeticionAjax()) {
			http_response_code((int)$codigo);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(array('ok' => false, 'codigo' => (int)$codigo, 'message' => $mensaje), JSON_UNESCAPED_UNICODE);
			exit;
		}
		if ((int)$codigo === 401) {
			header('Location: ' . urlLogin() . '?expirada=1');
			exit;
		}
		http_response_code((int)$codigo);
		echo '<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>Acceso denegado</h2><p>' . htmlspecialchars($mensaje) . '</p><a href="escritorio.php">Volver al escritorio</a></div>';
		exit;
	}

	// ---------- Autenticacion / autorizacion ----------

	function usuarioAutenticado() {
		return isset($_SESSION['idusuario']) && (int)$_SESSION['idusuario'] > 0;
	}

	/**
	 * Exige sesion iniciada. En modo ajax responde 401 JSON; si no, redirige al login.
	 * Tambien valida CSRF en peticiones de escritura.
	 */
	function requiereLogin($modoAjax = true) {
		iniciarSesionSegura();
		enviarCabecerasSeguridad();
		refrescarSesionUsuario();
		if (!usuarioAutenticado()) {
			responderNoAutorizado(401, 'Tu sesion ha expirado. Vuelve a iniciar sesion.', $modoAjax);
		}
		verificarCsrf($modoAjax);
		return true;
	}

	/**
	 * Relee de la BD el estado y los permisos del usuario en cada peticion:
	 * un permiso quitado o un usuario desactivado surte efecto de inmediato,
	 * sin esperar a que cierre sesion.
	 */
	function refrescarSesionUsuario() {
		if (!usuarioAutenticado()) {
			return;
		}
		$fila = dbRow("SELECT condicion FROM usuario WHERE idusuario=?", array((int)$_SESSION['idusuario']));
		if (!$fila || (int)$fila['condicion'] !== 1) {
			cerrarSesionUsuario();
			return;
		}
		$ids = array();
		foreach (dbAll("SELECT idpermiso FROM usuario_permiso WHERE idusuario=?", array((int)$_SESSION['idusuario'])) as $p) {
			$ids[] = (int)$p['idpermiso'];
		}
		aplicarPermisosSesion($ids);
	}

	/** Deja en sesion los permisos (y las claves antiguas $_SESSION['ventas']...). */
	function aplicarPermisosSesion(array $idsPermisos) {
		$_SESSION['permisos'] = array_values(array_unique(array_map('intval', $idsPermisos)));
		foreach (mapaPermisos() as $clave => $id) {
			$_SESSION[$clave] = in_array((int)$id, $_SESSION['permisos'], true) ? 1 : 0;
		}
		// Los administradores (acceso) tienen todo habilitado.
		if ($_SESSION['acceso'] === 1) {
			foreach (mapaPermisos() as $clave => $id) {
				$_SESSION[$clave] = 1;
				if (!in_array((int)$id, $_SESSION['permisos'], true)) {
					$_SESSION['permisos'][] = (int)$id;
				}
			}
		}
	}

	function usuarioTienePermiso($clave) {
		if (!usuarioAutenticado()) {
			return false;
		}
		$clave = strtolower(trim((string)$clave));
		if (!empty($_SESSION['permisos']) && is_array($_SESSION['permisos'])) {
			$mapa = mapaPermisos();
			if (isset($mapa[$clave]) && in_array((int)$mapa[$clave], $_SESSION['permisos'], true)) {
				return true;
			}
		}
		// Compatibilidad con las claves antiguas en sesion ($_SESSION['ventas']==1, etc.)
		return isset($_SESSION[$clave]) && (int)$_SESSION[$clave] === 1;
	}

	/**
	 * Exige al menos uno de los permisos indicados (string o array).
	 */
	function requierePermiso($claves, $modoAjax = true) {
		if (!is_array($claves)) {
			$claves = array($claves);
		}
		foreach ($claves as $c) {
			if (usuarioTienePermiso($c)) {
				return true;
			}
		}
		responderNoAutorizado(403, 'No tienes permiso para realizar esta accion.', $modoAjax);
		return false;
	}

	/**
	 * Carga en sesion los datos del usuario y sus permisos tras un login valido.
	 */
	function establecerSesionUsuario(array $usuario, array $idsPermisos) {
		session_regenerate_id(true);
		$_SESSION['idusuario'] = (int)$usuario['idusuario'];
		$_SESSION['nombre']    = $usuario['nombre'];
		$_SESSION['imagen']    = isset($usuario['imagen']) ? $usuario['imagen'] : '';
		$_SESSION['login']     = $usuario['login'];
		$_SESSION['cargo']     = isset($usuario['cargo']) ? $usuario['cargo'] : '';
		$_SESSION['creada_en'] = time();
		$_SESSION['ultima_actividad'] = time();
		aplicarPermisosSesion($idsPermisos);
		csrfToken();
	}

	function cerrarSesionUsuario() {
		$_SESSION = array();
		if (ini_get('session.use_cookies')) {
			$p = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
		}
		session_destroy();
	}

	// ---------- Fuerza bruta ----------

	function ipCliente() {
		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
		return substr((string)$ip, 0, 45);
	}

	/** Devuelve segundos restantes de bloqueo (0 si no esta bloqueado). */
	function segundosBloqueoLogin($login) {
		$ventana = (int)LOGIN_BLOQUEO_MINUTOS;
		$max = (int)LOGIN_MAX_INTENTOS;
		$row = dbRow(
			"SELECT COUNT(*) AS fallos, MAX(fecha_hora) AS ultimo
			 FROM intento_login
			 WHERE exito=0 AND fecha_hora >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
			 AND (login=? OR ip=?)",
			array($ventana, (string)$login, ipCliente())
		);
		if (!$row || (int)$row['fallos'] < $max) {
			return 0;
		}
		$ultimo = strtotime((string)$row['ultimo']);
		$restante = ($ultimo + $ventana * 60) - time();
		return $restante > 0 ? $restante : 0;
	}

	function registrarIntentoLogin($login, $exito) {
		dbExec(
			"INSERT INTO intento_login(login, ip, exito) VALUES(?,?,?)",
			array(substr((string)$login, 0, 60), ipCliente(), $exito ? 1 : 0)
		);
		if ($exito) {
			// Limpia fallos previos del mismo usuario/ip
			dbExec("DELETE FROM intento_login WHERE (login=? OR ip=?) AND exito=0", array(substr((string)$login, 0, 60), ipCliente()));
		}
		// Purga registros viejos (mas de 2 dias)
		if (mt_rand(1, 20) === 1) {
			dbExec("DELETE FROM intento_login WHERE fecha_hora < DATE_SUB(NOW(), INTERVAL 2 DAY)");
		}
	}

	// ---------- Contrasenas ----------

	function hashClave($clave) {
		return password_hash((string)$clave, PASSWORD_BCRYPT, array('cost' => 11));
	}

	/**
	 * Verifica la clave contra un hash bcrypt o, por compatibilidad, contra el
	 * antiguo SHA256 sin sal. Devuelve array(ok, necesitaRehash).
	 */
	function verificarClave($clave, $hashAlmacenado) {
		$hashAlmacenado = (string)$hashAlmacenado;
		if ($hashAlmacenado === '') {
			return array(false, false);
		}
		if (strlen($hashAlmacenado) === 64 && ctype_xdigit($hashAlmacenado)) {
			$ok = hash_equals(strtolower($hashAlmacenado), hash('sha256', (string)$clave));
			return array($ok, $ok);
		}
		$ok = password_verify((string)$clave, $hashAlmacenado);
		return array($ok, $ok && password_needs_rehash($hashAlmacenado, PASSWORD_BCRYPT, array('cost' => 11)));
	}

	function validarFortalezaClave($clave) {
		$clave = (string)$clave;
		if (strlen($clave) < 8) {
			return 'La contrasena debe tener al menos 8 caracteres.';
		}
		if (!preg_match('/[A-Za-z]/', $clave) || !preg_match('/\d/', $clave)) {
			return 'La contrasena debe combinar letras y numeros.';
		}
		return '';
	}

	// ---------- Auditoria ----------

	function registrarAuditoria($modulo, $accion, $detalle = '') {
		$idusuario = usuarioAutenticado() ? (int)$_SESSION['idusuario'] : null;
		$usuario = isset($_SESSION['login']) ? (string)$_SESSION['login'] : '';
		dbExec(
			"INSERT INTO auditoria(idusuario, usuario, modulo, accion, detalle, ip) VALUES(?,?,?,?,?,?)",
			array($idusuario, substr($usuario, 0, 60), substr((string)$modulo, 0, 40), substr((string)$accion, 0, 40), substr((string)$detalle, 0, 255), ipCliente())
		);
	}

	// ---------- Archivos subidos ----------

	/**
	 * Valida una imagen subida y la mueve a $carpetaDestino con nombre aleatorio.
	 * Devuelve array(ok, nombreArchivo|mensajeError).
	 */
	function guardarImagenSubida($campo, $carpetaDestino) {
		if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo])) {
			return array(false, 'No se recibio el archivo.');
		}
		$f = $_FILES[$campo];
		if (!isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
			return array(false, 'No se recibio el archivo.');
		}
		if ($f['error'] !== UPLOAD_ERR_OK) {
			return array(false, 'Error al subir el archivo (codigo ' . (int)$f['error'] . ').');
		}
		if (!is_uploaded_file($f['tmp_name'])) {
			return array(false, 'Archivo no valido.');
		}
		if ((int)$f['size'] > (int)UPLOAD_MAX_BYTES) {
			return array(false, 'La imagen supera el tamano maximo permitido (' . round(UPLOAD_MAX_BYTES / 1048576, 1) . ' MB).');
		}
		$permitidos = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		);
		$mime = '';
		if (function_exists('finfo_open')) {
			$fi = finfo_open(FILEINFO_MIME_TYPE);
			$mime = (string)finfo_file($fi, $f['tmp_name']);
			finfo_close($fi);
		} else {
			$info = @getimagesize($f['tmp_name']);
			$mime = $info && isset($info['mime']) ? (string)$info['mime'] : '';
		}
		if (!isset($permitidos[$mime])) {
			return array(false, 'Solo se permiten imagenes JPG, PNG, WEBP o GIF.');
		}
		if (@getimagesize($f['tmp_name']) === false) {
			return array(false, 'El archivo no es una imagen valida.');
		}
		if (!is_dir($carpetaDestino)) {
			@mkdir($carpetaDestino, 0775, true);
		}
		$nombre = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $permitidos[$mime];
		$destino = rtrim($carpetaDestino, '/\\') . DIRECTORY_SEPARATOR . $nombre;
		if (!move_uploaded_file($f['tmp_name'], $destino)) {
			return array(false, 'No se pudo guardar la imagen en el servidor.');
		}
		return array(true, $nombre);
	}

	/** Nombre de archivo seguro (sin rutas) o cadena vacia. */
	function nombreArchivoSeguro($nombre) {
		$nombre = basename(trim((string)$nombre));
		if ($nombre === '' || $nombre === '.' || $nombre === '..' || !preg_match('/^[A-Za-z0-9._-]+$/', $nombre)) {
			return '';
		}
		return $nombre;
	}

	// ---------- Salida ----------

	function e($valor) {
		return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
	}

	function responderJson($data, $codigo = 200) {
		http_response_code((int)$codigo);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
		exit;
	}
}
