<?php
/**
 * Comprobante publico: la pagina que abre el QR del ticket.
 *
 *   comprobante.php?c=CODIGO   (clave aleatoria de 16 caracteres, venta.codigo_publico)
 *
 * No pide login: la clave solo la conoce quien tiene el ticket. Muestra una
 * sola venta y permite imprimirla o descargar el PDF (reportes/exFactura.php?c=).
 * No expone datos internos (cajero, costos, caja) y enmascara el DNI.
 */
require_once __DIR__ . "/config/seguridad.php";
require_once __DIR__ . "/config/comprobante.php";

enviarCabecerasSeguridad();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-cache');

// Los modelos usan rutas relativas a ajax/
chdir(__DIR__ . '/ajax');
require_once __DIR__ . "/modelos/Venta.php";
require_once __DIR__ . "/modelos/Empresa.php";

$codigo = isset($_GET['c']) ? (string)$_GET['c'] : '';
$venta = new Venta();
$idventa = codigoPublicoValido($codigo) ? $venta->idPorCodigoPublico($codigo) : 0;

$cab = null;
$items = array();
if ($idventa > 0) {
  $rs = $venta->ventacabecera($idventa);
  $cab = $rs ? $rs->fetch_assoc() : null;
  $rsd = $venta->ventadetalles($idventa);
  while ($rsd && ($r = $rsd->fetch_assoc())) {
    $items[] = $r;
  }
}

$empresaModel = new Empresa();
$emp = $empresaModel->datosReporte();
$cfgTicket = $empresaModel->configTicket();
$marca = marcaEmpresa();
$simbolo = obtenerSimboloMoneda(!empty($emp['moneda']) ? $emp['moneda'] : 'PEN');
$nombreTienda = $emp['nombre_comercial'] !== '' ? $emp['nombre_comercial'] : $emp['nombre'];

function mPub($simbolo, $valor) {
  return $simbolo . ' ' . number_format((float)$valor, 2);
}

/** DNI y similares: solo los 2 primeros y 2 ultimos digitos (el RUC es publico). */
function docEnmascarado($tipo, $numero) {
  $numero = trim((string)$numero);
  if ($numero === '' || preg_match('/^0+$/', $numero)) {
    return '';
  }
  if (strtoupper((string)$tipo) === 'RUC' || strlen($numero) <= 4) {
    return $numero;
  }
  return substr($numero, 0, 2) . str_repeat('•', strlen($numero) - 4) . substr($numero, -2);
}

if (!$cab) {
  http_response_code(404);
}

if ($cab) {
  $total = (float)$cab['total_venta'];
  $impuesto = (float)$cab['impuesto'];
  $base = $impuesto > 0 ? $total / (1 + $impuesto / 100) : $total;
  $igv = $total - $base;
  $descuentos = 0.0;
  foreach ($items as $it) {
    $descuentos += (float)$it['descuento'];
  }
  $mediosTexto = array('EFECTIVO' => 'Efectivo', 'DEPOSITO' => 'Depósito en cuenta', 'TARJETA' => 'Tarjeta', 'TRANSFERENCIA' => 'Transferencia', 'YAPE' => 'Yape', 'PLIN' => 'Plin', 'OTRO' => 'Otro');
  $medio = strtoupper((string)$cab['medio_pago']);
  $esCredito = strtoupper((string)$cab['tipo_pago']) === 'CREDITO';
  $pagoTexto = $esCredito ? 'Crédito' : ($medio === 'MIXTO' ? 'Varios medios' : (isset($mediosTexto[$medio]) ? $mediosTexto[$medio] : $medio));
  // Cada medio con que se pago (sin N° de operacion: no se expone en la pagina publica)
  $pagosPub = $venta->pagos($idventa);
  $pagadoPub = 0.0;
  foreach ($pagosPub as $pg) {
    $pagadoPub += (float)$pg['monto'];
  }
  $tipoDoc = $cab['tipo_comprobante'] === 'Ticket' ? 'Nota de venta' : $cab['tipo_comprobante'] . ' de venta';
  $numero = $cab['serie_comprobante'] . '-' . $cab['num_comprobante'];
  $anulado = $cab['estado'] === 'Anulado';
  $doc = docEnmascarado($cab['tipo_documento'], $cab['num_documento']);
}
$titulo = $cab ? $tipoDoc . ' ' . $numero . ' · ' . $nombreTienda : 'Comprobante no encontrado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <meta name="theme-color" content="<?php echo e($marca['primario']); ?>">
  <title><?php echo e($titulo); ?></title>
