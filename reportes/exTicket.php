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
$recibido = isset($cab['monto_recibido']) && $cab['monto_recibido'] !== null ? (float)$cab['monto_recibido'] : 0.0;
$vuelto = $recibido > 0 ? max(0, $recibido - $total) : 0.0;
$mediosTexto = array('EFECTIVO' => 'Efectivo', 'DEPOSITO' => 'Depósito en cuenta', 'TARJETA' => 'Tarjeta', 'TRANSFERENCIA' => 'Transferencia', 'YAPE' => 'Yape', 'PLIN' => 'Plin', 'OTRO' => 'Otro');
$medio = strtoupper((string)$cab['medio_pago']);
$medioTexto = isset($mediosTexto[$medio]) ? $mediosTexto[$medio] : $medio;
$esCredito = strtoupper((string)$cab['tipo_pago']) === 'CREDITO';

// Cliente generico: no se imprime el documento vacio o de relleno
$docCliente = trim((string)$cab['num_documento']);
$mostrarDocCliente = $docCliente !== '' && !preg_match('/^0+$/', $docCliente);

// Sin logo propio no se imprime el generico del producto
$logo = ($cfgTicket['logo'] && marcaEmpresa()['tiene_logo']) ? marcaUrlLogo('../') : '';

$ancho = (int)$cfgTicket['ancho'];
$copias = $esPrueba ? 1 : (int)$cfgTicket['copias'];
$titulo = $cab['tipo_comprobante'] . ' ' . $cab['serie_comprobante'] . '-' . $cab['num_comprobante'];

function mTicket($simbolo, $valor) {
  return $simbolo . ' ' . number_format((float)$valor, 2);
}
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
  </div>
