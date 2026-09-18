<?php
/**
 * Ticket de venta para ticketera termica (80 o 58 mm, segun Empresa > Ticket).
 *
 *   ?id=N          ticket de la venta N
 *   ?prueba=1      ticket de ejemplo para revisar la configuracion
 *   &auto=1        imprime al cargar (lo usa el POS dentro de un iframe oculto)
 *                  y avisa a la ventana padre con postMessage('ticket-impreso')
 */
ob_start();
require_once "../config/seguridad.php";
requiereLogin(false);
require_once "../modelos/Venta.php";
require_once "../modelos/Empresa.php";
require_once "../config/comprobante.php";
require_once "../config/qr.php";
require_once "Letras.php";

$idReporte = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);
$esPrueba = !empty($_GET['prueba']);
$auto = !empty($_GET['auto']);

$permitido = usuarioTienePermiso('ventas') || ($esPrueba && (usuarioTienePermiso('empresa') || usuarioTienePermiso('acceso')));
if (!$permitido) {
  echo "No tiene permiso para visualizar el ticket";
  ob_end_flush();
  exit;
}

$empresaModel = new Empresa();
$cfgEmpresa = $empresaModel->datosReporte();
$cfgTicket = $empresaModel->configTicket();
$simbolo = obtenerSimboloMoneda(!empty($cfgEmpresa["moneda"]) ? $cfgEmpresa["moneda"] : 'PEN');

// ---------- Datos del ticket (venta real o ejemplo) ----------
$items = array();
if ($esPrueba) {
  $cab = array(
    'tipo_comprobante' => 'Boleta', 'serie_comprobante' => 'B001', 'num_comprobante' => '00000123',
    'fecha' => date('d/m/Y H:i'), 'usuario' => (string)$_SESSION['nombre'], 'cliente' => 'Cliente de ejemplo',
    'tipo_documento' => 'DNI', 'num_documento' => '12345678', 'impuesto' => 0, 'total_venta' => 58.50,
    'tipo_pago' => 'CONTADO', 'medio_pago' => 'EFECTIVO', 'num_operacion' => '', 'monto_recibido' => 100,
    'fecha_vencimiento' => '', 'observacion' => '', 'estado' => 'Aceptado'
  );
  $items = array(
    array('articulo' => 'Perno hexagonal 1/2" x 2"', 'unidad' => 'und', 'cantidad' => 10, 'precio_venta' => 1.50, 'descuento' => 0, 'subtotal' => 15.00),
    array('articulo' => 'Cable mellizo N 14', 'unidad' => 'm', 'cantidad' => 5.5, 'precio_venta' => 3.00, 'descuento' => 1.00, 'subtotal' => 15.50),
    array('articulo' => 'Pintura esmalte blanco 1/4 gl', 'unidad' => 'und', 'cantidad' => 1, 'precio_venta' => 28.00, 'descuento' => 0, 'subtotal' => 28.00),
  );
} else {
  $venta = new Venta();
  if (!puedeVerTodasLasVentas() && !$venta->esDelUsuario($idReporte, (int)$_SESSION['idusuario'])) {
    echo "Solo puedes ver tus propias ventas";
    ob_end_flush();
    exit;
  }
  $rspta = $venta->ventacabecera($idReporte);
  $cab = $rspta ? $rspta->fetch_assoc() : null;
  if (!$cab) {
    echo "No se encontró la venta solicitada.";
    ob_end_flush();
    exit;
  }
  $rsptad = $venta->ventadetalles($idReporte);
  while ($rsptad && ($regd = $rsptad->fetch_assoc())) {
    $items[] = $regd;
  }
}