<?php if ($marca['tiene_logo']) { ?>
  <link rel="icon" href="<?php echo e(marcaUrlLogo('')); ?>">
<?php } ?>
  <style>
    :root {
      --brand: <?php echo e($marca['primario']); ?>;
      --brand-dark: <?php echo e($marca['primario_oscuro']); ?>;
      --brand-soft: <?php echo e($marca['primario_suave']); ?>;
      --ink: #0f172a;
      --muted: #64748b;
      --line: #e2e8f0;
      --bg: #f1f5f9;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      background: var(--bg);
      color: var(--ink);
      font: 15px/1.45 -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      -webkit-text-size-adjust: 100%;
    }
    .wrap { max-width: 560px; margin: 0 auto; padding: 16px 16px 40px; }
    .card { background: #fff; border-radius: 14px; box-shadow: 0 8px 30px rgba(15, 23, 42, .08); overflow: hidden; }
    .head { background: var(--brand); color: #fff; padding: 20px 20px 18px; text-align: center; }
    .head img { max-height: 64px; max-width: 70%; background: #fff; border-radius: 10px; padding: 6px 10px; margin-bottom: 10px; }
    .head h1 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: .2px; }
    .head p { margin: 3px 0 0; font-size: 13px; opacity: .9; }
    .doc { padding: 18px 20px 6px; text-align: center; border-bottom: 1px dashed var(--line); }
    .doc .tipo { font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); }
    .doc .num { font-size: 24px; font-weight: 800; margin: 2px 0 4px; }
    .doc .fecha { color: var(--muted); font-size: 13px; margin-bottom: 12px; }
    .anulado { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 10px; padding: 10px; font-weight: 700; margin: 0 0 14px; }
    .sec { padding: 14px 20px; border-bottom: 1px dashed var(--line); }
    .fila { display: flex; justify-content: space-between; gap: 12px; padding: 2px 0; }
    .fila span:first-child { color: var(--muted); }
    .fila span:last-child { text-align: right; font-weight: 600; }
    .item { padding: 9px 0; border-bottom: 1px solid var(--line); }
    .item:last-child { border-bottom: 0; }
    .item .nom { font-weight: 700; }
    .item .det { display: flex; justify-content: space-between; color: var(--muted); font-size: 14px; }
    .item .det b { color: var(--ink); }
    .item .dsc { color: #b45309; font-size: 13px; }
    .total { display: flex; justify-content: space-between; align-items: baseline; padding-top: 8px; margin-top: 6px; border-top: 2px solid var(--ink); font-size: 22px; font-weight: 800; }
    .acciones { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 16px 20px 18px; }
    .btn { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; border-radius: 10px; border: 0; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
    .btn svg { width: 20px; height: 20px; flex: none; }
    .btn-pri { background: var(--brand); color: #fff; }
    .btn-pri:hover { background: var(--brand-dark); }
    .btn-sec { background: var(--brand-soft); color: var(--brand-dark); }
    .btn-full { grid-column: 1 / -1; background: #fff; color: var(--ink); border: 1px solid var(--line); }
    .leyenda { margin: 0 20px 18px; padding: 12px 14px; border-radius: 10px; background: #fffbeb; border: 1px solid #fde68a; color: #78350f; font-size: 13.5px; }
    .leyenda b { display: block; margin-bottom: 2px; }
    .pie { text-align: center; color: var(--muted); font-size: 13px; padding: 18px 12px 0; }
    .pie a { color: var(--brand-dark); font-weight: 600; }
    .vacio { text-align: center; padding: 40px 24px; }
    .vacio h2 { margin: 0 0 8px; }
    @media (max-width: 360px) { .acciones { grid-template-columns: 1fr; } }
    @media print {
      body { background: #fff; font-size: 12px; }
      .wrap { max-width: none; padding: 0; }
      .card { box-shadow: none; border-radius: 0; }
      .head { background: #fff !important; color: #000; border-bottom: 2px solid #000; }
      .head img { padding: 0; }
      .acciones, .no-print { display: none !important; }
      .leyenda { background: #fff; border: 1px solid #000; color: #000; }
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="head">
<?php if ($marca['tiene_logo']) { ?>
      <img src="<?php echo e(marcaUrlLogo('')); ?>" alt="<?php echo e($nombreTienda); ?>">
<?php } ?>
      <h1><?php echo e($nombreTienda); ?></h1>
<?php if ($emp['nombre'] !== '' && strcasecmp($emp['nombre'], $nombreTienda) !== 0) { ?>
      <p><?php echo e($emp['nombre']); ?></p>
<?php } ?>
<?php if ($emp['ruc'] !== '') { ?>
      <p>RUC <?php echo e($emp['ruc']); ?></p>
<?php } ?>
<?php if (trim($emp['direccion_linea1'] . ' ' . $emp['direccion_linea2']) !== '') { ?>
      <p><?php echo e(trim($emp['direccion_linea1'] . ' ' . $emp['direccion_linea2'])); ?></p>
<?php } ?>
    </div>

<?php if (!$cab) { ?>
    <div class="vacio">
      <h2>Comprobante no encontrado</h2>
      <p>El enlace no es válido o está incompleto. Vuelve a escanear el código QR de tu ticket o consulta en tienda.</p>
    </div>
  </div>
<?php } else { ?>
    <div class="doc">
      <div class="tipo"><?php echo e($tipoDoc); ?></div>
      <div class="num"><?php echo e($numero); ?></div>
      <div class="fecha"><?php echo e($cab['fecha']); ?></div>
<?php if ($anulado) { ?>
      <div class="anulado">Este comprobante fue ANULADO y no tiene validez.</div>
<?php } ?>
    </div>

    <div class="sec">
      <div class="fila"><span>Cliente</span><span><?php echo e($cab['cliente']); ?></span></div>
<?php if ($doc !== '') { ?>
      <div class="fila"><span><?php echo e($cab['tipo_documento']); ?></span><span><?php echo e($doc); ?></span></div>
<?php } ?>
      <div class="fila"><span>Pago</span><span><?php echo e($pagoTexto); ?></span></div>
<?php if (count($pagosPub) > 1 || $esCredito) { foreach ($pagosPub as $pg) { ?>
      <div class="fila"><span><?php echo e(($esCredito ? 'Adelanto · ' : '') . (isset($mediosTexto[$pg['medio_pago']]) ? $mediosTexto[$pg['medio_pago']] : $pg['medio_pago'])); ?></span><span><?php echo e(mPub($simbolo, $pg['monto'])); ?></span></div>
<?php } } ?>
<?php if ($esCredito) { ?>
      <div class="fila"><span>Saldo a crédito</span><span><?php echo e(mPub($simbolo, $total - $pagadoPub)); ?></span></div>
<?php } ?>
<?php if ($esCredito && !empty($cab['fecha_vencimiento'])) { ?>
      <div class="fila"><span>Vence</span><span><?php echo e(date('d/m/Y', strtotime($cab['fecha_vencimiento']))); ?></span></div>
<?php } ?>
    </div>

    <div class="sec">
<?php foreach ($items as $it) { ?>
      <div class="item">
        <div class="nom"><?php echo e($it['articulo']); ?></div>
        <div class="det">
          <span><?php echo e(formatearCantidad($it['cantidad']) . ' ' . $it['unidad']); ?> × <?php echo number_format((float)$it['precio_venta'], 2); ?></span>
          <b><?php echo number_format((float)$it['cantidad'] * (float)$it['precio_venta'], 2); ?></b>
        </div>
<?php   if ((float)$it['descuento'] > 0) { ?>
        <div class="dsc">Descuento -<?php echo number_format((float)$it['descuento'], 2); ?></div>
<?php   } ?>
      </div>
<?php } ?>
    </div>

    <div class="sec">
<?php if ($descuentos > 0) { ?>
      <div class="fila"><span>Descuentos</span><span>-<?php echo e(mPub($simbolo, $descuentos)); ?></span></div>
<?php } ?>
<?php if ($impuesto > 0) { ?>
      <div class="fila"><span>Op. gravada</span><span><?php echo e(mPub($simbolo, $base)); ?></span></div>
      <div class="fila"><span>IGV (<?php echo number_format($impuesto, 0); ?>%)</span><span><?php echo e(mPub($simbolo, $igv)); ?></span></div>
<?php } ?>
      <div class="total"><span>Total</span><span><?php echo e(mPub($simbolo, $total)); ?></span></div>
    </div>

    <div class="acciones">
      <a class="btn btn-pri" href="reportes/exFactura.php?c=<?php echo e(rawurlencode($codigo)); ?>&amp;descargar=1" rel="nofollow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
        Descargar PDF
      </a>
      <button type="button" class="btn btn-sec" onclick="window.print()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6"/><rect x="4" y="9" width="16" height="8" rx="2"/><path d="M7 14h10v7H7z"/></svg>
        Imprimir
      </button>
      <a class="btn btn-full" href="reportes/exFactura.php?c=<?php echo e(rawurlencode($codigo)); ?>" target="_blank" rel="nofollow">
        Ver en formato A4
      </a>
    </div>

<?php if ($cfgTicket['leyenda'] !== '') { ?>
    <div class="leyenda"><b>¿Necesitas boleta o factura electrónica?</b><?php echo e($cfgTicket['leyenda']); ?></div>
<?php } ?>
  </div>
<?php } ?>

  <div class="pie">
<?php if ($emp['telefono'] !== '') { ?>
    Consultas: <a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $emp['telefono'])); ?>"><?php echo e($emp['telefono']); ?></a><br>
<?php } ?>
    <?php echo e($emp['mensaje_ticket']); ?>
  </div>
</div>
</body>
</html>
