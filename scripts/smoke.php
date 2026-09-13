<?php
/**
 * Prueba de humo automatizada (CLI).
 *
 * Levanta (o usa) un servidor PHP, crea un usuario QA temporal, recorre login, vistas,
 * endpoints y un flujo transaccional completo (caja, venta contado, venta crédito, abono,
 * ajuste, compra, anulaciones), verifica el stock y limpia todo lo creado.
 *
 * Uso:
 *   php scripts/smoke.php                       -> arranca php -S en 127.0.0.1:8099 y prueba
 *   php scripts/smoke.php http://localhost/mi_tienda   -> usa un servidor ya levantado
 *
 * Sale con codigo 0 si todo pasa, 1 si algo falla. Requiere ext-curl.
 */
if (php_sapi_name() !== 'cli') { exit("Solo CLI\n"); }
chdir(__DIR__ . '/../ajax');
require_once "../config/Conexion.php";

$base = isset($argv[1]) ? rtrim($argv[1], '/') : '';
$proc = null;
if ($base === '') {
	$base = 'http://127.0.0.1:8099';
	$cmd = '"' . PHP_BINARY . '" -S 127.0.0.1:8099 -t "' . realpath(__DIR__ . '/..') . '"';
	$proc = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('file', sys_get_temp_dir() . '/mt_smoke_server.log', 'a'), 2 => array('file', sys_get_temp_dir() . '/mt_smoke_server.log', 'a')), $pipes, null, null, array('bypass_shell' => true));
	if (isset($pipes[0])) { fclose($pipes[0]); }
	usleep(1500000);
}

$fallos = 0; $pasos = 0;
$jar = tempnam(sys_get_temp_dir(), 'mtjar');
$csrf = '';

function http($metodo, $url, $data = null, $headers = array()) {
	global $jar, $csrf;
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
		CURLOPT_HEADER => false, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false,
	));
	$h = $headers;
	if ($csrf !== '') { $h[] = 'X-CSRF-Token: ' . $csrf; }
	curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
	if ($metodo === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : (string)$data); }
	$body = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return array($code, (string)$body);
}
function check($nombre, $cond, $detalle = '') {
	global $fallos, $pasos;
	$pasos++;
	if ($cond) { echo "  [OK]   $nombre\n"; } else { $fallos++; echo "  [FALLO] $nombre" . ($detalle !== '' ? " -> " . substr(preg_replace('/\s+/', ' ', $detalle), 0, 160) : '') . "\n"; }
	return (bool)$cond;
}
function sinErroresPhp($body) { return !preg_match('/(Warning|Fatal error|Notice|Deprecated|Parse error):/', $body); }
function sql($q, $p = array()) { return dbRow($q, $p); }

// ---------- Usuario QA ----------
$login = 'qa_smoke_' . substr(bin2hex(random_bytes(3)), 0, 5);
$clave = 'Smoke' . mt_rand(1000, 9999) . 'x';
$idqa = dbInsert("INSERT INTO usuario(nombre,tipo_documento,num_documento,direccion,telefono,email,cargo,login,clave,imagen,condicion) VALUES('QA Smoke','DNI','','','','','QA',?,?,'',1)", array($login, password_hash($clave, PASSWORD_BCRYPT)));
dbExec("INSERT INTO usuario_permiso(idusuario,idpermiso) SELECT ?, idpermiso FROM permiso", array($idqa));
$art = dbRow("SELECT idarticulo, stock, precio_compra, precio_venta FROM articulo WHERE condicion=1 AND stock>=20 ORDER BY stock DESC LIMIT 1");
$cli = dbRow("SELECT idpersona FROM persona WHERE tipo_persona='Cliente' AND condicion=1 LIMIT 1");
$prov = dbRow("SELECT idpersona FROM persona WHERE tipo_persona='Proveedor' AND condicion=1 LIMIT 1");
echo "Base: $base · usuario QA: $login (id $idqa) · artículo: " . ($art ? $art['idarticulo'] : '-') . "\n";

