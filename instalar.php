<?php
/**
 * Instalador web de Mi Tienda.
 *
 * 1. Verifica requisitos del servidor.
 * 2. Conecta a MySQL, crea la base de datos (si no existe) y carga el esquema base.
 * 3. Crea el usuario administrador (bcrypt) y, opcionalmente, datos de demostracion.
 * 4. Escribe config/local.php y crea instalar.lock.
 *
 * Tras instalar, ELIMINA este archivo del servidor.
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

$raiz = __DIR__;
$lock = $raiz . '/instalar.lock';
$localCfg = $raiz . '/config/local.php';
$esquema = $raiz . '/scripts/sql/esquema_base.sql';
$demo = $raiz . '/scripts/sql/demo.sql';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_name('mitienda_instalador');
	session_start();
}
if (empty($_SESSION['inst_csrf'])) {
	$_SESSION['inst_csrf'] = bin2hex(random_bytes(16));
}

$bloqueado = file_exists($lock);
$errores = array();
$exito = false;
$resumen = array();

function reqOk($cond) { return $cond ? '<span class="ok">OK</span>' : '<span class="bad">Falta</span>'; }
function h($t) { return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); }

$requisitos = array(
	'PHP >= 8.1 (actual ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.1.0', '>='),
	'Extensión mysqli' => extension_loaded('mysqli'),
	'Extensión mbstring' => extension_loaded('mbstring'),
	'Extensión gd' => extension_loaded('gd'),
	'Extensión fileinfo' => extension_loaded('fileinfo'),
	'Carpeta config/ con permiso de escritura' => is_writable($raiz . '/config'),
	'Carpeta files/ con permiso de escritura' => is_writable($raiz . '/files'),
	'Carpeta logs/ existente o creable' => (is_dir($raiz . '/logs') ? is_writable($raiz . '/logs') : is_writable($raiz)),
	'Esquema base disponible (scripts/sql/esquema_base.sql)' => is_file($esquema),
);
$requisitosOk = !in_array(false, $requisitos, true);

function ejecutarSqlMulti(mysqli $c, string $sql): ?string {
	if (!$c->multi_query($sql)) {
		return $c->error;
	}
	do {
		if ($r = $c->store_result()) { $r->free(); }
		if ($c->errno) { return $c->error; }
	} while ($c->more_results() && $c->next_result());
	return $c->errno ? $c->error : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {
	$csrf = isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : '';
	if (!hash_equals($_SESSION['inst_csrf'], $csrf)) {
		$errores[] = 'Token de seguridad inválido. Recarga la página.';
	}
	$dbHost = trim((string)($_POST['db_host'] ?? 'localhost'));
	$dbPort = (int)($_POST['db_port'] ?? 0);
	$dbName = trim((string)($_POST['db_name'] ?? ''));
	$dbUser = trim((string)($_POST['db_user'] ?? ''));
	$dbPass = (string)($_POST['db_pass'] ?? '');
	$producto = trim((string)($_POST['producto'] ?? 'Mi Tienda'));
	$empresa = trim((string)($_POST['empresa'] ?? ''));
	$admNombre = trim((string)($_POST['adm_nombre'] ?? ''));
	$admLogin = trim((string)($_POST['adm_login'] ?? ''));
	$admClave = (string)($_POST['adm_clave'] ?? '');
	$admClave2 = (string)($_POST['adm_clave2'] ?? '');
	$cargarDemo = !empty($_POST['demo']);
	$entorno = ($_POST['entorno'] ?? 'production') === 'development' ? 'development' : 'production';

	if (!$requisitosOk) { $errores[] = 'Faltan requisitos del servidor (ver tabla).'; }
	if ($dbName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $dbName)) { $errores[] = 'Nombre de base de datos inválido (solo letras, números y _).'; }
	if ($dbUser === '') { $errores[] = 'Indica el usuario de MySQL.'; }
	if ($admNombre === '' || $admLogin === '') { $errores[] = 'Indica nombre y usuario del administrador.'; }
	if (!preg_match('/^[A-Za-z0-9._@-]{3,20}$/', $admLogin)) { $errores[] = 'El usuario del administrador debe tener 3 a 20 caracteres (letras, números, . _ @ -).'; }
	if (strlen($admClave) < 8 || !preg_match('/[A-Za-z]/', $admClave) || !preg_match('/\d/', $admClave)) { $errores[] = 'La contraseña del administrador debe tener al menos 8 caracteres con letras y números.'; }
	if ($admClave !== $admClave2) { $errores[] = 'Las contraseñas no coinciden.'; }

	if (!$errores) {
		mysqli_report(MYSQLI_REPORT_OFF);
		$c = @new mysqli($dbHost, $dbUser, $dbPass, '', $dbPort > 0 ? $dbPort : null);
		if ($c->connect_errno) {
			$errores[] = 'No se pudo conectar a MySQL: ' . $c->connect_error;
		} else {
			$c->set_charset('utf8mb4');
			if (!$c->query("CREATE DATABASE IF NOT EXISTS `" . $c->real_escape_string($dbName) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
				$errores[] = 'No se pudo crear la base de datos: ' . $c->error;
			} elseif (!$c->select_db($dbName)) {
				$errores[] = 'No se pudo seleccionar la base de datos: ' . $c->error;
			} else {
				$rs = $c->query("SHOW TABLES LIKE 'usuario'");
				$yaInstalada = $rs && $rs->num_rows > 0;
				if ($yaInstalada && (int)$c->query("SELECT COUNT(*) FROM usuario")->fetch_row()[0] > 0 && empty($_POST['forzar'])) {
					$errores[] = 'La base de datos "' . h($dbName) . '" ya contiene datos. Si realmente quieres reinstalar sobre ella, marca "Forzar" (no se borrarán tablas existentes; solo se crean las que falten).';
				} else {
					$err = ejecutarSqlMulti($c, str_replace('DROP TABLE IF EXISTS', '-- DROP TABLE IF EXISTS', file_get_contents($esquema)));
					// Si ya existian tablas, CREATE TABLE falla: reintentar ignorando "already exists"
					if ($err !== null && stripos($err, 'already exists') === false) {
						$errores[] = 'Error al cargar el esquema: ' . $err;
					} else {
						$resumen[] = 'Esquema base cargado (' . (int)$c->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetch_row()[0] . ' tablas).';
						if ($empresa !== '') {
							$st = $c->prepare("UPDATE configuracion_empresa SET nombre_comercial=?, razon_social=? WHERE idconfig=1");
							$st->bind_param('ss', $empresa, $empresa);
							$st->execute();
						}
						// Administrador
						$hash = password_hash($admClave, PASSWORD_BCRYPT, array('cost' => 11));
						$st = $c->prepare("SELECT idusuario FROM usuario WHERE login=?");
						$st->bind_param('s', $admLogin);
						$st->execute();
						$existe = $st->get_result()->fetch_assoc();
						if ($existe) {
							$idAdm = (int)$existe['idusuario'];
							$st = $c->prepare("UPDATE usuario SET nombre=?, clave=?, condicion=1 WHERE idusuario=?");
							$st->bind_param('ssi', $admNombre, $hash, $idAdm);
							$st->execute();
						} else {
							$st = $c->prepare("INSERT INTO usuario(nombre,tipo_documento,num_documento,direccion,telefono,email,cargo,login,clave,imagen,condicion) VALUES(?,'DNI','','','','','Administrador',?,?,'',1)");
							$st->bind_param('sss', $admNombre, $admLogin, $hash);
							$st->execute();
							$idAdm = (int)$c->insert_id;
						}
						$c->query("INSERT IGNORE INTO usuario_permiso(idusuario,idpermiso) SELECT " . $idAdm . ", idpermiso FROM permiso");
						$resumen[] = 'Administrador "' . h($admLogin) . '" creado con todos los permisos.';

						if ($cargarDemo && is_file($demo)) {
							$err = ejecutarSqlMulti($c, file_get_contents($demo));
							if ($err !== null) {
								$resumen[] = 'Datos de demo: error parcial (' . h($err) . ').';
							} else {
								$resumen[] = 'Datos de demostración cargados (artículos, clientes, proveedores, compras, ventas, caja).';
							}
						}

						// config/local.php
						$cfg = "<?php\n// Generado por instalar.php el " . date('Y-m-d H:i') . "\nreturn array(\n"
							. "\t'DB_HOST' => " . var_export($dbHost, true) . ",\n"
							. "\t'DB_NAME' => " . var_export($dbName, true) . ",\n"
							. "\t'DB_USERNAME' => " . var_export($dbUser, true) . ",\n"
							. "\t'DB_PASSWORD' => " . var_export($dbPass, true) . ",\n"
							. "\t'DB_PORT' => " . (int)$dbPort . ",\n"
							. "\t'PRO_NOMBRE' => " . var_export($producto !== '' ? $producto : 'Mi Tienda', true) . ",\n"
							. "\t'APP_ENV' => " . var_export($entorno, true) . ",\n"
							. ");\n";
						if (@file_put_contents($localCfg, $cfg) === false) {
							$errores[] = 'No se pudo escribir config/local.php. Créalo manualmente con este contenido:<pre>' . h($cfg) . '</pre>';
						} else {
							$resumen[] = 'config/local.php escrito.';
						}
						if (!is_dir($raiz . '/logs')) { @mkdir($raiz . '/logs', 0775, true); }
						@file_put_contents($lock, "Instalado el " . date('Y-m-d H:i:s') . "\n");
						$exito = empty($errores);
					}
				}
			}
			$c->close();
		}
	}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Instalador · Mi Tienda</title>
<link rel="icon" href="public/img/brand-store.svg">
<style>
  :root{--p:#0f766e;--pd:#0b4f4a;--line:#e2e8f0;--soft:#64748b}
  *{box-sizing:border-box} body{margin:0;font-family:"Segoe UI",Inter,system-ui,sans-serif;background:#f1f5f9;color:#0f172a;padding:32px 16px}
  .wrap{max-width:820px;margin:0 auto}
  .head{display:flex;align-items:center;gap:14px;margin-bottom:20px}
  .head img{width:52px;height:52px;border-radius:14px}
  h1{margin:0;font-size:24px} .head small{color:var(--soft)}
  .card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:16px;box-shadow:0 6px 20px rgba(15,23,42,.06)}
  h2{font-size:16px;margin:0 0 12px;display:flex;align-items:center;gap:8px}
  .step{display:inline-flex;width:26px;height:26px;border-radius:8px;background:var(--p);color:#fff;font-size:13px;align-items:center;justify-content:center;font-weight:700}
  table{width:100%;border-collapse:collapse;font-size:14px} td{padding:8px 4px;border-bottom:1px solid var(--line)} td:last-child{text-align:right;font-weight:700}
  .ok{color:#16a34a} .bad{color:#dc2626}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px} .grid .full{grid-column:1/-1}
  label{display:block;font-size:13px;font-weight:600;margin-bottom:5px;color:#334155}
  input[type=text],input[type=password],input[type=number],select{width:100%;height:40px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;font-size:14px;font-family:inherit}
  input:focus,select:focus{outline:0;border-color:var(--p);box-shadow:0 0 0 3px rgba(15,118,110,.15)}
  .help{font-size:12px;color:var(--soft);margin-top:4px}
  .btn{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--p),var(--pd));color:#fff;border:0;border-radius:12px;padding:12px 22px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit}
  .btn.sec{background:#fff;color:var(--p);border:1px solid var(--p)}
  .alert{border-radius:12px;padding:12px 14px;font-size:14px;margin-bottom:12px}
  .alert.err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca} .alert.okk{background:#dcfce7;color:#166534;border:1px solid #bbf7d0} .alert.warn{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
  ul{margin:6px 0 0 18px;padding:0} li{margin-bottom:4px}
  pre{background:#0f172a;color:#e2e8f0;padding:12px;border-radius:10px;overflow:auto;font-size:12px}
  @media(max-width:640px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="head"><img src="public/img/brand-store.svg" alt=""><div><h1>Instalador de Mi Tienda</h1><small>Configura la base de datos y el administrador en un minuto</small></div></div>

<?php if ($bloqueado) { ?>
  <div class="card">
    <div class="alert warn"><strong>El sistema ya fue instalado.</strong> Existe el archivo <code>instalar.lock</code>. Por seguridad, elimina <code>instalar.php</code> del servidor. Si necesitas reinstalar, borra <code>instalar.lock</code> primero.</div>
    <a class="btn" href="vistas/login.php">Ir al inicio de sesión</a>
  </div>
<?php } elseif ($exito) { ?>
  <div class="card">
    <div class="alert okk"><strong>¡Instalación completada!</strong></div>
    <ul><?php foreach ($resumen as $r) { echo '<li>' . $r . '</li>'; } ?></ul>
    <div class="alert warn" style="margin-top:14px"><strong>Importante:</strong> elimina ahora el archivo <code>instalar.php</code> del servidor.</div>
    <a class="btn" href="vistas/login.php">Ingresar al sistema</a>
    <a class="btn sec" href="index.php">Ver la landing</a>
  </div>
<?php } else { ?>
  <?php if ($errores) { ?><div class="alert err"><strong>No se pudo instalar:</strong><ul><?php foreach ($errores as $e) { echo '<li>' . $e . '</li>'; } ?></ul></div><?php } ?>
  <div class="card">
    <h2><span class="step">1</span> Requisitos del servidor</h2>
    <table><?php foreach ($requisitos as $k => $v) { echo '<tr><td>' . h($k) . '</td><td>' . reqOk($v) . '</td></tr>'; } ?></table>
  </div>
  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?php echo h($_SESSION['inst_csrf']); ?>">
    <div class="card">
      <h2><span class="step">2</span> Base de datos MySQL / MariaDB</h2>
      <div class="grid">
        <div><label>Servidor</label><input type="text" name="db_host" value="<?php echo h($_POST['db_host'] ?? 'localhost'); ?>"></div>
        <div><label>Puerto (0 = por defecto)</label><input type="number" name="db_port" value="<?php echo h($_POST['db_port'] ?? '0'); ?>" min="0" max="65535"></div>
        <div><label>Nombre de la base de datos</label><input type="text" name="db_name" value="<?php echo h($_POST['db_name'] ?? 'mi_tienda'); ?>"><div class="help">Se crea si no existe.</div></div>
        <div><label>Usuario</label><input type="text" name="db_user" value="<?php echo h($_POST['db_user'] ?? 'root'); ?>"></div>
        <div class="full"><label>Contraseña</label><input type="password" name="db_pass" value=""></div>
      </div>
    </div>
    <div class="card">
      <h2><span class="step">3</span> Tu negocio y el administrador</h2>
      <div class="grid">
        <div><label>Nombre del producto (marca del sistema)</label><input type="text" name="producto" value="<?php echo h($_POST['producto'] ?? 'Mi Tienda'); ?>"></div>
        <div><label>Nombre comercial de la empresa</label><input type="text" name="empresa" value="<?php echo h($_POST['empresa'] ?? ''); ?>" placeholder="Ferretería Los Andes"></div>
        <div><label>Nombre del administrador</label><input type="text" name="adm_nombre" value="<?php echo h($_POST['adm_nombre'] ?? ''); ?>"></div>
        <div><label>Usuario (login)</label><input type="text" name="adm_login" value="<?php echo h($_POST['adm_login'] ?? 'admin'); ?>"></div>
        <div><label>Contraseña</label><input type="password" name="adm_clave"><div class="help">Mínimo 8 caracteres, letras y números.</div></div>
        <div><label>Confirmar contraseña</label><input type="password" name="adm_clave2"></div>
        <div><label>Entorno</label><select name="entorno"><option value="production" <?php echo (($_POST['entorno'] ?? 'production') === 'production') ? 'selected' : ''; ?>>Producción (recomendado)</option><option value="development" <?php echo (($_POST['entorno'] ?? '') === 'development') ? 'selected' : ''; ?>>Desarrollo (muestra errores)</option></select></div>
        <div style="display:flex;flex-direction:column;justify-content:flex-end;gap:6px">
          <label style="display:flex;align-items:center;gap:8px;font-weight:500"><input type="checkbox" name="demo" value="1" <?php echo !empty($_POST['demo']) ? 'checked' : ''; ?>> Cargar datos de demostración (ferretería de ejemplo)</label>
          <label style="display:flex;align-items:center;gap:8px;font-weight:500"><input type="checkbox" name="forzar" value="1"> Forzar sobre una base con datos</label>
        </div>
      </div>
    </div>
    <button class="btn" type="submit" <?php echo $requisitosOk ? '' : 'disabled'; ?>>Instalar ahora</button>
  </form>
<?php } ?>
</div>
</body>
</html>
