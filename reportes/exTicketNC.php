<?php
/**
 * Ticket termico de una nota de credito (devolucion o anulacion).
 *   ?id=N   (idnota)
 * Usa el mismo estilo que el ticket de venta (public/css/ticket.css).
 */
ob_start();
require_once "../config/seguridad.php";
requiereLogin(false);
require_once "../modelos/NotaCredito.php";
require_once "../modelos/Empresa.php";
require_once "Letras.php";

if (!usuarioTienePermiso('ventas')) {
  echo "No tiene permiso para visualizar la nota de crédito";
  ob_end_flush();
  exit;
}
$idnota = enteroSeguro(isset($_GET['id']) ? $_GET['id'] : 0);
$ncModel = new NotaCredito();
$cab = $ncModel->cabecera($idnota);
if (!$cab) {
  echo "No se encontró la nota de crédito.";
  ob_end_flush();
  exit;
}
// Un vendedor sin "Consulta ventas" solo ve las notas de sus ventas o las que el emitio
if (!puedeVerTodasLasVentas() && (int)$cab['idvendedor'] !== (int)$_SESSION['idusuario'] && (int)$cab['idusuario'] !== (int)$_SESSION['idusuario']) {
  echo "Solo puedes ver las notas de tus ventas";
  ob_end_flush();
  exit;
}
$items = $ncModel->detalle($idnota);

$empresaModel = new Empresa();
$cfgEmpresa = $empresaModel->datosReporte();
$cfgTicket = $empresaModel->configTicket();
$simbolo = obtenerSimboloMoneda(!empty($cfgEmpresa["moneda"]) ? $cfgEmpresa["moneda"] : 'PEN');
$nombreMoneda = obtenerNombreMonedaLetras(!empty($cfgEmpresa["moneda"]) ? $cfgEmpresa["moneda"] : 'PEN');
$total = (float)$cab['total'];
$totalLetras = (new EnLetras())->ValorEnLetras(round($total, 2), $nombreMoneda);
$ancho = (int)$cfgTicket['ancho'];
$logo = ($cfgTicket['logo'] && marcaEmpresa()['tiene_logo']) ? marcaUrlLogo('../') : '';
$nombreComercial = $cfgEmpresa['nombre_comercial'] !== '' ? $cfgEmpresa['nombre_comercial'] : $cfgEmpresa['nombre'];
$numero = $cab['serie'] . '-' . $cab['numero'];
$docAfectado = $cab['tipo_comprobante'] . ' ' . $cab['serie_comprobante'] . '-' . $cab['num_comprobante'];
$tipos = NotaCredito::TIPOS;
$medios = array('EFECTIVO' => 'Efectivo', 'YAPE' => 'Yape', 'PLIN' => 'Plin', 'TARJETA' => 'Extorno a tarjeta', 'TRANSFERENCIA' => 'Transferencia',
  'DEPOSITO' => 'Depósito', 'OTRO' => 'Otro', 'SALDO_A_FAVOR' => 'Saldo a favor', 'ORIGINAL' => 'Por los medios de la venta');