$ids = array('venta' => array(), 'ingreso' => array());
try {
	// ---------- Login ----------
	echo "== Login\n";
	list($c, $b) = http('GET', "$base/vistas/login.php");
	check('login.php responde 200', $c === 200, "code=$c");
	preg_match('/id="csrf" value="([a-f0-9]+)"/', $b, $m);
	$csrf = isset($m[1]) ? $m[1] : '';
	check('token CSRF presente', $csrf !== '');
	list($c, $b) = http('POST', "$base/ajax/usuario.php?op=verificar", array('logina' => $login, 'clavea' => 'incorrecta1'));
	check('clave incorrecta rechazada', strpos($b, '"ok":false') !== false, $b);
	list($c, $b) = http('POST', "$base/ajax/usuario.php?op=verificar", array('logina' => $login, 'clavea' => $clave));
	check('login correcto', strpos($b, '"ok":true') !== false, $b);
	list($c, $b) = http('GET', "$base/vistas/escritorio.php");
	preg_match('/appCsrfToken = "([a-f0-9]+)"/', $b, $m);
	$csrf = isset($m[1]) ? $m[1] : $csrf;
	$csrfBak = $csrf; $csrf = 'x';
	list($c, $b) = http('POST', "$base/ajax/categoria.php?op=mostrar", array('idcategoria' => 1));
	check('POST sin CSRF válido devuelve 419', $c === 419, "code=$c");
	$csrf = $csrfBak;

	// ---------- Vistas ----------
	echo "== Vistas\n";
	foreach (array('escritorio', 'articulo', 'categoria', 'unidad', 'cliente', 'proveedor', 'venta', 'ingreso', 'caja', 'cuentas', 'inventario', 'procenter', 'reportes', 'comprasfecha', 'ventasfechacliente', 'usuario', 'permiso', 'empresa', 'backup', 'auditoria', 'etiquetas') as $v) {
		list($c, $b) = http('GET', "$base/vistas/$v.php");
		check("vista $v", $c === 200 && sinErroresPhp($b) && strlen($b) > 5000, "code=$c len=" . strlen($b));
	}

	// ---------- Endpoints ----------
	echo "== Endpoints\n";
	foreach (array('articulo.php?op=listar', 'categoria.php?op=listar', 'unidad.php?op=listar', 'persona.php?op=listarc', 'persona.php?op=listarp', 'venta.php?op=listar', 'ingreso.php?op=listar', 'caja.php?op=estado', 'cuentas.php?op=listarCobrar', 'cuentas.php?op=resumen', 'inventario.php?op=listar', 'procenter.php?op=alertaStock', 'consultas.php?op=dashboardAlertas', 'consultas.php?op=utilidadperiodo', 'usuario.php?op=listar', 'empresa.php?op=mostrar', 'backup.php?op=listar', 'auditoria.php?op=listar', 'articulo.php?op=catalogoEtiquetas') as $u) {
		list($c, $b) = http('GET', "$base/ajax/$u");
		check("ajax $u", $c === 200 && sinErroresPhp($b) && ($b[0] === '{' || $b[0] === '['), "code=$c " . substr($b, 0, 80));
	}

	// ---------- Flujo transaccional ----------
	if ($art && $cli && $prov) {
		echo "== Flujo transaccional\n";
		$a = (int)$art['idarticulo']; $stock0 = (float)$art['stock'];
		list($c, $b) = http('POST', "$base/ajax/caja.php?op=abrir", array('monto_apertura' => 100));
		check('abrir caja', strpos($b, 'correctamente') !== false, $b);

		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE', 'idarticulo' => array($a), 'cantidad' => array(2), 'precio_venta' => array(3.5), 'descuento' => array(0), 'total_venta' => 999));
		$r = json_decode($b, true);
		check('venta contado creada', $r && !empty($r['ok']), $b);
		if ($r && !empty($r['ok'])) { $ids['venta'][] = (int)$r['idventa']; }
		check('total recalculado en servidor (7.00)', $r && abs((float)$r['total'] - 7.0) < 0.001, $b);
		check('caja registró la venta', $r && !empty($r['caja_registrada']), $b);
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($a));
		check('stock descontado (-2)', abs((float)$s['stock'] - ($stock0 - 2)) < 0.001, 'stock=' . $s['stock']);

		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Factura', 'serie_comprobante' => 'F001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 18, 'tipo_pago' => 'CREDITO', 'medio_pago' => 'EFECTIVO', 'fecha_vencimiento' => date('Y-m-d', strtotime('+30 days')), 'observacion' => 'SMOKE', 'idarticulo' => array($a), 'cantidad' => array(3), 'precio_venta' => array(4), 'descuento' => array(0)));
		$r = json_decode($b, true);
		check('venta crédito creada con cuenta por cobrar', $r && !empty($r['ok']) && !empty($r['cuenta_cobrar']), $b);
		if ($r && !empty($r['ok'])) { $ids['venta'][] = (int)$r['idventa']; }
		$v2 = $r ? (int)$r['idventa'] : 0;
		$cc = sql("SELECT idcuenta_cobrar, saldo FROM cuenta_cobrar WHERE idventa=?", array($v2));
		list($c, $b) = http('POST', "$base/ajax/cuentas.php?op=abonarCobrar", array('idcuenta' => $cc ? $cc['idcuenta_cobrar'] : 0, 'monto' => 5, 'medio_pago' => 'EFECTIVO'));
		check('abono a cuenta por cobrar', strpos($b, 'correctamente') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", array('idventa' => $v2));
		check('anular venta con cobros se bloquea', stripos($b, 'cobros') !== false, $b);

		list($c, $b) = http('POST', "$base/ajax/inventario.php?op=registrar", array('idarticulo' => $a, 'tipo' => 'SALIDA', 'motivo' => 'MERMA', 'cantidad' => 5, 'observacion' => 'SMOKE'));
		check('ajuste de salida', strpos($b, '"ok":true') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/inventario.php?op=registrar", array('idarticulo' => $a, 'tipo' => 'SALIDA', 'motivo' => 'MERMA', 'cantidad' => 999999, 'observacion' => 'SMOKE'));
		check('ajuste excesivo rechazado', strpos($b, '"ok":false') !== false, $b);

		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=guardaryeditar", array('idproveedor' => $prov['idpersona'], 'tipo_comprobante' => 'Factura', 'serie_comprobante' => 'F001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 18, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE', 'idarticulo' => array($a), 'cantidad' => array(10), 'precio_compra' => array(1.2), 'precio_venta' => array(0)));
		$r = json_decode($b, true);
		check('compra contado creada', $r && !empty($r['ok']), $b);
		if ($r && !empty($r['ok'])) { $ids['ingreso'][] = (int)$r['idingreso']; }
		$i1 = $r ? (int)$r['idingreso'] : 0;
		$s = sql("SELECT stock, precio_compra FROM articulo WHERE idarticulo=?", array($a));
		check('stock tras compra (+10)', abs((float)$s['stock'] - ($stock0 - 2 - 3 - 5 + 10)) < 0.001, 'stock=' . $s['stock']);
		check('precio de compra actualizado a 1.20', abs((float)$s['precio_compra'] - 1.2) < 0.001, 'pc=' . $s['precio_compra']);

		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", array('idventa' => $ids['venta'][0]));
		check('anular venta contado', strpos($b, 'anulada') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=anular", array('idingreso' => $i1));
		check('anular compra', strpos($b, 'anulado') !== false, $b);
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($a));
		check('stock final = inicial - 3 (crédito) - 5 (merma)', abs((float)$s['stock'] - ($stock0 - 8)) < 0.001, 'stock=' . $s['stock']);
		$mov = sql("SELECT COUNT(*) n FROM caja_movimiento WHERE idcaja=(SELECT MAX(idcaja) FROM caja_diaria WHERE idusuario=?)", array($idqa));
		check('movimientos de caja generados (5)', (int)$mov['n'] === 5, 'n=' . $mov['n']);
		list($c, $b) = http('POST', "$base/ajax/caja.php?op=cerrar", array('monto_cierre_real' => 100));
		check('cerrar caja', strpos($b, 'correctamente') !== false, $b);

		echo "== Reportes\n";
		list($c, $b) = http('GET', "$base/reportes/exFactura.php?id=$v2");
		check('PDF factura', $c === 200 && substr($b, 0, 4) === '%PDF', substr($b, 0, 80));
		list($c, $b) = http('GET', "$base/reportes/exTicket.php?id=" . $ids['venta'][0]);
		check('ticket HTML', $c === 200 && sinErroresPhp($b) && strpos($b, 'ticket-shell') !== false, substr($b, 0, 80));
		list($c, $b) = http('GET', "$base/reportes/exIngreso.php?id=$i1");
		check('PDF compra', $c === 200 && substr($b, 0, 4) === '%PDF', substr($b, 0, 80));
		list($c, $b) = http('GET', "$base/reportes/rptarticulos.php");
		check('PDF artículos', $c === 200 && substr($b, 0, 4) === '%PDF', substr($b, 0, 80));
	} else {
		echo "  (sin artículo/cliente/proveedor disponibles: se omite el flujo transaccional)\n";
	}
} finally {
	// ---------- Limpieza ----------
	echo "== Limpieza\n";
	$c = db();
	$c->query("SET FOREIGN_KEY_CHECKS=0");
	if ($art) {
		foreach ($ids['venta'] as $v) {
			dbExec("DELETE FROM pago_cuenta_cobrar WHERE idcuenta_cobrar IN (SELECT idcuenta_cobrar FROM cuenta_cobrar WHERE idventa=?)", array($v));
			dbExec("DELETE FROM cuenta_cobrar WHERE idventa=?", array($v));
			dbExec("DELETE FROM detalle_venta WHERE idventa=?", array($v));
			dbExec("DELETE FROM venta WHERE idventa=?", array($v));
		}
		foreach ($ids['ingreso'] as $i) {
			dbExec("DELETE FROM cuenta_pagar WHERE idingreso=?", array($i));
			dbExec("DELETE FROM detalle_ingreso WHERE idingreso=?", array($i));
			dbExec("DELETE FROM ingreso WHERE idingreso=?", array($i));
		}
		dbExec("UPDATE articulo SET stock=?, precio_compra=?, precio_venta=? WHERE idarticulo=?", array((float)$art['stock'], (float)$art['precio_compra'], (float)$art['precio_venta'], (int)$art['idarticulo']));
	}
	dbExec("DELETE FROM ajuste_inventario WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM caja_movimiento WHERE idcaja IN (SELECT idcaja FROM caja_diaria WHERE idusuario=?)", array($idqa));
	dbExec("DELETE FROM caja_diaria WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM auditoria WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM intento_login WHERE login=?", array($login));
	dbExec("DELETE FROM usuario_permiso WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM usuario WHERE idusuario=?", array($idqa));
	$c->query("SET FOREIGN_KEY_CHECKS=1");
	@unlink($jar);
	if ($proc) {
		$st = proc_get_status($proc);
		if (stripos(PHP_OS, "WIN") === 0 && !empty($st["pid"])) { @exec("taskkill /F /T /PID " . (int)$st["pid"] . " >nul 2>&1"); }
		else { proc_terminate($proc, 9); }
		@proc_close($proc);
	}
	echo "  datos de prueba eliminados, stock restaurado\n";
}

echo str_repeat('-', 50) . "\n";
echo ($fallos === 0 ? "TODO OK" : "$fallos FALLO(S)") . " · $pasos comprobaciones\n";
exit($fallos === 0 ? 0 : 1);