// ---------- Calculos ----------
$total = (float)$cab['total_venta'];
$impuesto = (float)$cab['impuesto'];
$base = $impuesto > 0 ? $total / (1 + ($impuesto / 100)) : $total;
$igv = $total - $base;
$descuentos = 0.0;
$unidades = 0.0;
foreach ($items as $it) {
  $descuentos += (float)$it['descuento'];
  $unidades += (float)$it['cantidad'] * (isset($it['factor']) ? (float)$it['factor'] : 1);
}
$mediosTexto = array('EFECTIVO' => 'Efectivo', 'DEPOSITO' => 'Depósito en cuenta', 'TARJETA' => 'Tarjeta', 'TRANSFERENCIA' => 'Transferencia', 'YAPE' => 'Yape', 'PLIN' => 'Plin', 'OTRO' => 'Otro', 'NOTA_CREDITO' => 'Nota de crédito');
$esCredito = strtoupper((string)$cab['tipo_pago']) === 'CREDITO';

// Pagos: una linea por medio (pago mixto); al credito son el adelanto
$pagosTicket = array();
if ($esPrueba) {
  $pagosTicket = array(
    array('medio_pago' => 'EFECTIVO', 'monto' => 40.00, 'recibido' => 50.00, 'num_operacion' => '', 'vuelto' => 10.00),
    array('medio_pago' => 'YAPE', 'monto' => 18.50, 'recibido' => null, 'num_operacion' => '123456', 'vuelto' => 0)
  );
} else {
  $pagosTicket = $venta->pagos($idReporte);
}
$pagadoTicket = 0.0;
$recibidoTicket = 0.0;
$vueltoTicket = 0.0;
foreach ($pagosTicket as $pg) {
  $pagadoTicket += (float)$pg['monto'];
  if ($pg['medio_pago'] === 'EFECTIVO' && $pg['recibido'] !== null && (float)$pg['recibido'] > 0) {
    $recibidoTicket += (float)$pg['recibido'];
    $vueltoTicket += (float)$pg['vuelto'];
  }
}

// Cliente generico: no se imprime el documento vacio o de relleno
$docCliente = trim((string)$cab['num_documento']);
$mostrarDocCliente = $docCliente !== '' && !preg_match('/^0+$/', $docCliente);

// Sin logo propio no se imprime el generico del producto
$logo = ($cfgTicket['logo'] && marcaEmpresa()['tiene_logo']) ? marcaUrlLogo('../') : '';

$ancho = (int)$cfgTicket['ancho'];
$copias = $esPrueba ? 1 : (int)$cfgTicket['copias'];
// QR: lleva a comprobante.php, donde el cliente ve, imprime o descarga solo esta venta
$urlQr = '';
if ($cfgTicket['qr']) {
  if ($esPrueba) {
    $urlQr = urlComprobantePublico('EJEMPLO');
  } else {
    $codigoPublico = $venta->codigoPublico($idReporte);
    $urlQr = $codigoPublico !== '' ? urlComprobantePublico($codigoPublico) : '';
  }
}
$qrSvg = $urlQr !== '' ? qrSvg($urlQr, 2) : '';

$titulo = $cab['tipo_comprobante'] . ' ' . $cab['serie_comprobante'] . '-' . $cab['num_comprobante'];

function mTicket($simbolo, $valor) {
  return $simbolo . ' ' . number_format((float)$valor, 2);
}

// Nombre impreso de cada comprobante (el "Ticket" del POS es la nota de venta)
$nombresDoc = array('Boleta' => 'BOLETA DE VENTA', 'Factura' => 'FACTURA', 'Ticket' => 'NOTA DE VENTA');
$nombreDoc = isset($nombresDoc[$cab['tipo_comprobante']]) ? $nombresDoc[$cab['tipo_comprobante']] : mb_strtoupper((string)$cab['tipo_comprobante'], 'UTF-8');
$esFactura = $cab['tipo_comprobante'] === 'Factura';

// Fecha "d/m/Y H:i" en dos datos, como en los comprobantes impresos
$partesFecha = explode(' ', trim((string)$cab['fecha']), 2);
$fechaEmision = $partesFecha[0];
$horaEmision = isset($partesFecha[1]) ? $partesFecha[1] : '';

