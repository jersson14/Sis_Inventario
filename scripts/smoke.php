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
	check('administrador entra al escritorio', strpos($b, '"redirect":"escritorio.php"') !== false, $b);
	list($c, $b) = http('GET', "$base/vistas/escritorio.php");
	preg_match('/appCsrfToken = "([a-f0-9]+)"/', $b, $m);
	$csrf = isset($m[1]) ? $m[1] : $csrf;
	$csrfBak = $csrf; $csrf = 'x';
	list($c, $b) = http('POST', "$base/ajax/categoria.php?op=mostrar", array('idcategoria' => 1));
	check('POST sin CSRF válido devuelve 419', $c === 419, "code=$c");
	$csrf = $csrfBak;

	// ---------- Vistas ----------
	echo "== Vistas\n";
	foreach (array('escritorio', 'articulo', 'categoria', 'unidad', 'cliente', 'proveedor', 'venta', 'ingreso', 'caja', 'cuentas', 'inventario', 'procenter', 'reportes', 'comprasfecha', 'ventasfechacliente', 'usuario', 'permiso', 'empresa', 'backup', 'auditoria', 'etiquetas', 'cotizacion', 'importar') as $v) {
		list($c, $b) = http('GET', "$base/vistas/$v.php");
		check("vista $v", $c === 200 && sinErroresPhp($b) && strlen($b) > 5000, "code=$c len=" . strlen($b));
	}

	// ---------- Endpoints ----------
	echo "== Endpoints\n";
	foreach (array('articulo.php?op=listar', 'categoria.php?op=listar', 'unidad.php?op=listar', 'persona.php?op=listarc', 'persona.php?op=listarp', 'venta.php?op=listar', 'ingreso.php?op=listar', 'caja.php?op=estado', 'cuentas.php?op=listarCobrar', 'cuentas.php?op=resumen', 'inventario.php?op=listar', 'procenter.php?op=alertaStock', 'consultas.php?op=dashboardAlertas', 'consultas.php?op=utilidadperiodo', 'usuario.php?op=listar', 'empresa.php?op=mostrar', 'backup.php?op=listar', 'auditoria.php?op=listar', 'articulo.php?op=catalogoEtiquetas', 'cotizacion.php?op=listar', 'cotizacion.php?op=resumen', 'cotizacion.php?op=siguienteNumero') as $u) {
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
		// ---------- Borrado definitivo ----------
		echo "== Eliminación de documentos
";
		$sPrev = (float)sql("SELECT stock FROM articulo WHERE idarticulo=?", array($a))['stock'];
		// Boletas y facturas no se eliminan: dejarian un hueco en la numeracion
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=eliminar", array('idventa' => $v2));
		check('eliminar una factura se bloquea (numeración sin huecos)', stripos($b, 'hueco') !== false, $b);

		// Compra vigente: al eliminarla hay que descontar lo que habia ingresado
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=guardaryeditar", array('idproveedor' => $prov['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE DEL', 'idarticulo' => array($a), 'cantidad' => array(7), 'precio_compra' => array(2), 'precio_venta' => array(0)));
		$r = json_decode($b, true);
		check('compra temporal creada', $r && !empty($r['ok']), $b);
		$i2 = $r ? (int)$r['idingreso'] : 0;
		if ($i2 > 0) { $ids['ingreso'][] = $i2; }
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($a));
		check('stock tras compra temporal (+7)', abs((float)$s['stock'] - ($sPrev + 7)) < 0.001, 'stock=' . $s['stock']);
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=eliminar", array('idingreso' => $i2));
		check('eliminar compra vigente', stripos($b, 'eliminada') !== false, $b);
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($a));
		check('stock descontado al eliminar la compra', abs((float)$s['stock'] - $sPrev) < 0.001, 'stock=' . $s['stock']);
		$n = sql("SELECT COUNT(*) n FROM ingreso WHERE idingreso=?", array($i2));
		check('cabecera de compra borrada', (int)$n['n'] === 0, 'n=' . $n['n']);
		$n = sql("SELECT COUNT(*) n FROM detalle_ingreso WHERE idingreso=?", array($i2));
		check('detalle de compra borrado', (int)$n['n'] === 0, 'n=' . $n['n']);
		$n = sql("SELECT COUNT(*) n FROM caja_movimiento WHERE referencia=?", array('C-' . $i2));
		check('movimiento de caja de la compra borrado', (int)$n['n'] === 0, 'n=' . $n['n']);

		// Nota de venta (Ticket) vigente: al eliminarla el stock vuelve al inventario
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Ticket', 'serie_comprobante' => 'T001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE DEL', 'idarticulo' => array($a), 'cantidad' => array(2), 'precio_venta' => array(5), 'descuento' => array(0)));
		$r = json_decode($b, true);
		check('venta temporal creada', $r && !empty($r['ok']), $b);
		$v3 = $r ? (int)$r['idventa'] : 0;
		if ($v3 > 0) { $ids['venta'][] = $v3; }
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=eliminar", array('idventa' => $v3));
		check('eliminar venta vigente', stripos($b, 'eliminada') !== false, $b);
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($a));
		check('stock devuelto al eliminar la venta', abs((float)$s['stock'] - $sPrev) < 0.001, 'stock=' . $s['stock']);

		// Documento ya anulado: eliminar no debe mover el stock por segunda vez
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Ticket', 'serie_comprobante' => 'T001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE DEL2', 'idarticulo' => array($a), 'cantidad' => array(4), 'precio_venta' => array(5), 'descuento' => array(0)));
		$r = json_decode($b, true);
		$v4 = $r ? (int)$r['idventa'] : 0;
		if ($v4 > 0) { $ids['venta'][] = $v4; }
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", array('idventa' => $v4));
		check('anular venta temporal', stripos($b, 'anulada') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=eliminar", array('idventa' => $v4));
		check('eliminar venta ya anulada', stripos($b, 'eliminada') !== false, $b);
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($a));
		check('sin doble devolución de stock en documento anulado', abs((float)$s['stock'] - $sPrev) < 0.001, 'stock=' . $s['stock']);

		list($c, $b) = http('POST', "$base/ajax/caja.php?op=cerrar", array('monto_cierre_real' => 100));
		check('cerrar caja', strpos($b, 'correctamente') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=eliminar", array('idingreso' => $i1));
		check('eliminar documento de caja cerrada se bloquea', stripos($b, 'caja ya cerrada') !== false, $b);

		// ---------- Rubro ferreteria: fracciones, presentaciones, precio por mayor ----------
		echo "== Ferretería\n";
		$rubroOriginal = (string)dbValue("SELECT tipo_negocio FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1", array(), 'GENERAL');
		dbExec("UPDATE configuracion_empresa SET tipo_negocio='FERRETERIA'");
		$sufijo = substr(bin2hex(random_bytes(3)), 0, 5);

		// Unidad con decimales, creada por la pantalla de unidades
		list($c, $b) = http('POST', "$base/ajax/unidad.php?op=guardaryeditar", array('nombre' => 'QA Metro ' . $sufijo, 'abreviatura' => 'qm' . substr($sufijo, 0, 3), 'descripcion' => 'SMOKE', 'permite_fraccion' => '1'));
		check('unidad con decimales creada', stripos($b, 'correctamente') !== false, $b);
		$qaUnidad = sql("SELECT idunidad, permite_fraccion FROM unidad_medida WHERE nombre=?", array('QA Metro ' . $sufijo));
		$ids['unidad'] = $qaUnidad ? (int)$qaUnidad['idunidad'] : 0;
		check('unidad guarda permite_fraccion', $qaUnidad && (int)$qaUnidad['permite_fraccion'] === 1, json_encode($qaUnidad));

		// Articulo con presentacion "Rollo x50" y precio por mayor desde 20
		$qaNombre = 'QA Cable ' . $sufijo;
		$qaCodigoRollo = 'QAROLLO' . $sufijo;
		$datosArticulo = array(
			'nombre' => $qaNombre, 'idcategoria' => (int)dbValue("SELECT idcategoria FROM categoria WHERE condicion=1 ORDER BY idcategoria LIMIT 1", array(), 0), 'idunidad' => $ids['unidad'], 'codigo' => 'QACABLE' . $sufijo,
			'stock' => '100', 'stock_minimo' => '5.5', 'precio_compra' => '1.00', 'precio_venta' => '2.50', 'descripcion' => 'SMOKE',
			'pres_enviadas' => '1', 'pres_id' => array('0'), 'pres_nombre' => array('Rollo x50'), 'pres_factor' => array('50'),
			'pres_precio_venta' => array('110.00'), 'pres_precio_compra' => array('45.00'), 'pres_codigo' => array($qaCodigoRollo),
			'escalas_enviadas' => '1', 'escala_cantidad' => array('20'), 'escala_precio' => array('2.00')
		);
		$malo = $datosArticulo; $malo['nombre'] .= ' X'; $malo['codigo'] .= 'X'; $malo['pres_factor'] = array('1'); $malo['pres_codigo'] = array('');
		list($c, $b) = http('POST', "$base/ajax/articulo.php?op=guardaryeditar", $malo);
		check('presentación con factor 1 rechazada', stripos($b, 'igual a la unidad base') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/articulo.php?op=guardaryeditar", $datosArticulo);
		check('artículo con presentación y precio por mayor creado', stripos($b, 'correctamente') !== false, $b);
		$qa = sql("SELECT idarticulo, stock, stock_minimo FROM articulo WHERE nombre=?", array($qaNombre));
		$qaId = $qa ? (int)$qa['idarticulo'] : 0;
		$ids['articulo'] = $qaId;
		check('stock mínimo con decimales guardado (5.5)', $qa && abs((float)$qa['stock_minimo'] - 5.5) < 0.001, json_encode($qa));
		list($c, $b) = http('POST', "$base/ajax/articulo.php?op=mostrar", array('idarticulo' => $qaId));
		$m = json_decode($b, true);
		check('ficha trae presentación, escala y fracción', $m && count($m['presentaciones']) === 1 && count($m['escalas']) === 1 && !empty($m['permite_fraccion']), substr($b, 0, 160));
		$idRollo = $m ? (int)$m['presentaciones'][0]['idpresentacion'] : 0;

		// Reeditar conservando la presentacion (mismo id) no la duplica
		$edit = $datosArticulo; $edit['idarticulo'] = $qaId; $edit['pres_id'] = array((string)$idRollo); $edit['pres_precio_venta'] = array('115.00');
		list($c, $b) = http('POST', "$base/ajax/articulo.php?op=guardaryeditar", $edit);
		$n = sql("SELECT COUNT(*) n, MAX(precio_venta) pv FROM articulo_presentacion WHERE idarticulo=? AND condicion=1", array($qaId));
		check('editar presentación no la duplica', stripos($b, 'correctamente') !== false && (int)$n['n'] === 1 && abs((float)$n['pv'] - 115) < 0.001, $b . ' ' . json_encode($n));

		list($c, $b) = http('POST', "$base/ajax/venta.php?op=buscarArticuloCodigo", array('codigo' => $qaCodigoRollo));
		$r = json_decode($b, true);
		check('código de barras de la presentación la selecciona', $r && !empty($r['ok']) && (int)$r['idpresentacion'] === $idRollo, substr($b, 0, 160));

		// Venta: 1.5 m sueltos + 1 rollo de 50 m
		$ventaFerr = array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE FERR',
			'idarticulo' => array($qaId, $qaId), 'idpresentacion' => array(0, $idRollo), 'cantidad' => array('1.5', '1'), 'precio_venta' => array('2.50', '115.00'), 'descuento' => array(0, 0));
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", $ventaFerr);
		$r = json_decode($b, true);
		check('venta con fracción y presentación', $r && !empty($r['ok']), $b);
		$vFerr = $r ? (int)$r['idventa'] : 0;
		if ($vFerr > 0) { $ids['venta'][] = $vFerr; }
		check('total exacto (1.5 x 2.50 + 115.00 = 118.75)', $r && abs((float)$r['total'] - 118.75) < 0.001, $b);
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($qaId));
		check('stock en unidades base (100 - 1.5 - 50 = 48.5)', abs((float)$s['stock'] - 48.5) < 0.001, 'stock=' . $s['stock']);
		$d = sql("SELECT factor, cantidad FROM detalle_venta WHERE idventa=? AND idpresentacion=?", array($vFerr, $idRollo));
		check('detalle guarda la presentación con su factor', $d && abs((float)$d['factor'] - 50) < 0.001 && abs((float)$d['cantidad'] - 1) < 0.001, json_encode($d));

		$sinStock = $ventaFerr; $sinStock['idarticulo'] = array($qaId); $sinStock['idpresentacion'] = array($idRollo); $sinStock['cantidad'] = array('1'); $sinStock['precio_venta'] = array('115'); $sinStock['descuento'] = array(0);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", $sinStock);
		check('rollo de 50 m con 48.5 m en stock se rechaza', stripos($b, 'Stock insuficiente') !== false, $b);

		list($c, $b) = http('GET', "$base/reportes/exTicket.php?id=$vFerr");
		check('ticket muestra la presentación y la fracción', $c === 200 && strpos($b, 'Rollo x50') !== false && strpos($b, '1.5') !== false, substr(strip_tags($b), 0, 120));
		list($c, $b) = http('GET', "$base/ajax/procenter.php?op=kardex&idarticulo=$qaId");
		check('kardex responde con presentaciones', $c === 200 && sinErroresPhp($b) && strpos($b, '"ok":true') !== false, substr($b, 0, 120));

		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", array('idventa' => $vFerr));
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($qaId));
		check('anular devuelve 51.5 m (fracción + rollo)', stripos($b, 'anulada') !== false && abs((float)$s['stock'] - 100) < 0.001, $b . ' stock=' . $s['stock']);

		// Compra: 2 rollos a 90.00 cada uno
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=guardaryeditar", array('idproveedor' => $prov['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE FERR',
			'idarticulo' => array($qaId), 'idpresentacion' => array($idRollo), 'cantidad' => array('2'), 'precio_compra' => array('90.00'), 'precio_venta' => array('0')));
		$r = json_decode($b, true);
		check('compra por presentación', $r && !empty($r['ok']), $b);
		$iFerr = $r ? (int)$r['idingreso'] : 0;
		if ($iFerr > 0) { $ids['ingreso'][] = $iFerr; }
		$s = sql("SELECT a.stock, a.precio_compra, p.precio_compra AS pc_rollo FROM articulo a INNER JOIN articulo_presentacion p ON p.idarticulo=a.idarticulo WHERE a.idarticulo=? AND p.idpresentacion=?", array($qaId, $idRollo));
		check('compra suma 100 m y ajusta costos (rollo 90.00, metro 1.80)', $s && abs((float)$s['stock'] - 200) < 0.001 && abs((float)$s['pc_rollo'] - 90) < 0.001 && abs((float)$s['precio_compra'] - 1.8) < 0.001, json_encode($s));
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=eliminar", array('idingreso' => $iFerr));
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($qaId));
		check('eliminar compra por presentación descuenta 100 m', stripos($b, 'eliminada') !== false && abs((float)$s['stock'] - 100) < 0.001, $b . ' stock=' . $s['stock']);

		// Ajuste de inventario con decimales
		list($c, $b) = http('POST', "$base/ajax/inventario.php?op=registrar", array('idarticulo' => $qaId, 'tipo' => 'SALIDA', 'motivo' => 'MERMA', 'cantidad' => '0.25', 'observacion' => 'SMOKE'));
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($qaId));
		check('ajuste de salida de 0.25 m', strpos($b, '"ok":true') !== false && abs((float)$s['stock'] - 99.75) < 0.001, $b . ' stock=' . $s['stock']);

		// Cotizacion con presentacion
		list($c, $b) = http('POST', "$base/ajax/cotizacion.php?op=guardar", array('idcliente' => $cli['idpersona'], 'fecha_hora' => date('Y-m-d\TH:i'), 'fecha_validez' => date('Y-m-d', strtotime('+10 days')), 'impuesto' => 0, 'observacion' => 'SMOKE',
			'idarticulo' => array($qaId, $qaId), 'idpresentacion' => array($idRollo, 0), 'cantidad' => array('3', '2.75'), 'precio' => array('110', '2.50'), 'descuento' => array('0', '0')));
		$r = json_decode($b, true);
		check('cotización con presentación y fracción', $r && !empty($r['ok']) && abs((float)$r['total'] - 336.88) < 0.001, $b);
		$ids['cotizacion'] = $r && !empty($r['idcotizacion']) ? (int)$r['idcotizacion'] : 0;
		list($c, $b) = http('GET', "$base/ajax/cotizacion.php?op=paraVenta&id=" . $ids['cotizacion']);
		$r = json_decode($b, true);
		check('cotización lleva la presentación a la venta', $r && !empty($r['ok']) && (int)$r['items'][0]['idpresentacion'] === $idRollo && abs((float)$r['items'][1]['cantidad'] - 2.75) < 0.001, substr($b, 0, 160));

		// Con otro rubro las capacidades se apagan en el servidor
		dbExec("UPDATE configuracion_empresa SET tipo_negocio='GENERAL'");
		$general = $sinStock; $general['cantidad'] = array('1'); $general['idpresentacion'] = array($idRollo);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", $general);
		check('rubro GENERAL rechaza presentaciones', stripos($b, 'presentaciones') !== false, $b);
		$general['idpresentacion'] = array(0); $general['cantidad'] = array('1.4'); $general['precio_venta'] = array('2.50');
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", $general);
		$r = json_decode($b, true);
		if ($r && !empty($r['ok'])) { $ids['venta'][] = (int)$r['idventa']; }
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($qaId));
		check('rubro GENERAL vende en enteros (1.4 → 1)', $r && !empty($r['ok']) && abs((float)$s['stock'] - 98.75) < 0.001, $b . ' stock=' . $s['stock']);
		dbExec("UPDATE configuracion_empresa SET tipo_negocio=?", array($rubroOriginal));

		// ---------- Rubro abarrotes: lotes, vencimientos y FEFO ----------
		echo "== Abarrotes\n";
		if (!isset($rubroOriginal)) {
			$rubroOriginal = (string)dbValue("SELECT tipo_negocio FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1", array(), 'GENERAL');
		}
		dbExec("UPDATE configuracion_empresa SET tipo_negocio='ABARROTES'");
		$sufAb = substr(bin2hex(random_bytes(3)), 0, 5);
		$idUnd = (int)dbValue("SELECT idunidad FROM unidad_medida WHERE abreviatura='und' LIMIT 1", array(), 0);
		$idCat = (int)dbValue("SELECT idcategoria FROM categoria WHERE condicion=1 ORDER BY idcategoria LIMIT 1", array(), 0);
		$nombreLeche = 'QA Leche ' . $sufAb;
		list($c, $b) = http('POST', "$base/ajax/articulo.php?op=guardaryeditar", array('nombre' => $nombreLeche, 'idcategoria' => $idCat, 'idunidad' => $idUnd, 'codigo' => 'QALECHE' . $sufAb, 'stock' => '10', 'stock_minimo' => '2', 'precio_compra' => '3.00', 'precio_venta' => '4.50', 'descripcion' => 'SMOKE'));
		$leche = (int)dbValue("SELECT idarticulo FROM articulo WHERE nombre=?", array($nombreLeche), 0);
		$ids['articulo_ab'] = $leche;
		check('artículo de abarrotes con 10 und sin lote', $leche > 0, $b);

		$hoy = date('Y-m-d');
		$compraLote = function ($cantidad, $codigoLote, $vence) use ($base, $prov, $leche, &$ids) {
			list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=guardaryeditar", array('idproveedor' => $prov['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE AB',
				'idarticulo' => array($leche), 'idpresentacion' => array(0), 'cantidad' => array((string)$cantidad), 'precio_compra' => array('3.00'), 'precio_venta' => array('0'), 'lote_codigo' => array($codigoLote), 'lote_vencimiento' => array($vence)));
			$r = json_decode($b, true);
			if ($r && !empty($r['ok'])) { $ids['ingreso'][] = (int)$r['idingreso']; }
			return array($r, $b);
		};
		$loteStock = function ($codigo) use ($leche) {
			$v = dbValue("SELECT stock FROM lote WHERE idarticulo=? AND codigo_lote=? AND condicion=1", array($leche, $codigo), null);
			return $v === null ? null : round((float)$v, 3);
		};
		$stockLeche = function () use ($leche) { return round((float)dbValue("SELECT stock FROM articulo WHERE idarticulo=?", array($leche), 0), 3); };
		$ventaLeche = function ($cantidad) use ($base, $cli, $leche, &$ids) {
			list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Ticket', 'serie_comprobante' => 'T001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE AB',
				'idarticulo' => array($leche), 'idpresentacion' => array(0), 'cantidad' => array((string)$cantidad), 'precio_venta' => array('4.50'), 'descuento' => array(0)));
			$r = json_decode($b, true);
			if ($r && !empty($r['ok'])) { $ids['venta'][] = (int)$r['idventa']; }
			return array($r, $b);
		};

		list($r, $b) = $compraLote(20, 'L-A' . $sufAb, date('Y-m-d', strtotime('+10 days')));
		$iA = $r ? (int)$r['idingreso'] : 0;
		check('compra con lote A (20, vence en 10 días)', $r && !empty($r['ok']) && $loteStock('L-A' . $sufAb) == 20, $b);
		list($r, $b) = $compraLote(15, 'L-B' . $sufAb, date('Y-m-d', strtotime('+40 days')));
		$iB = $r ? (int)$r['idingreso'] : 0;
		check('compra con lote B (15, vence en 40 días)', $r && !empty($r['ok']) && $loteStock('L-B' . $sufAb) == 15, $b);
		list($r, $b) = $compraLote(5, 'L-X' . $sufAb, date('Y-m-d', strtotime('-1 days')));
		check('compra con vencimiento anterior a la compra se rechaza', stripos($b, 'anterior a la fecha de la compra') !== false, $b);

		list($c, $b) = http('POST', "$base/ajax/inventario.php?op=registrar", array('idarticulo' => $leche, 'tipo' => 'ENTRADA', 'motivo' => 'DEVOLUCION_CLIENTE', 'cantidad' => '5', 'observacion' => 'SMOKE', 'lote_codigo' => 'L-V' . $sufAb, 'lote_vencimiento' => date('Y-m-d', strtotime('-3 days'))));
		check('entrada por ajuste crea lote V ya vencido (5)', strpos($b, '"ok":true') !== false && $loteStock('L-V' . $sufAb) == 5 && $stockLeche() == 50, $b . ' stock=' . $stockLeche());

		list($c, $b) = http('POST', "$base/ajax/venta.php?op=infoArticulo", array('idarticulo' => $leche));
		$f = json_decode($b, true);
		check('punto de venta: disponible 45 (5 vencidos no cuentan)', $f && abs($f['stock'] - 45) < 0.001 && abs($f['stock_vencido'] - 5) < 0.001, substr($b, 0, 200));
		check('punto de venta: próximo vencimiento es el lote A', $f && $f['proximo_vencimiento'] && $f['proximo_vencimiento']['codigo_lote'] === 'L-A' . $sufAb, substr($b, 0, 200));

		list($r, $b) = $ventaLeche(48);
		check('vender 48 con 45 disponibles se rechaza (vencido no se vende)', stripos($b, 'vencido: 5') !== false, $b);
		list($r, $b) = $ventaLeche(25);
		$vAb = $r ? (int)$r['idventa'] : 0;
		check('venta de 25 sale en FEFO: A 20→0, B 15→10, V intacto', $r && !empty($r['ok']) && $loteStock('L-A' . $sufAb) == 0 && $loteStock('L-B' . $sufAb) == 10 && $loteStock('L-V' . $sufAb) == 5, $b . ' A=' . $loteStock('L-A' . $sufAb) . ' B=' . $loteStock('L-B' . $sufAb));
		$n = (int)dbValue("SELECT COUNT(*) FROM lote_movimiento WHERE idventa=?", array($vAb), 0);
		check('venta registra 2 movimientos de lote', $n === 2, 'n=' . $n);

		list($c, $b) = http('GET', "$base/ajax/lote.php?op=resumen&dias=45");
		$rs = json_decode($b, true);
		check('resumen: 1 lote vencido y 1 por vencer (45 días)', $rs && $rs['vencidos'] === 1 && $rs['por_vencer'] === 1, $b);
		list($c, $b) = http('GET', "$base/ajax/consultas.php?op=dashboardAlertas");
		$al = json_decode($b, true);
		check('alertas del sistema incluyen lotes vencidos', $al && (int)$al['usa_vencimientos'] === 1 && (int)$al['lotes_vencidos'] >= 1, substr($b, 0, 160));
		list($c, $b) = http('GET', "$base/ajax/lote.php?op=listar&estado=VENCIDO");
		check('listado de vencidos', $c === 200 && strpos($b, 'L-V' . $sufAb) !== false, substr($b, 0, 120));
		list($c, $b) = http('GET', "$base/vistas/vencimientos.php");
		check('vista vencimientos', $c === 200 && sinErroresPhp($b) && strpos($b, 'tbllotes') !== false, "code=$c");

		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=anular", array('idingreso' => $iA));
		check('anular compra A se bloquea (su lote ya se vendió)', stripos($b, 'ya salio') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", array('idventa' => $vAb));
		check('anular venta devuelve a los mismos lotes (A 20, B 15)', stripos($b, 'anulada') !== false && $loteStock('L-A' . $sufAb) == 20 && $loteStock('L-B' . $sufAb) == 15 && $stockLeche() == 50, $b . ' stock=' . $stockLeche());
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=anular", array('idingreso' => $iB));
		check('anular compra B (sin consumir) retira su lote', stripos($b, 'anulado') !== false && $loteStock('L-B' . $sufAb) === null && $stockLeche() == 35, $b . ' stock=' . $stockLeche());

		$idLoteV = (int)dbValue("SELECT idlote FROM lote WHERE codigo_lote=?", array('L-V' . $sufAb), 0);
		list($c, $b) = http('POST', "$base/ajax/lote.php?op=darBaja", array('idlote' => $idLoteV, 'motivo' => 'VENCIMIENTO'));
		check('dar de baja lote vencido', strpos($b, '"ok":true') !== false && $loteStock('L-V' . $sufAb) == 0 && $stockLeche() == 30, $b . ' stock=' . $stockLeche());

		list($c, $b) = http('POST', "$base/ajax/inventario.php?op=registrar", array('idarticulo' => $leche, 'tipo' => 'SALIDA', 'motivo' => 'MERMA', 'cantidad' => '12', 'observacion' => 'SMOKE'));
		check('salida por merma sale del lote que vence antes (A 20→8)', strpos($b, '"ok":true') !== false && $loteStock('L-A' . $sufAb) == 8 && $stockLeche() == 18, $b . ' A=' . $loteStock('L-A' . $sufAb));
		list($c, $b) = http('POST', "$base/ajax/inventario.php?op=registrar", array('idarticulo' => $leche, 'tipo' => 'SALIDA', 'motivo' => 'VENCIMIENTO', 'cantidad' => '9', 'idlote' => $idLoteV, 'observacion' => 'SMOKE'));
		check('baja mayor al stock del lote se rechaza sin tocar el stock', stripos($b, 'solo tiene') !== false && $stockLeche() == 18, $b . ' stock=' . $stockLeche());

		list($r, $b) = $ventaLeche(15);
		$vAb2 = $r ? (int)$r['idventa'] : 0;
		check('venta de 15: 8 del lote A y 7 del stock sin lote', $r && !empty($r['ok']) && $loteStock('L-A' . $sufAb) == 0 && $stockLeche() == 3, $b . ' stock=' . $stockLeche());
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=eliminar", array('idventa' => $vAb2));
		check('eliminar venta vigente devuelve 8 al lote A', stripos($b, 'eliminada') !== false && $loteStock('L-A' . $sufAb) == 8 && $stockLeche() == 18, $b . ' stock=' . $stockLeche());

		list($c, $b) = http('POST', "$base/ajax/articulo.php?op=guardaryeditar", array('idarticulo' => $leche, 'nombre' => $nombreLeche, 'idcategoria' => $idCat, 'idunidad' => $idUnd, 'codigo' => 'QALECHE' . $sufAb, 'stock' => '5', 'stock_minimo' => '2', 'precio_compra' => '3.00', 'precio_venta' => '4.50', 'descripcion' => 'SMOKE'));
		check('editar stock a mano a 5 recorta el lote A a 5', stripos($b, 'correctamente') !== false && $loteStock('L-A' . $sufAb) == 5, $b . ' A=' . $loteStock('L-A' . $sufAb));

		dbExec("UPDATE configuracion_empresa SET tipo_negocio='GENERAL'");
		list($r, $b) = $ventaLeche(2);
		check('rubro GENERAL: la venta no usa lotes pero los mantiene bajo el stock (A 5→3)', $r && !empty($r['ok']) && $stockLeche() == 3 && $loteStock('L-A' . $sufAb) == 3, $b . ' A=' . $loteStock('L-A' . $sufAb));
		list($c, $b) = http('GET', "$base/ajax/lote.php?op=resumen");
		check('rubro GENERAL: módulo de vencimientos inactivo', stripos($b, 'no usa control de vencimientos') !== false, $b);
		dbExec("UPDATE configuracion_empresa SET tipo_negocio=?", array($rubroOriginal));

		// ---------- Rubro ropa: tallas y colores con stock propio ----------
		echo "== Ropa\n";
		if (!isset($rubroOriginal)) {
			$rubroOriginal = (string)dbValue("SELECT tipo_negocio FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1", array(), 'GENERAL');
		}
		dbExec("UPDATE configuracion_empresa SET tipo_negocio='ROPA'");
		$sufRo = substr(bin2hex(random_bytes(3)), 0, 5);
		$idUndRo = (int)dbValue("SELECT idunidad FROM unidad_medida WHERE abreviatura='und' LIMIT 1", array(), 0);
		$idCatRo = (int)dbValue("SELECT idcategoria FROM categoria WHERE condicion=1 ORDER BY idcategoria LIMIT 1", array(), 0);
		$nomCatRo = html_entity_decode((string)dbValue("SELECT nombre FROM categoria WHERE idcategoria=?", array($idCatRo), ''), ENT_QUOTES, 'UTF-8');
		$nombrePolo = 'QA Polo ' . $sufRo;
		$codMN = 'QAPMN' . $sufRo;
		$ids['articulos_ropa'] = array();

		$fichaPolo = array(
			'nombre' => $nombrePolo, 'idcategoria' => $idCatRo, 'idunidad' => $idUndRo, 'codigo' => 'QAPOLO' . $sufRo, 'stock' => '0', 'stock_minimo' => '1',
			'precio_compra' => '20.00', 'precio_venta' => '40.00', 'descripcion' => 'SMOKE', 'temporada' => 'Verano 2026', 'coleccion' => 'Casual',
			'var_enviadas' => '1',
			'var_id' => array('0', '0', '0'), 'var_talla' => array('M', 'L', 'M'), 'var_color' => array('Negro', 'Negro', 'Blanco'),
			'var_codigo' => array($codMN, '', ''), 'var_stock' => array('10', '5', '0'), 'var_stock_minimo' => array('2', '1', '1'), 'var_precio' => array('0', '0', '45.00')
		);
		$dup = $fichaPolo; $dup['nombre'] .= ' D'; $dup['codigo'] .= 'D'; $dup['var_talla'] = array('M', 'M', 'L'); $dup['var_color'] = array('Negro', 'negro', 'Negro'); $dup['var_codigo'] = array('', '', '');
		list($c, $b) = http('POST', "$base/ajax/articulo.php?op=guardaryeditar", $dup);
		check('talla/color repetida en el formulario se rechaza', stripos($b, 'repetida') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/articulo.php?op=guardaryeditar", $fichaPolo);
		$polo = (int)dbValue("SELECT idarticulo FROM articulo WHERE nombre=?", array($nombrePolo), 0);
		if ($polo > 0) { $ids['articulos_ropa'][] = $polo; }
		$stockPolo = function () use ($polo) { return round((float)dbValue("SELECT stock FROM articulo WHERE idarticulo=?", array($polo), 0), 3); };
		$varStock = function ($talla, $color) use ($polo) { $v = dbValue("SELECT stock FROM articulo_variante WHERE idarticulo=? AND talla=? AND color=? AND condicion=1", array($polo, $talla, $color), null); return $v === null ? null : round((float)$v, 3); };
		$varId = function ($talla, $color) use ($polo) { return (int)dbValue("SELECT idvariante FROM articulo_variante WHERE idarticulo=? AND talla=? AND color=?", array($polo, $talla, $color), 0); };
		check('artículo con 3 tallas/colores: stock = suma (15)', stripos($b, 'correctamente') !== false && $stockPolo() == 15 && $varStock('M', 'Negro') == 10 && $varStock('L', 'Negro') == 5, $b . ' stock=' . $stockPolo());
		$tmp = dbRow("SELECT temporada, coleccion FROM articulo WHERE idarticulo=?", array($polo));
		check('temporada y colección guardadas', $tmp && $tmp['temporada'] === 'Verano 2026' && $tmp['coleccion'] === 'Casual', json_encode($tmp));
		$idMN = $varId('M', 'Negro'); $idLN = $varId('L', 'Negro'); $idMB = $varId('M', 'Blanco');

		list($c, $b) = http('POST', "$base/ajax/venta.php?op=buscarArticuloCodigo", array('codigo' => $codMN));
		$r = json_decode($b, true);
		check('código de la talla/color la selecciona', $r && !empty($r['ok']) && (int)$r['idvariante'] === $idMN && count($r['variantes']) === 3, substr($b, 0, 200));
		$precioMB = 0; foreach (($r ? $r['variantes'] : array()) as $vv) { if ((int)$vv['idvariante'] === $idMB) { $precioMB = $vv['precio_venta']; } }
		check('precio propio de la talla/color (M / Blanco 45.00)', abs($precioMB - 45) < 0.001, 'precio=' . $precioMB);

		$ventaPolo = function (array $lineas) use ($base, $cli, $polo, &$ids) {
			$d = array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE RO',
				'idarticulo' => array(), 'idpresentacion' => array(), 'idvariante' => array(), 'cantidad' => array(), 'precio_venta' => array(), 'descuento' => array());
			foreach ($lineas as $l) {
				$d['idarticulo'][] = isset($l[2]) ? $l[2] : $polo; $d['idpresentacion'][] = 0; $d['idvariante'][] = $l[0]; $d['cantidad'][] = (string)$l[1]; $d['precio_venta'][] = '40.00'; $d['descuento'][] = '0';
			}
			list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", $d);
			$r = json_decode($b, true);
			if ($r && !empty($r['ok'])) { $ids['venta'][] = (int)$r['idventa']; }
			return array($r, $b);
		};

		list($r, $b) = $ventaPolo(array(array(0, 1)));
		check('vender sin elegir talla/color se rechaza', stripos($b, 'Elige la talla') !== false, $b);
		list($r, $b) = $ventaPolo(array(array($idMN, 3), array($idLN, 1)));
		$vRo = $r ? (int)$r['idventa'] : 0;
		check('venta descuenta de cada talla/color (M/Negro 7, L/Negro 4, total 11)', $r && !empty($r['ok']) && $varStock('M', 'Negro') == 7 && $varStock('L', 'Negro') == 4 && $stockPolo() == 11, $b . ' stock=' . $stockPolo());
		list($r, $b) = $ventaPolo(array(array($idMN, 8)));
		check('pedir 8 de M/Negro con 7 se rechaza aunque el artículo tenga 11', empty($r['ok']) && stripos((string)($r['message'] ?? ''), 'M / Negro (disponible: 7') !== false, $b);
		$otroArt = (int)dbValue("SELECT idarticulo FROM articulo WHERE idarticulo<>? AND condicion=1 AND stock>=1 ORDER BY idarticulo LIMIT 1", array($polo), 0);
		list($r, $b) = $ventaPolo(array(array($idMN, 1, $otroArt)));
		check('talla/color de otro artículo se rechaza', stripos($b, 'no las tiene') !== false, $b);
		list($c, $b) = http('GET', "$base/reportes/exTicket.php?id=$vRo");
		check('ticket muestra la talla y el color', $c === 200 && strpos($b, 'M / Negro') !== false, substr(strip_tags($b), 0, 120));
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", array('idventa' => $vRo));
		check('anular devuelve a cada talla/color (M/Negro 10, L/Negro 5)', stripos($b, 'anulada') !== false && $varStock('M', 'Negro') == 10 && $varStock('L', 'Negro') == 5 && $stockPolo() == 15, $b . ' stock=' . $stockPolo());

		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=guardaryeditar", array('idproveedor' => $prov['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE RO',
			'idarticulo' => array($polo), 'idpresentacion' => array(0), 'idvariante' => array($idMB), 'cantidad' => array('6'), 'precio_compra' => array('20.00'), 'precio_venta' => array('0'), 'lote_codigo' => array(''), 'lote_vencimiento' => array('')));
		$r = json_decode($b, true);
		$iRo = $r && !empty($r['ok']) ? (int)$r['idingreso'] : 0;
		if ($iRo) { $ids['ingreso'][] = $iRo; }
		check('compra suma a M/Blanco (6) y al artículo (21)', $iRo > 0 && $varStock('M', 'Blanco') == 6 && $stockPolo() == 21, $b . ' stock=' . $stockPolo());
		list($r, $b) = $ventaPolo(array(array($idMB, 2)));
		$vRo2 = $r ? (int)$r['idventa'] : 0;
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=anular", array('idingreso' => $iRo));
		check('anular compra se bloquea si esa talla/color ya se vendió', stripos($b, 'M / Blanco ya fue utilizado') !== false && $varStock('M', 'Blanco') == 4, $b);
		http('POST', "$base/ajax/venta.php?op=anular", array('idventa' => $vRo2));
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=anular", array('idingreso' => $iRo));
		check('anular compra sin consumo resta de M/Blanco (0) y del artículo (15)', stripos($b, 'anulado') !== false && $varStock('M', 'Blanco') == 0 && $stockPolo() == 15, $b . ' stock=' . $stockPolo());

		list($c, $b) = http('POST', "$base/ajax/inventario.php?op=registrar", array('idarticulo' => $polo, 'tipo' => 'SALIDA', 'motivo' => 'MERMA', 'cantidad' => '2', 'observacion' => 'SMOKE'));
		check('ajuste sin talla/color se rechaza', stripos($b, 'Elige la talla') !== false && $stockPolo() == 15, $b);
		list($c, $b) = http('POST', "$base/ajax/inventario.php?op=registrar", array('idarticulo' => $polo, 'idvariante' => $idMN, 'tipo' => 'SALIDA', 'motivo' => 'MERMA', 'cantidad' => '2', 'observacion' => 'SMOKE'));
		check('ajuste de salida en M/Negro (8) y artículo (13)', strpos($b, '"ok":true') !== false && $varStock('M', 'Negro') == 8 && $stockPolo() == 13, $b . ' stock=' . $stockPolo());
		list($c, $b) = http('POST', "$base/ajax/inventario.php?op=registrar", array('idarticulo' => $polo, 'idvariante' => $idLN, 'tipo' => 'SALIDA', 'motivo' => 'MERMA', 'cantidad' => '8', 'observacion' => 'SMOKE'));
		check('salida de 8 en L/Negro (tiene 5, el artículo 13) se rechaza', stripos($b, 'solo tiene 5') !== false && $stockPolo() == 13, $b);

		// Quitar tallas desde la ficha
		$edit = $fichaPolo; $edit['idarticulo'] = $polo;
		$edit['var_id'] = array((string)$idMN, (string)$idMB); $edit['var_talla'] = array('M', 'M'); $edit['var_color'] = array('Negro', 'Blanco');
		$edit['var_codigo'] = array($codMN, ''); $edit['var_stock'] = array('0', '0'); $edit['var_stock_minimo'] = array('2', '1'); $edit['var_precio'] = array('0', '45.00');
		list($c, $b) = http('POST', "$base/ajax/articulo.php?op=guardaryeditar", $edit);
		check('no se puede quitar una talla/color con stock (L/Negro)', stripos($b, 'todavía tiene 5') !== false && $varStock('L', 'Negro') == 5, $b);
		$edit['var_id'] = array((string)$idMN, (string)$idLN, '0'); $edit['var_talla'] = array('M', 'L', 'XL'); $edit['var_color'] = array('Negro', 'Negro', 'Negro');
		$edit['var_codigo'] = array($codMN, '', ''); $edit['var_stock'] = array('99', '99', '3'); $edit['var_stock_minimo'] = array('2', '1', '1'); $edit['var_precio'] = array('0', '0', '0');
		list($c, $b) = http('POST', "$base/ajax/articulo.php?op=guardaryeditar", $edit);
		check('quitar M/Blanco (sin stock) y agregar XL/Negro con 3: el stock existente no se pisa', stripos($b, 'correctamente') !== false && $varStock('M', 'Blanco') === null && $varStock('M', 'Negro') == 8 && $varStock('XL', 'Negro') == 3 && $stockPolo() == 16, $b . ' stock=' . $stockPolo());

		list($c, $b) = http('POST', "$base/ajax/cotizacion.php?op=guardar", array('idcliente' => $cli['idpersona'], 'fecha_hora' => date('Y-m-d\TH:i'), 'fecha_validez' => date('Y-m-d', strtotime('+10 days')), 'impuesto' => 0, 'observacion' => 'SMOKE RO',
			'idarticulo' => array($polo), 'idpresentacion' => array(0), 'idvariante' => array($idLN), 'cantidad' => array('2'), 'precio' => array('40'), 'descuento' => array('0')));
		$r = json_decode($b, true);
		$ids['cotizacion_ropa'] = $r && !empty($r['idcotizacion']) ? (int)$r['idcotizacion'] : 0;
		list($c, $b) = http('GET', "$base/ajax/cotizacion.php?op=paraVenta&id=" . $ids['cotizacion_ropa']);
		$r = json_decode($b, true);
		check('cotización conserva la talla/color para la venta', $r && !empty($r['ok']) && (int)$r['items'][0]['idvariante'] === $idLN, substr($b, 0, 160));

		list($c, $b) = http('GET', "$base/ajax/procenter.php?op=kardex&idarticulo=$polo");
		check('kardex muestra la talla/color de cada movimiento', $c === 200 && strpos($b, 'M \/ Negro') !== false, substr($b, 0, 160));
		list($c, $b) = http('GET', "$base/ajax/articulo.php?op=catalogoEtiquetas");
		check('etiquetas incluyen el código de la talla/color', strpos($b, $codMN) !== false, 'sin ' . $codMN);
		list($c, $b) = http('GET', "$base/ajax/consultas.php?op=dashboardAlertas");
		$al = json_decode($b, true);
		check('alertas incluyen tallas/colores', $al && (int)$al['usa_variantes'] === 1 && isset($al['variantes_agotadas']), substr($b, 0, 160));

		// Importacion: una fila por talla/color
		$nombreCasaca = 'QA Casaca ' . $sufRo;
		$csv = "\xEF\xBB\xBF" . "Nombre;Código;Categoría;Unidad;Stock;Precio venta;Talla;Color\n"
			. $nombreCasaca . ";QACS" . $sufRo . ";" . $nomCatRo . ";und;4;120;S;Azul\n"
			. $nombreCasaca . ";;;und;6;120;M;Azul\n"
			. $nombreCasaca . ";;;und;2;135;L;Azul\n";
		$archivoCsv = sys_get_temp_dir() . '/qa_ropa_' . $sufRo . '.csv';
		file_put_contents($archivoCsv, $csv);
		$ch = curl_init("$base/ajax/importar.php?op=previsualizar&tipo=articulos");
		curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => array('X-CSRF-Token: ' . $csrf), CURLOPT_POSTFIELDS => array('archivo' => new CURLFile($archivoCsv, 'text/csv', 'casacas.csv'))));
		$b = (string)curl_exec($ch); curl_close($ch);
		@unlink($archivoCsv);
		$pv = json_decode($b, true);
		check('importación previsualiza 3 tallas/colores nuevas', $pv && !empty($pv['ok']) && (int)$pv['crear'] === 3 && !empty($pv['modo_variantes']), substr($b, 0, 200));
		list($c, $b) = http('POST', "$base/ajax/importar.php?op=importar&tipo=articulos", array('token' => $pv ? $pv['token'] : '', 'actualizar_existentes' => 1, 'crear_categorias' => 0, 'actualizar_stock' => 1));
		$casaca = (int)dbValue("SELECT idarticulo FROM articulo WHERE nombre=?", array($nombreCasaca), 0);
		if ($casaca > 0) { $ids['articulos_ropa'][] = $casaca; }
		$nVar = (int)dbValue("SELECT COUNT(*) FROM articulo_variante WHERE idarticulo=? AND condicion=1", array($casaca), 0);
		$stCas = round((float)dbValue("SELECT stock FROM articulo WHERE idarticulo=?", array($casaca), 0), 3);
		$pL = round((float)dbValue("SELECT precio_venta FROM articulo_variante WHERE idarticulo=? AND talla='L'", array($casaca), 0), 2);
		check('importación crea el artículo con 3 tallas, stock 12 y precio propio en L', strpos($b, '"ok":true') !== false && $nVar === 3 && $stCas == 12 && abs($pL - 135) < 0.001, $b . " vars=$nVar stock=$stCas pL=$pL");

		// Con otro rubro, un articulo con tallas sigue exigiendo talla/color
		dbExec("UPDATE configuracion_empresa SET tipo_negocio='GENERAL'");
		list($r, $b) = $ventaPolo(array(array(0, 1)));
		check('rubro GENERAL: artículo con tallas sigue exigiendo talla/color', stripos($b, 'Elige la talla') !== false, $b);
		list($r, $b) = $ventaPolo(array(array($idXL = $varId('XL', 'Negro'), 1)));
		check('rubro GENERAL: vender una talla/color mantiene la suma (XL 2, total 15)', $r && !empty($r['ok']) && $varStock('XL', 'Negro') == 2 && $stockPolo() == 15, $b . ' stock=' . $stockPolo());
		dbExec("UPDATE configuracion_empresa SET tipo_negocio=?", array($rubroOriginal));

		echo "== POS, cobro y ticket\n";
		$ventaPos = function($extra) use ($base, $cli, $a) {
			list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array_merge(array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE POS', 'idarticulo' => array($a), 'cantidad' => array(1), 'precio_venta' => array(3.5), 'descuento' => array(0)), $extra));
			$r = json_decode($b, true);
			if ($r && !empty($r['ok'])) { $GLOBALS['ids']['venta'][] = (int)$r['idventa']; }
			return array($r, $b);
		};
		list($r, $b) = $ventaPos(array('monto_recibido' => '2.00'));
		check('efectivo recibido menor al total se rechaza', $r && empty($r['ok']) && stripos($b, 'menor') !== false, $b);
		list($r, $b) = $ventaPos(array('monto_recibido' => '10'));
		$vPos = $r && !empty($r['ok']) ? (int)$r['idventa'] : 0;
		$fila = sql("SELECT monto_recibido, num_operacion FROM venta WHERE idventa=?", array($vPos));
		check('venta en efectivo guarda lo recibido (10.00)', $vPos > 0 && $fila && abs((float)$fila['monto_recibido'] - 10) < 0.001, $b);
		list($c, $b) = http('GET', "$base/reportes/exTicket.php?id=$vPos");
		check('ticket térmico con recibido y vuelto 6.50', $c === 200 && sinErroresPhp($b) && strpos($b, 'class="ticket"') !== false && strpos($b, 'Vuelto') !== false && strpos($b, '6.50') !== false, substr(strip_tags($b), 0, 160));
		check('ticket con tamaño de rollo (@page en mm)', preg_match('/@page \{ size: (80|58)mm auto/', $b) === 1, substr($b, 0, 160));
		list($r, $b) = $ventaPos(array('medio_pago' => 'YAPE', 'num_operacion' => 'OP-SMOKE-1', 'monto_recibido' => '50'));
		$vYape = $r && !empty($r['ok']) ? (int)$r['idventa'] : 0;
		$fila = sql("SELECT medio_pago, monto_recibido, num_operacion FROM venta WHERE idventa=?", array($vYape));
		check('venta con Yape guarda la operación y no el efectivo', $fila && $fila['medio_pago'] === 'YAPE' && $fila['num_operacion'] === 'OP-SMOKE-1' && $fila['monto_recibido'] === null, $b);
		// IGV por comprobante y numeracion automatica (requisitos para facturacion electronica)
		$impEmpresa = round((float)dbValue("SELECT impuesto_default FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1", array(), 18), 2);
		list($r1, $b) = $ventaPos(array('impuesto' => 0, 'num_comprobante' => '99999999'));
		list($r2, $b2) = $ventaPos(array('impuesto' => 0));
		$f1 = $r1 && !empty($r1['ok']) ? sql("SELECT impuesto, num_comprobante FROM venta WHERE idventa=?", array((int)$r1['idventa'])) : null;
		$f2 = $r2 && !empty($r2['ok']) ? sql("SELECT num_comprobante FROM venta WHERE idventa=?", array((int)$r2['idventa'])) : null;
		check('boleta lleva el IGV de la empresa aunque el navegador envíe 0', $f1 && abs((float)$f1['impuesto'] - $impEmpresa) < 0.001, $b . json_encode($f1));
		check('el número escrito a mano se ignora', $f1 && $f1['num_comprobante'] !== '99999999', json_encode($f1));
		check('dos boletas seguidas llevan números consecutivos', $f1 && $f2 && (int)$f2['num_comprobante'] === (int)$f1['num_comprobante'] + 1, json_encode(array($f1, $f2)));
		list($r3, $b) = $ventaPos(array('tipo_comprobante' => 'Ticket', 'serie_comprobante' => 'T001', 'impuesto' => 18));
		$f3 = $r3 && !empty($r3['ok']) ? sql("SELECT impuesto FROM venta WHERE idventa=?", array((int)$r3['idventa'])) : null;
		check('la nota de venta (Ticket) no desglosa IGV', $f3 && (float)$f3['impuesto'] == 0, $b . json_encode($f3));
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=guardaryeditar", array('idproveedor' => $prov['idpersona'], 'tipo_comprobante' => 'Factura', 'serie_comprobante' => 'F001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => '0.18', 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE IGV', 'idarticulo' => array($a), 'cantidad' => array(1), 'precio_compra' => array(1), 'precio_venta' => array(0)));
		$r = json_decode($b, true);
		if ($r && !empty($r['ok'])) { $ids['ingreso'][] = (int)$r['idingreso']; }
		check('compra con IGV 0.18 se rechaza (va en porcentaje)', $r && empty($r['ok']) && stripos($b, 'porcentaje') !== false, $b);

		list($c, $b) = http('GET', "$base/reportes/exTicket.php?prueba=1");
		check('ticket de prueba', $c === 200 && sinErroresPhp($b) && strpos($b, 'TICKET DE PRUEBA') !== false, substr(strip_tags($b), 0, 120));
		list($c, $b) = http('GET', "$base/ajax/venta.php?op=catalogoPos");
		$r = json_decode($b, true);
		check('catálogo del POS con artículos y ajustes del ticket', $r && !empty($r['ok']) && count($r['items']) > 0 && isset($r['ticket']['ancho']), substr($b, 0, 120));

		$codigoArt = (string)dbValue("SELECT codigo FROM articulo WHERE idarticulo=?", array($a), '');
		if ($codigoArt !== '') {
			list($c, $b) = http('GET', "$base/ajax/ingreso.php?op=buscarArticulos&q=" . urlencode($codigoArt));
			$r = json_decode($b, true);
			check('buscador de compras: el código exacto sale primero', $r && !empty($r['items']) && (int)$r['items'][0]['idarticulo'] === $a && !empty($r['items'][0]['exacto']), substr($b, 0, 160));
		}
		$compraPos = array('idproveedor' => $prov['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'observacion' => 'SMOKE DEP', 'idarticulo' => array($a), 'cantidad' => array(1), 'precio_compra' => array(1), 'precio_venta' => array(0));
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=guardaryeditar", array_merge($compraPos, array('medio_pago' => 'DEPOSITO', 'cuenta_pago' => 'BCP 191-SMOKE', 'num_operacion' => 'DEP-SMOKE-9')));
		$r = json_decode($b, true);
		$iDep = $r && !empty($r['ok']) ? (int)$r['idingreso'] : 0;
		if ($iDep > 0) { $ids['ingreso'][] = $iDep; }
		$fila = sql("SELECT medio_pago, cuenta_pago, num_operacion FROM ingreso WHERE idingreso=?", array($iDep));
		check('compra por depósito guarda cuenta y operación', $fila && $fila['medio_pago'] === 'DEPOSITO' && $fila['cuenta_pago'] === 'BCP 191-SMOKE' && $fila['num_operacion'] === 'DEP-SMOKE-9', $b);
		list($c, $b) = http('POST', "$base/ajax/ingreso.php?op=guardaryeditar", array_merge($compraPos, array('medio_pago' => 'EFECTIVO', 'cuenta_pago' => 'NO', 'num_operacion' => 'NO')));
		$r = json_decode($b, true);
		$iEf = $r && !empty($r['ok']) ? (int)$r['idingreso'] : 0;
		if ($iEf > 0) { $ids['ingreso'][] = $iEf; }
		$fila = sql("SELECT cuenta_pago, num_operacion FROM ingreso WHERE idingreso=?", array($iEf));
		check('compra en efectivo no guarda cuenta ni operación', $iEf > 0 && $fila && $fila['cuenta_pago'] === null && $fila['num_operacion'] === null, $b);

		echo "== Rol vendedor (segunda sesión)\n";
		$loginV = 'qa_vend_' . substr(bin2hex(random_bytes(3)), 0, 5);
		$claveV = 'Vende' . mt_rand(1000, 9999) . 'x';
		$idVend = dbInsert("INSERT INTO usuario(nombre,tipo_documento,num_documento,direccion,telefono,email,cargo,login,clave,imagen,condicion) VALUES('QA Vendedor','DNI','','','','','Vendedor',?,?,'',1)", array($loginV, password_hash($claveV, PASSWORD_BCRYPT)));
		$mapaP = array('ventas' => 4, 'caja' => 14);   // ids fijos (mapaPermisos)
		foreach (array('ventas', 'caja') as $p) { dbExec("INSERT INTO usuario_permiso(idusuario,idpermiso) VALUES(?,?)", array($idVend, $mapaP[$p])); }
		$ids['vendedor'] = array($idVend, $loginV);
		$jarAdmin = $jar; $csrfAdmin = $csrf;
		$jar = tempnam(sys_get_temp_dir(), 'mtjarv'); $csrf = '';
		list($c, $b) = http('GET', "$base/vistas/login.php");
		preg_match('/id="csrf" value="([a-f0-9]+)"/', $b, $m); $csrf = isset($m[1]) ? $m[1] : '';
		list($c, $b) = http('POST', "$base/ajax/usuario.php?op=verificar", array('logina' => $loginV, 'clavea' => $claveV));
		$r = json_decode($b, true);
		check('vendedor entra directo al punto de venta', $r && !empty($r['ok']) && $r['redirect'] === 'venta.php?nuevo=1', $b);
		list($c, $b) = http('GET', "$base/vistas/venta.php");
		preg_match('/appCsrfToken = "([a-f0-9]+)"/', $b, $m); $csrf = isset($m[1]) ? $m[1] : $csrf;
		check('menú del vendedor sin compras, almacén ni configuración', $c === 200 && strpos($b, 'ingreso.php') === false && strpos($b, 'articulo.php') === false && strpos($b, 'usuario.php"') === false && strpos($b, 'empresa.php') === false && strpos($b, 'cuentas.php') === false, 'menu');
		list($c, $b) = http('GET', "$base/vistas/escritorio.php");
		check('escritorio lo redirige a su inicio', $c === 302, "code=$c");
		list($c, $b) = http('GET', "$base/vistas/ingreso.php");
		check('vista de compras bloqueada', strpos($b, 'Sin acceso a este módulo') !== false, substr(strip_tags($b), 0, 80));
		foreach (array(array('GET', 'ingreso.php?op=listar'), array('GET', 'usuario.php?op=listar'), array('POST', 'empresa.php?op=guardaryeditar'), array('POST', 'articulo.php?op=guardaryeditar'), array('GET', 'backup.php?op=listar'), array('GET', 'consultas.php?op=utilidadperiodo'), array('GET', 'cuentas.php?op=listarCobrar')) as $ep) {
			list($c, $b) = http($ep[0], "$base/ajax/" . $ep[1], $ep[0] === 'POST' ? array('x' => 1) : null);
			check('vendedor sin acceso a ' . $ep[1], $c === 403 || stripos($b, 'permiso') !== false, "code=$c " . substr($b, 0, 80));
		}
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=infoArticulo", array('idarticulo' => $a));
		$fichaV = json_decode($b, true);
		$pLista = round((float)$fichaV['precio_venta'], 2);
		foreach ((array)$fichaV['escalas'] as $es) { if (1 + 0.0005 >= $es['cantidad_minima']) { $pLista = round((float)$es['precio'], 2); } }
		$esperado = round(50 + $pLista, 2);
		$ventaVend = array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE VEND', 'idarticulo' => array($a), 'cantidad' => array(1), 'precio_venta' => array($pLista), 'descuento' => array(0));
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", $ventaVend);
		$r = json_decode($b, true);
		check('sin caja abierta no puede vender', $r && empty($r['ok']) && !empty($r['caja_cerrada']), $b);
		list($c, $b) = http('POST', "$base/ajax/caja.php?op=abrir", array('monto_apertura' => 50));
		check('vendedor abre su caja', strpos($b, 'correctamente') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array_merge($ventaVend, array('precio_venta' => array($pLista + 1))));
		check('sin permiso de precios no puede cambiar el precio', stripos($b, 'No puedes cambiar el precio') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array_merge($ventaVend, array('descuento' => array(0.5))));
		check('sin permiso de precios no puede poner descuento', stripos($b, 'no puede aplicar descuentos') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/cotizacion.php?op=guardar", array('idcliente' => $cli['idpersona'], 'idarticulo' => array($a), 'cantidad' => array(1), 'precio' => array($pLista + 1), 'descuento' => array(0)));
		check('tampoco puede cotizar con otro precio', stripos($b, 'No puedes cambiar el precio') !== false, $b);
		$idsVend = array();
		foreach (array('EFECTIVO', 'YAPE') as $medio) {
			list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array_merge($ventaVend, array('medio_pago' => $medio)));
			$r = json_decode($b, true);
			if ($r && !empty($r['ok'])) { $ids['venta'][] = (int)$r['idventa']; $idsVend[] = (int)$r['idventa']; }
		}
		check('vendedor vende en efectivo y con Yape', count($idsVend) === 2, $b);
		list($c, $b) = http('GET', "$base/ajax/caja.php?op=estado");
		$r = json_decode($b, true);
		check('arqueo ciego: el vendedor no recibe efectivo esperado ni totales', $r && !empty($r['arqueo_ciego']) && !isset($r['sistema']) && !isset($r['ingresos']) && !isset($r['medios']), $b);
		list($c, $b) = http('GET', "$base/ajax/venta.php?op=listar");
		$r = json_decode($b, true);
		check('listado muestra solo sus 2 ventas', $r && (int)$r['iTotalRecords'] === 2, substr($b, 0, 120));
		check('su botón de anular pide la clave de un encargado', $r && strpos($b, 'pide la clave de un encargado') !== false, 'botones');
		list($c, $b) = http('GET', "$base/ajax/venta.php?op=resumenDia");
		$r = json_decode($b, true);
		check('resumen del día solo con sus ventas', $r && (int)$r['comprobantes'] === 2, $b);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=mostrar", array('idventa' => $v2));
		check('no ve el detalle de una venta ajena', $c === 403, "code=$c");
		list($c, $b) = http('GET', "$base/reportes/exTicket.php?id=$v2");
		check('no imprime el ticket de una venta ajena', strpos($b, 'propias ventas') !== false, substr(strip_tags($b), 0, 80));
		list($c, $b) = http('GET', "$base/reportes/exTicket.php?id=" . $idsVend[0]);
		check('sí imprime el ticket de su venta', $c === 200 && strpos($b, 'class="ticket"') !== false, substr(strip_tags($b), 0, 80));
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", array('idventa' => $idsVend[0]));
		check('anular sin permiso exige motivo', stripos($b, 'motivo') !== false, $b);
		$anulaYape = array('idventa' => $idsVend[1], 'motivo' => 'Cliente devolvio (SMOKE)');
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", $anulaYape);
		check('y la autorización de un encargado', stripos($b, 'clave de un encargado') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", array_merge($anulaYape, array('autoriza_login' => $login, 'autoriza_clave' => 'mala' . $clave)));
		check('clave de encargado errada se rechaza', stripos($b, 'incorrectos') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", array_merge($anulaYape, array('autoriza_login' => $loginV, 'autoriza_clave' => $claveV)));
		check('un vendedor no puede autorizarse a sí mismo', stripos($b, 'no tiene permiso para autorizar') !== false, $b);
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=anular", array_merge($anulaYape, array('autoriza_login' => $login, 'autoriza_clave' => $clave)));
		$aud = sql("SELECT detalle FROM auditoria WHERE idusuario=? AND accion='anular' ORDER BY idauditoria DESC LIMIT 1", array($idVend));
		check('con la clave del encargado se anula y queda en auditoría', stripos($b, 'anulada') !== false && $aud && strpos($aud['detalle'], 'autorización de ' . $login) !== false && strpos($aud['detalle'], 'motivo') !== false, $b . json_encode($aud));
		list($c, $b) = http('POST', "$base/ajax/caja.php?op=cerrar", array('monto_cierre_real' => number_format($esperado, 2, '.', '')));
		$cv = sql("SELECT monto_cierre_sistema, diferencia FROM caja_diaria WHERE idusuario=? ORDER BY idcaja DESC LIMIT 1", array($idVend));
		check('arqueo ciego: al cerrar no le muestra el cuadre', strpos($b, 'administrador revisa') !== false && strpos($b, number_format($esperado, 2)) === false, $b);
		check('cierre: esperado = 50 + venta en efectivo (Yape anulado no cuenta) y cuadra', $cv && abs((float)$cv['monto_cierre_sistema'] - $esperado) < 0.001 && abs((float)$cv['diferencia']) < 0.001, $b . json_encode($cv));
		dbExec("DELETE FROM usuario_permiso WHERE idusuario=? AND idpermiso=?", array($idVend, $mapaP['ventas']));
		list($c, $b) = http('GET', "$base/ajax/venta.php?op=listar");
		check('quitar el permiso de ventas surte efecto sin cerrar sesión', $c === 403, "code=$c");
		dbExec("UPDATE usuario SET condicion=0 WHERE idusuario=?", array($idVend));
		list($c, $b) = http('GET', "$base/ajax/caja.php?op=estado");
		check('usuario desactivado queda fuera de inmediato', $c === 401, "code=$c");
		@unlink($jar);
		$jar = $jarAdmin; $csrf = $csrfAdmin;

		echo "== Reportes\n";
		list($c, $b) = http('GET', "$base/reportes/exFactura.php?id=$v2");
		check('PDF factura', $c === 200 && substr($b, 0, 4) === '%PDF', substr($b, 0, 80));
		list($c, $b) = http('GET', "$base/reportes/exTicket.php?id=" . $ids['venta'][0]);
		check('ticket HTML', $c === 200 && sinErroresPhp($b) && strpos($b, 'class="ticket"') !== false, substr($b, 0, 80));
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
	// Datos del bloque de ferreteria (con FOREIGN_KEY_CHECKS=0 no hay cascada: se borra explicito)
	if (isset($rubroOriginal)) {
		dbExec("UPDATE configuracion_empresa SET tipo_negocio=?", array($rubroOriginal));
	}
	if (!empty($ids['cotizacion'])) {
		dbExec("DELETE FROM detalle_cotizacion WHERE idcotizacion=?", array($ids['cotizacion']));
		dbExec("DELETE FROM cotizacion WHERE idcotizacion=?", array($ids['cotizacion']));
	}
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
	if (!empty($ids['vendedor'])) {
		list($idV, $loginVend) = $ids['vendedor'];
		dbExec("DELETE FROM caja_movimiento WHERE idcaja IN (SELECT idcaja FROM caja_diaria WHERE idusuario=?)", array($idV));
		dbExec("DELETE FROM caja_diaria WHERE idusuario=?", array($idV));
		dbExec("DELETE FROM auditoria WHERE idusuario=?", array($idV));
		dbExec("DELETE FROM intento_login WHERE login=?", array($loginVend));
		dbExec("DELETE FROM usuario_permiso WHERE idusuario=?", array($idV));
		dbExec("DELETE FROM usuario WHERE idusuario=?", array($idV));
	}
	dbExec("DELETE FROM ajuste_inventario WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM caja_movimiento WHERE idcaja IN (SELECT idcaja FROM caja_diaria WHERE idusuario=?)", array($idqa));
	dbExec("DELETE FROM caja_diaria WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM auditoria WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM intento_login WHERE login=?", array($login));
	dbExec("DELETE FROM usuario_permiso WHERE idusuario=?", array($idqa));
	dbExec("DELETE FROM usuario WHERE idusuario=?", array($idqa));
	if (!empty($ids['articulo'])) {
		dbExec("DELETE FROM articulo_precio_escala WHERE idarticulo=?", array($ids['articulo']));
		dbExec("DELETE FROM articulo_presentacion WHERE idarticulo=?", array($ids['articulo']));
		dbExec("DELETE FROM ajuste_inventario WHERE idarticulo=?", array($ids['articulo']));
		dbExec("DELETE FROM articulo WHERE idarticulo=?", array($ids['articulo']));
	}
	if (!empty($ids['cotizacion_ropa'])) {
		dbExec("DELETE FROM detalle_cotizacion WHERE idcotizacion=?", array($ids['cotizacion_ropa']));
		dbExec("DELETE FROM cotizacion WHERE idcotizacion=?", array($ids['cotizacion_ropa']));
	}
	if (!empty($ids['articulos_ropa'])) {
		foreach ($ids['articulos_ropa'] as $idRopa) {
			dbExec("DELETE FROM articulo_variante WHERE idarticulo=?", array($idRopa));
			dbExec("DELETE FROM ajuste_inventario WHERE idarticulo=?", array($idRopa));
			dbExec("DELETE FROM articulo WHERE idarticulo=?", array($idRopa));
		}
	}
	if (!empty($ids['articulo_ab'])) {
		dbExec("DELETE FROM lote_movimiento WHERE idlote IN (SELECT idlote FROM lote WHERE idarticulo=?)", array($ids['articulo_ab']));
		dbExec("DELETE FROM lote WHERE idarticulo=?", array($ids['articulo_ab']));
		dbExec("DELETE FROM ajuste_inventario WHERE idarticulo=?", array($ids['articulo_ab']));
		dbExec("DELETE FROM articulo WHERE idarticulo=?", array($ids['articulo_ab']));
	}
	if (!empty($ids['unidad'])) {
		dbExec("DELETE FROM unidad_medida WHERE idunidad=?", array($ids['unidad']));
	}
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
