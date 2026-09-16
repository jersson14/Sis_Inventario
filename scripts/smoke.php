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

		// Con cobros aplicados no se puede borrar el documento de origen
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=eliminar", array('idventa' => $v2));
		check('eliminar venta con cobros se bloquea', stripos($b, 'cobros') !== false, $b);

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

		// Venta vigente: al eliminarla el stock vuelve al inventario
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE DEL', 'idarticulo' => array($a), 'cantidad' => array(2), 'precio_venta' => array(5), 'descuento' => array(0)));
		$r = json_decode($b, true);
		check('venta temporal creada', $r && !empty($r['ok']), $b);
		$v3 = $r ? (int)$r['idventa'] : 0;
		if ($v3 > 0) { $ids['venta'][] = $v3; }
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=eliminar", array('idventa' => $v3));
		check('eliminar venta vigente', stripos($b, 'eliminada') !== false, $b);
		$s = sql("SELECT stock FROM articulo WHERE idarticulo=?", array($a));
		check('stock devuelto al eliminar la venta', abs((float)$s['stock'] - $sPrev) < 0.001, 'stock=' . $s['stock']);

		// Documento ya anulado: eliminar no debe mover el stock por segunda vez
		list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE DEL2', 'idarticulo' => array($a), 'cantidad' => array(4), 'precio_venta' => array(5), 'descuento' => array(0)));
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
			list($c, $b) = http('POST', "$base/ajax/venta.php?op=guardaryeditar", array('idcliente' => $cli['idpersona'], 'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'fecha_hora' => date('Y-m-d\TH:i'), 'impuesto' => 0, 'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'observacion' => 'SMOKE AB',
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