$codigoMoneda = !empty($cfgEmpresa['moneda']) ? strtoupper((string)$cfgEmpresa['moneda']) : 'PEN';
$nombreMoneda = obtenerNombreMonedaLetras($codigoMoneda);
$enLetras = new EnLetras();
$totalLetras = $enLetras->ValorEnLetras(round($total, 2), $nombreMoneda);

$direccionCliente = isset($cab['direccion']) ? trim((string)$cab['direccion']) : '';
$direccionEmpresa = trim($cfgEmpresa['direccion_linea1'] . ' ' . $cfgEmpresa['direccion_linea2']);
$contactoEmpresa = trim((isset($cfgEmpresa['email']) ? $cfgEmpresa['email'] : '') . '   ' . (isset($cfgEmpresa['web']) ? $cfgEmpresa['web'] : ''));
$nombreComercial = $cfgEmpresa['nombre_comercial'] !== '' ? $cfgEmpresa['nombre_comercial'] : $cfgEmpresa['nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($titulo); ?></title>
  <link rel="stylesheet" href="../public/css/ticket.css?v=<?php echo e(APP_VERSION); ?>">
  <style>
    @page { size: <?php echo $ancho; ?>mm auto; margin: 0; }
  </style>
</head>
<body class="ticket-<?php echo $ancho; ?><?php echo $auto ? ' ticket-auto' : ''; ?>">
<?php if (!$auto) { ?>
  <div class="ticket-toolbar">
    <button type="button" onclick="window.print()">Imprimir</button>
    <button type="button" onclick="window.close()">Cerrar</button>
    <span>Papel de <?php echo $ancho; ?> mm<?php echo $copias > 1 ? ' · ' . $copias . ' copias' : ''; ?></span>
<?php if ($urlQr !== '' && urlEsSoloLocal($urlQr)) { ?>
    <span class="ticket-aviso">El QR apunta a <?php echo e(parse_url($urlQr, PHP_URL_HOST)); ?>: el celular del cliente no podrá abrirlo. Configura la dirección pública en Empresa &gt; Ticket.</span>
<?php } ?>
  </div>
<?php } ?>
<?php for ($copia = 1; $copia <= $copias; $copia++) { ?>
  <div class="ticket">
    <header class="t-center t-cabecera">
<?php if ($logo !== '') { ?>
      <img class="t-logo" src="<?php echo e($logo); ?>" alt="">
<?php } ?>
      <div class="t-empresa"><?php echo e($nombreComercial); ?></div>
<?php if ($cfgEmpresa['nombre'] !== '' && strcasecmp($cfgEmpresa['nombre'], $nombreComercial) !== 0) { ?>
      <div class="t-razon"><?php echo e($cfgEmpresa['nombre']); ?></div>
<?php } ?>
<?php if ($cfgEmpresa['ruc'] !== '' || $cfgEmpresa['telefono'] !== '') { ?>
      <div class="t-contacto">
<?php   if ($cfgEmpresa['ruc'] !== '') { ?>
        <span>RUC: <?php echo e($cfgEmpresa['ruc']); ?></span>
<?php   } ?>
<?php   if ($cfgEmpresa['telefono'] !== '') { ?>
        <span>Telf: <?php echo e($cfgEmpresa['telefono']); ?></span>
<?php   } ?>
      </div>
<?php } ?>
<?php if ($direccionEmpresa !== '') { ?>
      <div class="t-direccion"><?php echo e($direccionEmpresa); ?></div>
<?php } ?>
<?php if ($contactoEmpresa !== '') { ?>
      <div class="t-direccion"><?php echo e($contactoEmpresa); ?></div>
<?php } ?>
<?php if ($cfgTicket['cabecera'] !== '') { ?>
      <div class="t-nota"><?php echo e($cfgTicket['cabecera']); ?></div>
<?php } ?>
    </header>

    <div class="t-documento">
      <div class="t-doc-tipo"><?php echo e($nombreDoc); ?></div>
      <div class="t-doc-num"><?php echo e($cab['serie_comprobante'] . '-' . $cab['num_comprobante']); ?></div>
    </div>
<?php if ($cab['estado'] === 'Anulado') { ?>
    <div class="t-center t-anulado">*** ANULADO ***</div>
<?php } ?>
<?php if ($esPrueba) { ?>
    <div class="t-center t-anulado">*** TICKET DE PRUEBA ***</div>
<?php } ?>
<?php if ($copias > 1) { ?>
    <div class="t-center t-copia"><?php echo $copia === 1 ? 'Copia cliente' : 'Copia ' . $copia; ?></div>
<?php } ?>

    <div class="t-doble"></div>
    <dl class="t-datos">
      <dt><?php echo $esFactura ? 'Razón social' : 'Señor(es)'; ?></dt><dd><?php echo e($cab['cliente']); ?></dd>
<?php if ($mostrarDocCliente) { ?>
      <dt>N° <?php echo e($cab['tipo_documento'] !== '' ? $cab['tipo_documento'] : 'Doc.'); ?></dt><dd><?php echo e($docCliente); ?></dd>
<?php } ?>
<?php if ($direccionCliente !== '') { ?>
      <dt>Domicilio</dt><dd><?php echo e($direccionCliente); ?></dd>
<?php } ?>
      <dt>Fecha</dt><dd><?php echo e(trim($fechaEmision . '  ' . $horaEmision)); ?></dd>
      <dt>Pago</dt><dd><?php echo $esCredito ? 'CRÉDITO' : 'CONTADO'; ?> · <?php echo e($nombreMoneda); ?></dd>
<?php if ($esCredito && !empty($cab['fecha_vencimiento'])) { ?>
      <dt>Vence</dt><dd><?php echo e(date('d/m/Y', strtotime($cab['fecha_vencimiento']))); ?></dd>
<?php } ?>
    </dl>

    <div class="t-doble"></div>
    <table class="t-items<?php echo $descuentos > 0 ? '' : ' t-sin-dscto'; ?>">
      <thead>
        <tr>
          <th class="t-c-cant">Cant.</th>
          <th class="t-c-desc">Descripción</th>
          <th class="t-c-num t-c-precio">P.Unit</th>
          <th class="t-c-num t-c-dscto">Dscto</th>
          <th class="t-c-num">Importe</th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($items as $it) { ?>
        <tr>
          <td class="t-c-cant"><?php echo formatearCantidad($it['cantidad']); ?></td>
          <td class="t-c-desc">
            <span class="t-item-nombre"><?php echo e($it['articulo']); ?></span>
            <span class="t-item-unidad"><?php echo e($it['unidad']); ?><span class="t-solo58"> x <?php echo number_format((float)$it['precio_venta'], 2); ?><?php if ((float)$it['descuento'] > 0) { ?> · Dscto -<?php echo number_format((float)$it['descuento'], 2); ?><?php } ?></span></span>
          </td>
          <td class="t-c-num t-c-precio"><?php echo number_format((float)$it['precio_venta'], 2); ?></td>
          <td class="t-c-num t-c-dscto"><?php echo number_format((float)$it['descuento'], 2); ?></td>
          <td class="t-c-num t-c-importe"><?php echo number_format((float)$it['cantidad'] * (float)$it['precio_venta'] - (float)$it['descuento'], 2); ?></td>
        </tr>
<?php } ?>
      </tbody>
    </table>

    <div class="t-linea"></div>
    <div class="t-totales">
<?php if ($impuesto > 0) { ?>
      <div class="t-fila"><span>Op. gravada</span><span><?php echo e(mTicket($simbolo, $base)); ?></span></div>
      <div class="t-fila"><span>IGV (<?php echo number_format($impuesto, 0); ?>%)</span><span><?php echo e(mTicket($simbolo, $igv)); ?></span></div>
<?php } ?>
<?php if ($descuentos > 0) { ?>
      <div class="t-fila"><span>Descuentos aplicados</span><span>-<?php echo e(mTicket($simbolo, $descuentos)); ?></span></div>
<?php } ?>
    </div>
    <div class="t-total"><span>IMPORTE TOTAL</span><span><?php echo e(mTicket($simbolo, $total)); ?></span></div>
    <div class="t-letras">SON: <?php echo e($totalLetras); ?></div>

    <div class="t-pagos">
<?php foreach ($pagosTicket as $pg) { ?>
      <div class="t-fila"><span><?php echo e(($esCredito ? 'Adelanto ' : '') . (isset($mediosTexto[$pg['medio_pago']]) ? $mediosTexto[$pg['medio_pago']] : $pg['medio_pago'])); ?></span><span><?php echo e(mTicket($simbolo, $pg['monto'])); ?></span></div>
<?php   if (!empty($pg['num_operacion'])) { ?>
      <div class="t-fila t-desc"><span>&nbsp;&nbsp;N° operación</span><span><?php echo e($pg['num_operacion']); ?></span></div>
<?php   } ?>
<?php } ?>
<?php if ($esCredito) { ?>
      <div class="t-fila t-vuelto"><span>Saldo a crédito</span><span><?php echo e(mTicket($simbolo, $total - $pagadoTicket)); ?></span></div>
<?php } elseif ($recibidoTicket > 0) { ?>
      <div class="t-fila"><span>Recibido<?php echo count($pagosTicket) > 1 ? ' (efectivo)' : ''; ?></span><span><?php echo e(mTicket($simbolo, $recibidoTicket)); ?></span></div>
      <div class="t-fila t-vuelto"><span>Vuelto</span><span><?php echo e(mTicket($simbolo, $vueltoTicket)); ?></span></div>
<?php } ?>
    </div>
<?php if (!empty($cab['observacion'])) { ?>
    <div class="t-nota">Obs.: <?php echo e($cab['observacion']); ?></div>
<?php } ?>

    <div class="t-cierre<?php echo $qrSvg !== '' ? ' t-con-qr' : ''; ?>">
      <div class="t-cierre-datos">
        <div><b>Cajero:</b> <?php echo e($cab['usuario']); ?></div>
        <div><b>Artículos:</b> <?php echo count($items); ?> (<?php echo formatearCantidad($unidades); ?> und)</div>
<?php if ($qrSvg !== '') { ?>
        <div class="t-qr-texto">Escanea para ver tu comprobante</div>
<?php } ?>
        <div class="t-pie">Representación impresa de la <?php echo e($nombreDoc); ?></div>
      </div>
<?php if ($qrSvg !== '') { ?>
      <div class="t-qr"><?php echo $qrSvg; ?></div>
<?php } ?>
    </div>

    <footer class="t-center">
<?php if ($cfgTicket['leyenda'] !== '') { ?>
      <div class="t-leyenda"><?php echo e($cfgTicket['leyenda']); ?></div>
<?php } ?>
<?php if (trim((string)$cfgEmpresa['mensaje_ticket']) !== '') { ?>
      <div class="t-mensaje"><?php echo e(mb_strtoupper($cfgEmpresa['mensaje_ticket'], 'UTF-8')); ?></div>
<?php } ?>
    </footer>
  </div>
<?php } ?>
<?php if ($auto) { ?>
<script>
  // Espera a que cargue el logo para no imprimir un hueco
  window.addEventListener("load", function () {
    var avisar = function () { try { window.parent.postMessage("ticket-impreso", window.location.origin); } catch (e) {} };
    window.addEventListener("afterprint", avisar);
    setTimeout(function () { window.focus(); window.print(); }, 150);
  });
</script>
<?php } ?>
</body>
</html>
<?php
ob_end_flush();