$docCliente = trim((string)$cab['num_documento']);
function mNC($simbolo, $v) { return $simbolo . ' ' . number_format((float)$v, 2); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nota de crédito <?php echo e($numero); ?></title>
  <link rel="stylesheet" href="../public/css/ticket.css?v=<?php echo e(APP_VERSION); ?>">
  <style>@page { size: <?php echo $ancho; ?>mm auto; margin: 0; }</style>
</head>
<body class="ticket-<?php echo $ancho; ?>">
  <div class="ticket-toolbar">
    <button type="button" onclick="window.print()">Imprimir</button>
    <button type="button" onclick="window.close()">Cerrar</button>
    <span>Papel de <?php echo $ancho; ?> mm</span>
  </div>
  <div class="ticket">
    <header class="t-center t-cabecera">
<?php if ($logo !== '') { ?>
      <img class="t-logo" src="<?php echo e($logo); ?>" alt="">
<?php } ?>
      <div class="t-empresa"><?php echo e($nombreComercial); ?></div>
<?php if ($cfgEmpresa['ruc'] !== '') { ?>
      <div class="t-contacto"><span>RUC: <?php echo e($cfgEmpresa['ruc']); ?></span></div>
<?php } ?>
<?php if (trim($cfgEmpresa['direccion_linea1'] . ' ' . $cfgEmpresa['direccion_linea2']) !== '') { ?>
      <div class="t-direccion"><?php echo e(trim($cfgEmpresa['direccion_linea1'] . ' ' . $cfgEmpresa['direccion_linea2'])); ?></div>
<?php } ?>
    </header>

    <div class="t-documento">
      <div class="t-doc-tipo">NOTA DE CRÉDITO</div>
      <div class="t-doc-num"><?php echo e($numero); ?></div>
    </div>
<?php if ($cab['estado'] !== 'EMITIDA') { ?>
    <div class="t-center t-anulado">*** <?php echo e($cab['estado']); ?> ***</div>
<?php } ?>

    <div class="t-doble"></div>
    <dl class="t-datos">
      <dt>Señor(es)</dt><dd><?php echo e($cab['cliente']); ?></dd>
<?php if ($docCliente !== '' && !preg_match('/^0+$/', $docCliente)) { ?>
      <dt>N° <?php echo e($cab['tipo_documento']); ?></dt><dd><?php echo e($docCliente); ?></dd>
<?php } ?>
      <dt>Fecha</dt><dd><?php echo e($cab['fecha']); ?></dd>
      <dt>Modifica a</dt><dd><?php echo e($docAfectado); ?> (<?php echo e($cab['fecha_venta']); ?>)</dd>
      <dt>Tipo</dt><dd><?php echo e(isset($tipos[$cab['tipo_nota']]) ? $tipos[$cab['tipo_nota']] : $cab['tipo_nota']); ?></dd>
      <dt>Motivo</dt><dd><?php echo e($cab['motivo']); ?></dd>
    </dl>

    <div class="t-doble"></div>
    <table class="t-items">
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
            <span class="t-item-nombre"><?php echo e(html_entity_decode($it['articulo'], ENT_QUOTES, 'UTF-8')); ?></span>
            <span class="t-item-unidad"><?php echo e($it['unidad']); ?><?php echo (int)$it['reingresa_stock'] === 1 ? '' : ' · DAÑADO'; ?><span class="t-solo58"> x <?php echo number_format((float)$it['precio'], 2); ?></span></span>
          </td>
          <td class="t-c-num t-c-precio"><?php echo number_format((float)$it['precio'], 2); ?></td>
          <td class="t-c-num t-c-dscto"><?php echo number_format((float)$it['descuento'], 2); ?></td>
          <td class="t-c-num t-c-importe"><?php echo number_format((float)$it['subtotal'], 2); ?></td>
        </tr>
<?php } ?>
      </tbody>
    </table>

    <div class="t-total"><span>TOTAL NOTA</span><span><?php echo e(mNC($simbolo, $total)); ?></span></div>
    <div class="t-letras">SON: <?php echo e($totalLetras); ?></div>

    <div class="t-pagos">
<?php if ((float)$cab['monto_credito'] > 0) { ?>
      <div class="t-fila"><span><?php echo $cab['reintegro'] === 'ORIGINAL' ? 'Deuda anulada' : 'Descontado de la deuda'; ?></span><span><?php echo e(mNC($simbolo, $cab['monto_credito'])); ?></span></div>
<?php } ?>
<?php if ((float)$cab['monto_reintegro'] > 0) { ?>
      <div class="t-fila t-vuelto"><span><?php echo $cab['reintegro'] === 'SALDO_A_FAVOR' ? 'Saldo a favor' : 'Devuelto'; ?></span><span><?php echo e(mNC($simbolo, $cab['monto_reintegro'])); ?></span></div>
      <div class="t-fila t-desc"><span>&nbsp;&nbsp;Forma</span><span><?php echo e(isset($medios[$cab['reintegro']]) ? $medios[$cab['reintegro']] : $cab['reintegro']); ?></span></div>
<?php } ?>
<?php if ($cab['reintegro'] === 'SALDO_A_FAVOR') { ?>
      <div class="t-nota">Presente este comprobante (<?php echo e($numero); ?>) para usar su saldo a favor en su próxima compra.</div>
<?php } ?>
    </div>

    <div class="t-cierre">
      <div class="t-cierre-datos">
        <div><b>Registró:</b> <?php echo e($cab['usuario']); ?></div>
<?php if ($cab['autorizo'] !== '') { ?>
        <div><b>Autorizó:</b> <?php echo e($cab['autorizo']); ?></div>
<?php } ?>
      </div>
    </div>
    <br><br>
    <div class="t-center">______________________<br>Firma del cliente</div>
    <footer class="t-center">
      <div class="t-pie">Representación impresa de la<br>NOTA DE CRÉDITO</div>
    </footer>
  </div>
</body>
</html>
<?php
ob_end_flush();
