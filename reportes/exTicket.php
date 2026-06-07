<?php
ob_start();
if (strlen(session_id()) < 1) {
  session_start();
}

if (!isset($_SESSION['nombre'])) {
  echo "Debe ingresar al sistema correctamente para visualizar el reporte";
} else {
  if ($_SESSION['ventas'] == 1) {
    require_once "../modelos/Venta.php";
    require_once "../modelos/Empresa.php";

    $venta = new Venta();
    $rspta = $venta->ventacabecera($_GET["id"]);
    $reg = $rspta->fetch_object();

    if (!$reg) {
      echo "No se encontró la venta solicitada.";
      ob_end_flush();
      exit;
    }

    $empresaModel = new Empresa();
    $cfgEmpresa = $empresaModel->datosReporte();
    $codigoMoneda = !empty($cfgEmpresa["moneda"]) ? strtoupper((string)$cfgEmpresa["moneda"]) : 'PEN';
    $simboloMoneda = obtenerSimboloMoneda($codigoMoneda);

    $empresa = !empty($cfgEmpresa["nombre"]) ? $cfgEmpresa["nombre"] : 'PERNO CENTRO "SEÑOR DE HUANCA"';
    $documento = !empty($cfgEmpresa["ruc"]) ? $cfgEmpresa["ruc"] : "20603558422";
    $direccion = trim((string)$cfgEmpresa["direccion_linea1"]." ".(string)$cfgEmpresa["direccion_linea2"]);
    if ($direccion === '') {
      $direccion = "Bar. Santa Rosa S/N (costado Grifo Wari), Abancay - Apurimac";
    }
    $telefono = !empty($cfgEmpresa["telefono"]) ? $cfgEmpresa["telefono"] : "932381391";
    $email = !empty($cfgEmpresa["email"]) ? $cfgEmpresa["email"] : "ventas@pernocentro.com";

    $logo = "logo1.jpeg";
    if (!empty($cfgEmpresa["logo"])) {
      $logoEmpresaFS = realpath(__DIR__ . "/../files/empresa/" . $cfgEmpresa["logo"]);
      if ($logoEmpresaFS && file_exists($logoEmpresaFS)) {
        $logo = "../files/empresa/" . $cfgEmpresa["logo"];
      } elseif (file_exists(__DIR__ . "/" . $cfgEmpresa["logo"])) {
        $logo = $cfgEmpresa["logo"];
      }
    } elseif (file_exists(__DIR__ . "/logo.png")) {
      $logo = "logo.png";
    }

    $total = (float)$reg->total_venta;
    $impuesto = (float)$reg->impuesto;
    $base = $impuesto > 0 ? $total / (1 + ($impuesto / 100)) : $total;
    $igv = $total - $base;

    function e($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
  <title>Comprobante de Venta</title>
  <link rel="stylesheet" href="../public/css/ticket.css?v=20260420a">
</head>
<body onload="window.print();">
  <div class="ticket-shell">
    <div class="ticket-head">
      <div class="brand-row">
        <img class="brand-logo" src="<?php echo e($logo); ?>" alt="Logo">
        <div>
          <h1 class="brand-name"><?php echo e($empresa); ?></h1>
          <p class="brand-sub">RUC <?php echo e($documento); ?></p>
        </div>
      </div>
      <div class="doc-badge"><?php echo e($reg->tipo_comprobante); ?> <?php echo e($reg->serie_comprobante . " - " . $reg->num_comprobante); ?></div>
    </div>

    <div class="ticket-body">
      <p class="info-line"><strong>Fecha:</strong> <?php echo e($reg->fecha); ?></p>
      <p class="info-line"><strong>Dirección:</strong> <?php echo e($direccion); ?></p>
      <p class="info-line"><strong>Contacto:</strong> <?php echo e($telefono); ?> | <?php echo e($email); ?></p>

      <hr class="sep">

      <div class="client-card">
        <p class="client-title">Cliente</p>
        <p class="info-line"><strong>Nombre:</strong> <?php echo e($reg->cliente); ?></p>
        <p class="info-line"><strong>Documento:</strong> <?php echo e($reg->tipo_documento . ": " . $reg->num_documento); ?></p>
      </div>

      <table class="items-table">
        <thead>
          <tr>
            <th class="qty">Cant.</th>
            <th class="desc">Descripción</th>
            <th class="amount">Importe</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $rsptad = $venta->ventadetalles($_GET["id"]);
          $cantidadTotal = 0.0;
          while ($regd = $rsptad->fetch_object()) {
            $cantidadTotal += (float)$regd->cantidad;
          ?>
          <tr>
            <td class="qty"><?php echo number_format((float)$regd->cantidad, 0) . " " . e($regd->unidad); ?></td>
            <td class="desc"><?php echo e($regd->articulo); ?></td>
            <td class="amount"><?php echo e($simboloMoneda); ?> <?php echo number_format((float)$regd->subtotal, 2); ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>

      <div class="totals">
        <div class="totals-row"><span>Subtotal</span><span><?php echo e($simboloMoneda); ?> <?php echo number_format($base, 2); ?></span></div>
        <div class="totals-row"><span>IGV (<?php echo number_format($impuesto, 2); ?>%)</span><span><?php echo e($simboloMoneda); ?> <?php echo number_format($igv, 2); ?></span></div>
        <div class="totals-row totals-main"><span>Total</span><strong><?php echo e($simboloMoneda); ?> <?php echo number_format($total, 2); ?></strong></div>
      </div>

      <p class="info-line" style="margin-top:8px;"><strong>Items:</strong> <?php echo number_format($cantidadTotal, 0); ?></p>

      <div class="ticket-foot">
        <p>Gracias por su compra.</p>
        <p>Este comprobante fue generado por el sistema.</p>
      </div>
    </div>
  </div>
</body>
</html>
<?php
  } else {
    echo "No tiene permiso para visualizar el reporte";
  }
}

ob_end_flush();
?>
