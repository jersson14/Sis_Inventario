<?php
require_once "../config/seguridad.php";
requiereLogin(false);
// Sin permiso de escritorio (ej. vendedor) se va directo a su pagina de trabajo;
// asi los enlaces "Inicio" de todo el sistema le sirven igual
if (!usuarioTienePermiso('escritorio')) {
  header("Location: " . paginaInicioUsuario());
  exit;
}
$tituloPagina = "Escritorio";
$iconoPagina = "fa-dashboard";
require 'header.php';

if (usuarioTienePermiso('escritorio')) {
  require_once "../modelos/Consultas.php";
  $consulta = new Consultas();

  if (!function_exists('filasResultado')) {
    function filasResultado($rs) {
      if (is_array($rs)) return $rs;
      $out = array();
      if ($rs instanceof mysqli_result) {
        while ($row = $rs->fetch_assoc()) { $out[] = $row; }
      }
      return $out;
    }
  }

  $hoy = date("Y-m-d");
  $inicioDefault = date("Y-m-01");
  $fechaInicio = fechaSegura(isset($_GET["fecha_inicio"]) ? $_GET["fecha_inicio"] : '', $inicioDefault);
  $fechaFin = fechaSegura(isset($_GET["fecha_fin"]) ? $_GET["fecha_fin"] : '', $hoy);
  if (strtotime($fechaInicio) > strtotime($fechaFin)) { $t = $fechaInicio; $fechaInicio = $fechaFin; $fechaFin = $t; }
  $rangoTexto = date("d/m/Y", strtotime($fechaInicio)) . " – " . date("d/m/Y", strtotime($fechaFin));
  $codigoMoneda = obtenerMonedaEmpresaCodigo();
  $simbolo = obtenerSimboloMoneda($codigoMoneda);

  $regc = filasResultado($consulta->totalcomprarango($fechaInicio, $fechaFin));
  $totalc = $regc ? (float)$regc[0]['total_compra'] : 0;
  $regv = filasResultado($consulta->totalventarango($fechaInicio, $fechaFin));
  $totalv = $regv ? (float)$regv[0]['total_venta'] : 0;

  $kpiRow = filasResultado($consulta->kpisgenerales());
  $kpi = $kpiRow ? $kpiRow[0] : array();
  $articulosActivos = isset($kpi['articulos_activos']) ? (int)$kpi['articulos_activos'] : 0;
  $categoriasActivas = isset($kpi['categorias_activas']) ? (int)$kpi['categorias_activas'] : 0;
  $clientes = isset($kpi['clientes']) ? (int)$kpi['clientes'] : 0;
  $proveedores = isset($kpi['proveedores']) ? (int)$kpi['proveedores'] : 0;
  $stockTotal = isset($kpi['stock_total']) ? (float)$kpi['stock_total'] : 0;

  $util = $consulta->utilidadResumenRango($fechaInicio, $fechaFin);
  $utilidad = $util ? (float)$util['utilidad'] : 0;
  $margen = $util ? (float)$util['margen'] : 0;
  $numVentas = $util ? (int)$util['num_ventas'] : 0;
  $ticketPromedio = $util ? (float)$util['ticket_promedio'] : 0;
  $costoTotal = $util ? (float)$util['costo_total'] : 0;

  $alertas = $consulta->resumenAlertas((int)$_SESSION['idusuario']);
  if (!$alertas) { $alertas = array(); }
  $al = function ($k) use ($alertas) { return isset($alertas[$k]) ? $alertas[$k] : 0; };

  $diasPeriodo = (int)floor((strtotime($fechaFin) - strtotime($fechaInicio)) / 86400) + 1;
  if ($diasPeriodo <= 0) $diasPeriodo = 1;

  // Series para graficos
  $ventasDia = filasResultado($consulta->ventasPorDiaRango($fechaInicio, $fechaFin));
  $labelsVentasDia = array(); $dataVentasDia = array();
  foreach ($ventasDia as $r) { $labelsVentasDia[] = date("d/m", strtotime($r['fecha'])); $dataVentasDia[] = round((float)$r['total'], 2); }

  $comprasMap = array(); $ventasMap = array();
  foreach (filasResultado($consulta->comprasmensualesrango($fechaInicio, $fechaFin)) as $r) { $comprasMap[$r['periodo']] = round((float)$r['total'], 2); }
  foreach (filasResultado($consulta->ventasmensualesrango($fechaInicio, $fechaFin)) as $r) { $ventasMap[$r['periodo']] = round((float)$r['total'], 2); }
  $labelsMes = array(); $dataComprasMes = array(); $dataVentasMes = array();
  $cursor = strtotime(date("Y-m-01", strtotime($fechaInicio)));
  $finMes = strtotime(date("Y-m-01", strtotime($fechaFin)));
  while ($cursor <= $finMes) {
    $p = date("Y-m", $cursor);
    $labelsMes[] = date("M Y", $cursor);
    $dataComprasMes[] = isset($comprasMap[$p]) ? $comprasMap[$p] : 0;
    $dataVentasMes[] = isset($ventasMap[$p]) ? $ventasMap[$p] : 0;
    $cursor = strtotime("+1 month", $cursor);
  }

  $labelsTop = array(); $dataTop = array();
  foreach (filasResultado($consulta->topproductosvendidosrango($fechaInicio, $fechaFin, 7)) as $r) { $labelsTop[] = $r['producto']; $dataTop[] = round((float)$r['total'], 2); }
  $labelsCat = array(); $dataCat = array();
  foreach (filasResultado($consulta->ventasporcategoriarango($fechaInicio, $fechaFin, 8)) as $r) { $labelsCat[] = $r['categoria']; $dataCat[] = round((float)$r['total'], 2); }
  $labelsMedio = array(); $dataMedio = array();
  foreach (filasResultado($consulta->ventasPorMedioPago($fechaInicio, $fechaFin)) as $r) { $labelsMedio[] = $r['medio_pago']; $dataMedio[] = round((float)$r['total'], 2); }
  $labelsVend = array(); $dataVend = array();
  foreach (filasResultado($consulta->ventasPorVendedor($fechaInicio, $fechaFin, 8)) as $r) { $labelsVend[] = $r['vendedor']; $dataVend[] = round((float)$r['total'], 2); }
  $horas = array_fill(0, 24, 0);
  foreach (filasResultado($consulta->ventasPorHora($fechaInicio, $fechaFin)) as $r) { $h = (int)$r['hora']; if ($h >= 0 && $h < 24) $horas[$h] = round((float)$r['total'], 2); }
  $labelsHora = array(); for ($h = 0; $h < 24; $h++) { $labelsHora[] = sprintf("%02d:00", $h); }

  $movimientos = array();
  foreach (filasResultado($consulta->ultimomovimientosrango($fechaInicio, $fechaFin, 8)) as $r) {
    $movimientos[] = array('tipo' => $r['tipo'], 'fecha' => date("d/m/Y H:i", strtotime($r['fecha'])), 'documento' => $r['documento'], 'persona' => $r['persona'], 'total' => (float)$r['total']);
  }
  $hora = (int)date('G');
  $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
  $primerNombre = trim(explode(' ', trim((string)$_SESSION['nombre']))[0]);
?>
<div class="content-wrapper">
  <section class="content dashboard-wrap">
    <div class="page-head">
      <div class="dashboard-head">
        <h1><?php echo e($saludo); ?>, <?php echo e($primerNombre); ?> 👋</h1>
        <p>Así va tu negocio del <strong><?php echo e($rangoTexto); ?></strong>.</p>
      </div>
      <div class="quick-actions">
        <?php if (usuarioTienePermiso('ventas')) { ?><a href="venta.php?nuevo=1" class="btn btn-primary"><i class="fa fa-plus"></i> Nueva venta</a><?php } ?>
        <?php if (usuarioTienePermiso('compras')) { ?><a href="ingreso.php?nuevo=1" class="btn btn-default"><i class="fa fa-truck"></i> Nueva compra</a><?php } ?>
        <?php if (usuarioTienePermiso('caja') || usuarioTienePermiso('ventas')) { ?><a href="caja.php" class="btn btn-default"><i class="fa fa-money"></i> Caja</a><?php } ?>
      </div>
    </div>

    <div class="box dashboard-filter-box">
      <div class="box-body">
        <form method="get" action="escritorio.php" class="row dashboard-filter-form">
          <div class="col-md-2 col-sm-4 col-xs-6"><label>Desde</label><input type="date" class="form-control input-sm" name="fecha_inicio" value="<?php echo e($fechaInicio); ?>"></div>
          <div class="col-md-2 col-sm-4 col-xs-6"><label>Hasta</label><input type="date" class="form-control input-sm" name="fecha_fin" value="<?php echo e($fechaFin); ?>"></div>
          <div class="col-md-8 col-sm-12 col-xs-12 dashboard-filter-actions">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Aplicar</button>
            <span class="text-soft" style="margin-left:6px">Rápido:</span>
            <a href="escritorio.php?fecha_inicio=<?php echo $hoy; ?>&fecha_fin=<?php echo $hoy; ?>" class="btn btn-default btn-xs">Hoy</a>
            <a href="escritorio.php?fecha_inicio=<?php echo date('Y-m-d', strtotime('monday this week')); ?>&fecha_fin=<?php echo $hoy; ?>" class="btn btn-default btn-xs">Esta semana</a>
            <a href="escritorio.php?fecha_inicio=<?php echo $inicioDefault; ?>&fecha_fin=<?php echo $hoy; ?>" class="btn btn-default btn-xs">Este mes</a>
            <a href="escritorio.php?fecha_inicio=<?php echo date('Y-m-01', strtotime('-1 month')); ?>&fecha_fin=<?php echo date('Y-m-t', strtotime('-1 month')); ?>" class="btn btn-default btn-xs">Mes pasado</a>
            <a href="escritorio.php?fecha_inicio=<?php echo date('Y-01-01'); ?>&fecha_fin=<?php echo $hoy; ?>" class="btn btn-default btn-xs">Este año</a>
          </div>
        </form>
      </div>
    </div>

    <div class="row dashboard-kpis">
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <a href="venta.php" class="kpi-card kpi-sales"><div class="kpi-icon"><i class="fa fa-line-chart"></i></div><div class="kpi-meta"><span>Ventas del periodo</span><strong><?php echo e(formatearMoneda($totalv, $codigoMoneda)); ?></strong><small><?php echo $numVentas; ?> comprobante(s) · ticket prom. <?php echo e(formatearMoneda($ticketPromedio, $codigoMoneda)); ?></small></div></a>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <div class="kpi-card kpi-profit"><div class="kpi-icon"><i class="fa fa-trophy"></i></div><div class="kpi-meta"><span>Utilidad estimada</span><strong><?php echo e(formatearMoneda($utilidad, $codigoMoneda)); ?></strong><small>Margen <?php echo number_format($margen, 1); ?>% · costo <?php echo e(formatearMoneda($costoTotal, $codigoMoneda)); ?></small></div></div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <a href="ingreso.php" class="kpi-card kpi-buy"><div class="kpi-icon"><i class="fa fa-shopping-basket"></i></div><div class="kpi-meta"><span>Compras del periodo</span><strong><?php echo e(formatearMoneda($totalc, $codigoMoneda)); ?></strong><small>Promedio <?php echo e(formatearMoneda($totalc / $diasPeriodo, $codigoMoneda)); ?> / día</small></div></a>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <a href="procenter.php" class="kpi-card kpi-stock"><div class="kpi-icon"><i class="fa fa-cubes"></i></div><div class="kpi-meta"><span>Inventario</span><strong><?php echo e(formatearCantidad($stockTotal)); ?> und</strong><small><?php echo $articulosActivos; ?> artículos · <?php echo $categoriasActivas; ?> categorías</small></div></a>
      </div>
    </div>

    <div class="alert-cards">
      <a href="procenter.php" class="alert-card <?php echo $al('articulos_agotados') > 0 ? 'is-danger' : 'is-success'; ?>"><div class="alert-ico"><i class="fa fa-ban"></i></div><div><strong><?php echo (int)$al('articulos_agotados'); ?></strong><span>Artículos agotados</span></div></a>
      <a href="procenter.php" class="alert-card <?php echo $al('articulos_bajo_minimo') > 0 ? 'is-warning' : 'is-success'; ?>"><div class="alert-ico"><i class="fa fa-exclamation-triangle"></i></div><div><strong><?php echo (int)$al('articulos_bajo_minimo'); ?></strong><span>Bajo stock mínimo</span></div></a>
<?php if ((int)$al('usa_variantes') === 1): ?>
      <a href="articulo.php" class="alert-card <?php echo $al('variantes_agotadas') > 0 ? 'is-danger' : ($al('variantes_bajo_minimo') > 0 ? 'is-warning' : 'is-success'); ?>"><div class="alert-ico"><i class="fa fa-tags"></i></div><div><strong><?php echo (int)$al('variantes_agotadas'); ?> talla(s)/color(es)</strong><span>agotados · <?php echo (int)$al('variantes_bajo_minimo'); ?> bajo el mínimo</span></div></a>
<?php endif; ?>
<?php if ((int)$al('usa_vencimientos') === 1): ?>
      <a href="vencimientos.php?estado=<?php echo $al('lotes_vencidos') > 0 ? 'VENCIDO' : 'POR_VENCER'; ?>" class="alert-card <?php echo $al('lotes_vencidos') > 0 ? 'is-danger' : ($al('lotes_por_vencer') > 0 ? 'is-warning' : 'is-success'); ?>"><div class="alert-ico"><i class="fa fa-calendar-times-o"></i></div><div><strong><?php echo (int)$al('lotes_vencidos'); ?> vencido(s)</strong><span><?php echo (int)$al('lotes_por_vencer'); ?> por vencer en <?php echo (int)$al('dias_alerta_vencimiento'); ?> días</span></div></a>
<?php endif; ?>
      <a href="cuentas.php" class="alert-card <?php echo $al('cxc_vencidas') > 0 ? 'is-danger' : 'is-info'; ?>"><div class="alert-ico"><i class="fa fa-arrow-circle-down"></i></div><div><strong><?php echo e(formatearMoneda($al('cxc_pendiente_monto'), $codigoMoneda)); ?></strong><span>Por cobrar · <?php echo (int)$al('cxc_vencidas'); ?> vencida(s)</span></div></a>
      <a href="cuentas.php#cxp" class="alert-card <?php echo $al('cxp_vencidas') > 0 ? 'is-warning' : 'is-info'; ?>"><div class="alert-ico"><i class="fa fa-arrow-circle-up"></i></div><div><strong><?php echo e(formatearMoneda($al('cxp_pendiente_monto'), $codigoMoneda)); ?></strong><span>Por pagar · <?php echo (int)$al('cxp_vencidas'); ?> vencida(s)</span></div></a>
      <a href="caja.php" class="alert-card <?php echo (int)$al('caja_abierta') === 1 ? 'is-success' : 'is-purple'; ?>"><div class="alert-ico"><i class="fa <?php echo (int)$al('caja_abierta') === 1 ? 'fa-unlock' : 'fa-lock'; ?>"></i></div><div><strong><?php echo e(formatearMoneda($al('ventas_hoy_monto'), $codigoMoneda)); ?></strong><span>Ventas de hoy · caja <?php echo (int)$al('caja_abierta') === 1 ? 'abierta' : 'cerrada'; ?></span></div></a>
    </div>

    <div class="row">
      <div class="col-lg-8 col-md-7">
        <div class="box dashboard-box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-area-chart"></i> Ventas por día</h3></div>
          <div class="box-body"><canvas id="chartVentasDia"></canvas></div>
        </div>
      </div>
      <div class="col-lg-4 col-md-5">
        <div class="box dashboard-box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-credit-card"></i> Ventas por medio de pago</h3></div>
          <div class="box-body"><canvas id="chartMedio"></canvas></div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-6 col-md-6">
        <div class="box dashboard-box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-bar-chart"></i> Compras vs ventas por mes</h3></div>
          <div class="box-body"><canvas id="chartComparativo"></canvas></div>
        </div>
      </div>
      <div class="col-lg-6 col-md-6">
        <div class="box dashboard-box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-clock-o"></i> Ventas por hora del día</h3></div>
          <div class="box-body"><canvas id="chartHora"></canvas></div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-4 col-md-6">
        <div class="box dashboard-box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-star"></i> Top productos</h3></div>
          <div class="box-body"><canvas id="chartTop"></canvas></div>
        </div>
      </div>
      <div class="col-lg-4 col-md-6">
        <div class="box dashboard-box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-tags"></i> Ventas por categoría</h3></div>
          <div class="box-body"><canvas id="chartCategoria"></canvas></div>
        </div>
      </div>
      <div class="col-lg-4 col-md-12">
        <div class="box dashboard-box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-user"></i> Ventas por vendedor</h3></div>
          <div class="box-body"><canvas id="chartVendedor"></canvas></div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-history"></i> Últimos movimientos</h3><div class="box-tools"><a href="venta.php" class="btn btn-default btn-xs">Ver ventas</a> <a href="ingreso.php" class="btn btn-default btn-xs">Ver compras</a></div></div>
          <div class="box-body table-responsive" style="min-height:auto">
            <?php if (count($movimientos) === 0) { ?>
            <div class="empty-state"><i class="fa fa-inbox"></i><strong>Sin movimientos en el periodo</strong>Registra tu primera venta o compra para ver actividad aquí.</div>
            <?php } else { ?>
            <table class="table table-striped table-hover">
              <thead><tr><th>Tipo</th><th>Fecha</th><th>Documento</th><th>Cliente / Proveedor</th><th class="text-right">Total</th></tr></thead>
              <tbody>
              <?php foreach ($movimientos as $mov) { ?>
                <tr>
                  <td><span class="label <?php echo $mov['tipo'] === 'Venta' ? 'bg-green' : 'bg-aqua'; ?>"><?php echo e($mov['tipo']); ?></span></td>
                  <td><?php echo e($mov['fecha']); ?></td>
                  <td><?php echo e($mov['documento']); ?></td>
                  <td><?php echo e($mov['persona']); ?></td>
                  <td class="text-right money"><?php echo e(formatearMoneda($mov['total'], $codigoMoneda)); ?></td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
if (usuarioTienePermiso('escritorio')) {
?>
<script src="../public/js/Chart.bundle.min.js"></script>
<script>
(function () {
  var S = <?php echo json_encode($simbolo); ?>;
  var money = function (v) { return S + ' ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 0 }); };
  Chart.defaults.global.animation.duration = 400;
  Chart.defaults.global.defaultFontFamily = '"Segoe UI", Inter, system-ui, sans-serif';
  Chart.defaults.global.defaultFontColor = '#64748b';
  Chart.defaults.global.legend.labels.boxWidth = 12;
  Chart.defaults.global.tooltips.callbacks.label = function (item, data) {
    var ds = data.datasets[item.datasetIndex];
    var v = ds.data[item.index];
    return (ds.label ? ds.label + ': ' : (data.labels[item.index] ? data.labels[item.index] + ': ' : '')) + S + ' ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };
  var palette = ['#0f766e', '#f59e0b', '#0284c7', '#16a34a', '#7c3aed', '#e11d48', '#06b6d4', '#f97316', '#84cc16', '#64748b'];
  var ejeMoney = { yAxes: [{ ticks: { beginAtZero: true, callback: money }, gridLines: { color: '#eef2f7' } }], xAxes: [{ gridLines: { display: false } }] };
  function vacio(labels) { return labels.length ? labels : ['Sin datos']; }
  function vacioData(d) { return d.length ? d : [0]; }

  new Chart(document.getElementById('chartVentasDia'), { type: 'line', data: { labels: vacio(<?php echo json_encode($labelsVentasDia); ?>), datasets: [{ label: 'Ventas', data: vacioData(<?php echo json_encode($dataVentasDia); ?>), borderColor: '#0f766e', backgroundColor: 'rgba(15,118,110,0.12)', fill: true, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#0f766e', lineTension: 0.3 }] }, options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: ejeMoney } });
  new Chart(document.getElementById('chartMedio'), { type: 'doughnut', data: { labels: vacio(<?php echo json_encode($labelsMedio); ?>), datasets: [{ data: <?php echo count($dataMedio) ? json_encode($dataMedio) : '[1]'; ?>, backgroundColor: palette, borderWidth: 2 }] }, options: { responsive: true, maintainAspectRatio: false, cutoutPercentage: 62, legend: { position: 'bottom' } } });
  new Chart(document.getElementById('chartComparativo'), { type: 'bar', data: { labels: <?php echo json_encode($labelsMes); ?>, datasets: [{ label: 'Compras', data: <?php echo json_encode($dataComprasMes); ?>, backgroundColor: 'rgba(2,132,199,0.75)', borderRadius: 6 }, { label: 'Ventas', data: <?php echo json_encode($dataVentasMes); ?>, backgroundColor: 'rgba(22,163,74,0.8)' }] }, options: { responsive: true, maintainAspectRatio: false, legend: { position: 'top' }, scales: ejeMoney } });
  new Chart(document.getElementById('chartHora'), { type: 'bar', data: { labels: <?php echo json_encode($labelsHora); ?>, datasets: [{ label: 'Ventas', data: <?php echo json_encode(array_values($horas)); ?>, backgroundColor: 'rgba(245,158,11,0.8)' }] }, options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: { yAxes: ejeMoney.yAxes, xAxes: [{ gridLines: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12 } }] } } });
  new Chart(document.getElementById('chartTop'), { type: 'horizontalBar', data: { labels: vacio(<?php echo json_encode($labelsTop); ?>), datasets: [{ label: 'Vendido', data: vacioData(<?php echo json_encode($dataTop); ?>), backgroundColor: 'rgba(15,118,110,0.8)' }] }, options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: { xAxes: [{ ticks: { beginAtZero: true, callback: money }, gridLines: { color: '#eef2f7' } }], yAxes: [{ gridLines: { display: false } }] } } });
  new Chart(document.getElementById('chartCategoria'), { type: 'doughnut', data: { labels: vacio(<?php echo json_encode($labelsCat); ?>), datasets: [{ data: <?php echo count($dataCat) ? json_encode($dataCat) : '[1]'; ?>, backgroundColor: palette, borderWidth: 2 }] }, options: { responsive: true, maintainAspectRatio: false, cutoutPercentage: 55, legend: { position: 'bottom' } } });
  new Chart(document.getElementById('chartVendedor'), { type: 'bar', data: { labels: vacio(<?php echo json_encode($labelsVend); ?>), datasets: [{ label: 'Ventas', data: vacioData(<?php echo json_encode($dataVend); ?>), backgroundColor: 'rgba(124,58,237,0.8)' }] }, options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: ejeMoney } });
})();
</script>
<?php } ?>