<?php } ?>
<?php for ($copia = 1; $copia <= $copias; $copia++) { ?>
  <div class="ticket">
    <header class="t-center">
<?php if ($logo !== '') { ?>
      <img class="t-logo" src="<?php echo e($logo); ?>" alt="">
<?php } ?>
      <div class="t-empresa"><?php echo e($cfgEmpresa['nombre_comercial'] !== '' ? $cfgEmpresa['nombre_comercial'] : $cfgEmpresa['nombre']); ?></div>
<?php if ($cfgEmpresa['nombre'] !== '' && strcasecmp($cfgEmpresa['nombre'], $cfgEmpresa['nombre_comercial']) !== 0) { ?>
      <div><?php echo e($cfgEmpresa['nombre']); ?></div>
<?php } ?>
<?php if ($cfgEmpresa['ruc'] !== '') { ?>
      <div>RUC <?php echo e($cfgEmpresa['ruc']); ?></div>
<?php } ?>
<?php if (trim($cfgEmpresa['direccion_linea1'] . ' ' . $cfgEmpresa['direccion_linea2']) !== '') { ?>
      <div><?php echo e(trim($cfgEmpresa['direccion_linea1'] . ' ' . $cfgEmpresa['direccion_linea2'])); ?></div>
<?php } ?>
<?php if ($cfgEmpresa['telefono'] !== '') { ?>
      <div>Tel. <?php echo e($cfgEmpresa['telefono']); ?></div>
<?php } ?>
<?php if ($cfgTicket['cabecera'] !== '') { ?>
      <div class="t-nota"><?php echo e($cfgTicket['cabecera']); ?></div>
<?php } ?>
    </header>

    <div class="t-sep"></div>
    <div class="t-center t-doc"><?php echo e(strtoupper($cab['tipo_comprobante'])); ?> DE VENTA<br><?php echo e($cab['serie_comprobante'] . '-' . $cab['num_comprobante']); ?></div>
<?php if ($cab['estado'] === 'Anulado') { ?>
    <div class="t-center t-anulado">*** ANULADO ***</div>
<?php } ?>
<?php if ($esPrueba) { ?>
    <div class="t-center t-anulado">*** TICKET DE PRUEBA ***</div>
<?php } ?>
<?php if ($copias > 1) { ?>
    <div class="t-center t-copia"><?php echo $copia === 1 ? 'Copia cliente' : 'Copia ' . $copia; ?></div>
<?php } ?>
    <div class="t-sep"></div>

    <div class="t-fila"><span>Fecha</span><span><?php echo e($cab['fecha']); ?></span></div>
    <div class="t-fila"><span>Cajero</span><span><?php echo e($cab['usuario']); ?></span></div>
    <div class="t-fila"><span>Cliente</span><span><?php echo e($cab['cliente']); ?></span></div>
<?php if ($mostrarDocCliente) { ?>
    <div class="t-fila"><span><?php echo e($cab['tipo_documento']); ?></span><span><?php echo e($docCliente); ?></span></div>
<?php } ?>

    <div class="t-sep"></div>
    <div class="t-fila t-cab"><span>Descripción</span><span>Importe</span></div>
    <div class="t-sep t-sep-fina"></div>
<?php foreach ($items as $it) { ?>
    <div class="t-item">
      <div class="t-item-nombre"><?php echo e($it['articulo']); ?></div>
      <div class="t-fila">
        <span><?php echo formatearCantidad($it['cantidad']) . ' ' . e($it['unidad']); ?> x <?php echo number_format((float)$it['precio_venta'], 2); ?></span>
        <span><?php echo number_format((float)$it['cantidad'] * (float)$it['precio_venta'], 2); ?></span>
      </div>
<?php if ((float)$it['descuento'] > 0) { ?>
      <div class="t-fila t-desc"><span>Descuento</span><span>-<?php echo number_format((float)$it['descuento'], 2); ?></span></div>
<?php } ?>
    </div>
<?php } ?>
    <div class="t-sep"></div>

<?php if ($descuentos > 0) { ?>
    <div class="t-fila"><span>Descuentos</span><span>-<?php echo e(mTicket($simbolo, $descuentos)); ?></span></div>
<?php } ?>
<?php if ($impuesto > 0) { ?>
    <div class="t-fila"><span>Op. gravada</span><span><?php echo e(mTicket($simbolo, $base)); ?></span></div>
    <div class="t-fila"><span>IGV (<?php echo number_format($impuesto, 0); ?>%)</span><span><?php echo e(mTicket($simbolo, $igv)); ?></span></div>
<?php } ?>
    <div class="t-fila t-total"><span>TOTAL</span><span><?php echo e(mTicket($simbolo, $total)); ?></span></div>
    <div class="t-sep t-sep-fina"></div>

<?php if ($esCredito) { ?>
    <div class="t-fila"><span>Pago</span><span>CRÉDITO</span></div>
<?php   if (!empty($cab['fecha_vencimiento'])) { ?>
    <div class="t-fila"><span>Vence</span><span><?php echo e(date('d/m/Y', strtotime($cab['fecha_vencimiento']))); ?></span></div>
<?php   } ?>
<?php } else { ?>
    <div class="t-fila"><span>Pago</span><span><?php echo e($medioTexto); ?></span></div>
<?php   if ($recibido > 0) { ?>
    <div class="t-fila"><span>Recibido</span><span><?php echo e(mTicket($simbolo, $recibido)); ?></span></div>
    <div class="t-fila t-vuelto"><span>Vuelto</span><span><?php echo e(mTicket($simbolo, $vuelto)); ?></span></div>
<?php   } ?>
<?php   if (!empty($cab['num_operacion'])) { ?>
    <div class="t-fila"><span>N° operación</span><span><?php echo e($cab['num_operacion']); ?></span></div>
<?php   } ?>
<?php } ?>
    <div class="t-fila"><span>Artículos</span><span><?php echo count($items); ?> (<?php echo formatearCantidad($unidades); ?> und)</span></div>
<?php if (!empty($cab['observacion'])) { ?>
    <div class="t-nota">Obs.: <?php echo e($cab['observacion']); ?></div>
<?php } ?>

    <div class="t-sep"></div>
    <footer class="t-center">
      <div class="t-mensaje"><?php echo e($cfgEmpresa['mensaje_ticket']); ?></div>
      <div class="t-pie">Representación impresa del comprobante interno</div>
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
