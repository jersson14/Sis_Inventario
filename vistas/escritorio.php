<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
} else {

require 'header.php';

if ($_SESSION['escritorio'] == 1) {
  require_once "../modelos/Consultas.php";
  $consulta = new Consultas();

  $hoy = date("Y-m-d");
  $inicioDefault = date("Y-m-01");

  $fechaInicio = isset($_GET["fecha_inicio"]) ? trim($_GET["fecha_inicio"]) : $inicioDefault;
  $fechaFin = isset($_GET["fecha_fin"]) ? trim($_GET["fecha_fin"]) : $hoy;

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
    $fechaInicio = $inicioDefault;
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
    $fechaFin = $hoy;
  }
  if (strtotime($fechaInicio) > strtotime($fechaFin)) {
    $tmpFecha = $fechaInicio;
    $fechaInicio = $fechaFin;
    $fechaFin = $tmpFecha;
  }

  $rangoTexto = date("d/m/Y", strtotime($fechaInicio)) . " - " . date("d/m/Y", strtotime($fechaFin));

  $rsptac = $consulta->totalcomprarango($fechaInicio, $fechaFin);
  $regc = $rsptac->fetch_object();
  $totalc = $regc ? (float)$regc->total_compra : 0;

  $rsptav = $consulta->totalventarango($fechaInicio, $fechaFin);
  $regv = $rsptav->fetch_object();
  $totalv = $regv ? (float)$regv->total_venta : 0;
  $totalcm = $totalc;
  $totalvm = $totalv;
  $codigoMoneda = function_exists('obtenerMonedaEmpresaCodigo') ? obtenerMonedaEmpresaCodigo() : 'PEN';

  $rskpi = $consulta->kpisgenerales();
  $kpi = $rskpi->fetch_object();
  $articulosActivos = $kpi ? (int)$kpi->articulos_activos : 0;
  $categoriasActivas = $kpi ? (int)$kpi->categorias_activas : 0;
  $clientes = $kpi ? (int)$kpi->clientes : 0;
  $proveedores = $kpi ? (int)$kpi->proveedores : 0;
  $stockTotal = $kpi ? (float)$kpi->stock_total : 0;

  $diasPeriodo = (int)floor((strtotime($fechaFin) - strtotime($fechaInicio)) / 86400) + 1;
  if ($diasPeriodo <= 0) {
    $diasPeriodo = 1;
  }
  $promedioVentas = $totalv / $diasPeriodo;
  $promedioCompras = $totalc / $diasPeriodo;

  $labelsCompras10 = array();
  $dataCompras10 = array();
  $compras10 = $consulta->comprasdiariasrango($fechaInicio, $fechaFin);
  while ($reg = $compras10->fetch_object()) {
    $labelsCompras10[] = date("d/m", strtotime($reg->fecha));
    $dataCompras10[] = round((float)$reg->total, 2);
  }

  $labelsVentas12 = array();
  $dataVentas12 = array();
  $ventas12 = $consulta->ventasmensualesrango($fechaInicio, $fechaFin);
  while ($reg = $ventas12->fetch_object()) {
    $labelsVentas12[] = $reg->fecha;
    $dataVentas12[] = round((float)$reg->total, 2);
  }

  $labelsComparativo = array();
  $compras6Map = array();
  $ventas6Map = array();

  $compras6 = $consulta->comprasmensualesrango($fechaInicio, $fechaFin);
  while ($reg = $compras6->fetch_object()) {
    $periodo = isset($reg->periodo) ? $reg->periodo : '';
    if ($periodo !== '') {
      $compras6Map[$periodo] = round((float)$reg->total, 2);
    }
  }

  $ventas6 = $consulta->ventasmensualesrango($fechaInicio, $fechaFin);
  while ($reg = $ventas6->fetch_object()) {
    $periodo = isset($reg->periodo) ? $reg->periodo : '';
    if ($periodo !== '') {
      $ventas6Map[$periodo] = round((float)$reg->total, 2);
    }
  }

  $periodosComparativo = array();
  $cursorMes = strtotime(date("Y-m-01", strtotime($fechaInicio)));
  $finMes = strtotime(date("Y-m-01", strtotime($fechaFin)));
  while ($cursorMes <= $finMes) {
    $periodosComparativo[] = date("Y-m", $cursorMes);
    $cursorMes = strtotime("+1 month", $cursorMes);
  }

  if (count($periodosComparativo) === 0) {
    $periodosComparativo[] = date("Y-m", strtotime($fechaInicio));
  }

  $dataCompras6 = array();
  $dataVentas6 = array();
  foreach ($periodosComparativo as $periodo) {
    $labelsComparativo[] = date("M Y", strtotime($periodo . "-01"));
    $dataCompras6[] = isset($compras6Map[$periodo]) ? $compras6Map[$periodo] : 0;
    $dataVentas6[] = isset($ventas6Map[$periodo]) ? $ventas6Map[$periodo] : 0;
  }

  $labelsTop = array();
  $dataTop = array();
  $topProductos = $consulta->topproductosvendidosrango($fechaInicio, $fechaFin, 7);
  while ($reg = $topProductos->fetch_object()) {
    $labelsTop[] = $reg->producto;
    $dataTop[] = round((float)$reg->total, 2);
  }

  $labelsCategoria = array();
  $dataCategoria = array();
  $ventasCategoria = $consulta->ventasporcategoriarango($fechaInicio, $fechaFin, 8);
  while ($reg = $ventasCategoria->fetch_object()) {
    $labelsCategoria[] = $reg->categoria;
    $dataCategoria[] = round((float)$reg->total, 2);
  }

  $movimientos = array();
  $rsmov = $consulta->ultimomovimientosrango($fechaInicio, $fechaFin, 10);
  while ($reg = $rsmov->fetch_object()) {
    $movimientos[] = array(
      "tipo" => $reg->tipo,
      "fecha" => date("d/m/Y H:i", strtotime($reg->fecha)),
      "documento" => $reg->documento,
      "persona" => $reg->persona,
      "total" => (float)$reg->total
    );
  }
?>
<div class="content-wrapper">
  <section class="content dashboard-wrap">
    <div class="dashboard-head">
      <h1>Panel Ejecutivo</h1>
      <p>Resumen interactivo de compras, ventas e inventario.</p>
    </div>

    <div class="box dashboard-box dashboard-filter-box">
      <div class="box-body">
        <form method="get" action="escritorio.php" class="row dashboard-filter-form">
          <div class="col-md-3 col-sm-6 col-xs-12">
            <label>Desde</label>
            <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($fechaInicio); ?>" max="<?php echo htmlspecialchars($fechaFin); ?>">
          </div>
          <div class="col-md-3 col-sm-6 col-xs-12">
            <label>Hasta</label>
            <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($fechaFin); ?>" min="<?php echo htmlspecialchars($fechaInicio); ?>">
          </div>
          <div class="col-md-6 col-sm-12 col-xs-12 dashboard-filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Aplicar filtros</button>
            <a href="escritorio.php" class="btn btn-default"><i class="fa fa-refresh"></i> Limpiar</a>
            <span class="text-muted" style="margin-left:12px;">Rango activo: <strong><?php echo htmlspecialchars($rangoTexto); ?></strong></span>
          </div>
        </form>
      </div>
    </div>

    <div class="row dashboard-kpis">
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <a href="venta.php" class="kpi-card kpi-sales">
          <div class="kpi-icon"><i class="fa fa-line-chart"></i></div>
          <div class="kpi-meta">
            <span>Ventas del Periodo</span>
            <strong><?php echo formatearMoneda($totalv, $codigoMoneda); ?></strong>
          </div>
        </a>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <a href="ingreso.php" class="kpi-card kpi-buy">
          <div class="kpi-icon"><i class="fa fa-shopping-basket"></i></div>
          <div class="kpi-meta">
            <span>Compras del Periodo</span>
            <strong><?php echo formatearMoneda($totalc, $codigoMoneda); ?></strong>
          </div>
        </a>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <div class="kpi-card kpi-stock">
          <div class="kpi-icon"><i class="fa fa-cubes"></i></div>
          <div class="kpi-meta">
            <span>Stock Total</span>
            <strong><?php echo number_format($stockTotal, 3); ?></strong>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12">
        <div class="kpi-card kpi-month">
          <div class="kpi-icon"><i class="fa fa-calendar"></i></div>
          <div class="kpi-meta">
            <span>Balance del Periodo</span>
            <strong><?php echo formatearMoneda($totalvm - $totalcm, $codigoMoneda); ?></strong>
          </div>
        </div>
      </div>
    </div>

    <div class="row dashboard-kpis secondary">
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="mini-kpi">
          <label>Promedio Ventas / Dia</label>
          <strong><?php echo formatearMoneda($promedioVentas, $codigoMoneda); ?></strong>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="mini-kpi">
          <label>Promedio Compras / Dia</label>
          <strong><?php echo formatearMoneda($promedioCompras, $codigoMoneda); ?></strong>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="mini-kpi">
          <label>Clientes</label>
          <strong><?php echo $clientes; ?></strong>
        </div>
      </div>
      <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="mini-kpi">
          <label>Proveedores / Categorias / Articulos</label>
          <strong><?php echo $proveedores; ?> / <?php echo $categoriasActivas; ?> / <?php echo $articulosActivos; ?></strong>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-8 col-md-8 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">Comparativo Compras vs Ventas (Rango Seleccionado)</h3>
          </div>
          <div class="box-body">
            <canvas id="chartComparativo" height="120"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">Ventas por Categoria (Rango Seleccionado)</h3>
          </div>
          <div class="box-body">
            <canvas id="chartCategoria" height="170"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">Compras por Dia (Rango Seleccionado)</h3>
          </div>
          <div class="box-body">
            <canvas id="chartCompras10" height="150"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">Ventas por Mes (Rango Seleccionado)</h3>
          </div>
          <div class="box-body">
            <canvas id="chartVentas12" height="150"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">Top Productos Vendidos</h3>
          </div>
          <div class="box-body">
            <canvas id="chartTopProductos" height="180"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
        <div class="box dashboard-box">
          <div class="box-header with-border">
            <h3 class="box-title">Ultimos Movimientos</h3>
          </div>
          <div class="box-body table-responsive">
            <table class="table table-striped table-condensed">
              <thead>
                <tr>
                  <th>Tipo</th>
                  <th>Fecha</th>
                  <th>Documento</th>
                  <th>Persona</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($movimientos) === 0) { ?>
                <tr>
                  <td colspan="5">Sin movimientos recientes.</td>
                </tr>
                <?php } else { foreach ($movimientos as $mov) { ?>
                <tr>
                  <td>
                    <span class="label <?php echo $mov["tipo"] === "Venta" ? "bg-green" : "bg-aqua"; ?>">
                      <?php echo $mov["tipo"]; ?>
                    </span>
                  </td>
                  <td><?php echo $mov["fecha"]; ?></td>
                  <td><?php echo $mov["documento"]; ?></td>
                  <td><?php echo $mov["persona"]; ?></td>
                  <td><?php echo formatearMoneda($mov["total"], $codigoMoneda); ?></td>
                </tr>
                <?php } } ?>
              </tbody>
            </table>
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
?>
<script src="../public/js/Chart.bundle.min.js"></script>
<script>
(function () {
  var labelsCompras10 = <?php echo json_encode($labelsCompras10); ?>;
  var dataCompras10 = <?php echo json_encode($dataCompras10); ?>;
  var labelsVentas12 = <?php echo json_encode($labelsVentas12); ?>;
  var dataVentas12 = <?php echo json_encode($dataVentas12); ?>;
  var labelsComparativo = <?php echo json_encode($labelsComparativo); ?>;
  var dataCompras6 = <?php echo json_encode($dataCompras6); ?>;
  var dataVentas6 = <?php echo json_encode($dataVentas6); ?>;
  var labelsTop = <?php echo json_encode($labelsTop); ?>;
  var dataTop = <?php echo json_encode($dataTop); ?>;
  var labelsCategoria = <?php echo json_encode($labelsCategoria); ?>;
  var dataCategoria = <?php echo json_encode($dataCategoria); ?>;

  Chart.defaults.global.animation.duration = 350;
  Chart.defaults.global.defaultFontFamily = '"Trebuchet MS", "Verdana", "Segoe UI", sans-serif';
  Chart.defaults.global.defaultFontColor = '#334155';

  var currencySymbol = window.appCurrencySymbol || <?php echo json_encode(obtenerSimboloMoneda($codigoMoneda)); ?>;
  var moneyTick = function(value){
    return currencySymbol + ' ' + Number(value).toLocaleString('es-PE', {minimumFractionDigits: 0, maximumFractionDigits: 0});
  };

  new Chart(document.getElementById('chartComparativo').getContext('2d'), {
    type: 'line',
    data: {
      labels: labelsComparativo,
      datasets: [{
        label: 'Compras',
        data: dataCompras6,
        borderColor: '#0284c7',
        backgroundColor: 'rgba(2,132,199,0.15)',
        fill: true,
        borderWidth: 2,
        pointRadius: 3
      },{
        label: 'Ventas',
        data: dataVentas6,
        borderColor: '#16a34a',
        backgroundColor: 'rgba(22,163,74,0.15)',
        fill: true,
        borderWidth: 2,
        pointRadius: 3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { position: 'top' },
      scales: {
        yAxes: [{ ticks: { beginAtZero: true, callback: moneyTick } }]
      }
    }
  });

  new Chart(document.getElementById('chartCategoria').getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: labelsCategoria.length ? labelsCategoria : ['Sin datos'],
      datasets: [{
        data: dataCategoria.length ? dataCategoria : [1],
        backgroundColor: ['#0ea5e9','#14b8a6','#f59e0b','#16a34a','#8b5cf6','#f97316','#06b6d4','#e11d48']
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { position: 'bottom' }
    }
  });

  new Chart(document.getElementById('chartCompras10').getContext('2d'), {
    type: 'bar',
    data: {
      labels: labelsCompras10,
      datasets: [{
        label: 'Compras (' + currencySymbol + ')',
        data: dataCompras10,
        backgroundColor: 'rgba(2,132,199,0.22)',
        borderColor: '#0284c7',
        borderWidth: 1.5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { display: false },
      scales: {
        yAxes: [{ ticks: { beginAtZero: true, callback: moneyTick } }]
      }
    }
  });

  new Chart(document.getElementById('chartVentas12').getContext('2d'), {
    type: 'bar',
    data: {
      labels: labelsVentas12,
      datasets: [{
        label: 'Ventas (' + currencySymbol + ')',
        data: dataVentas12,
        backgroundColor: 'rgba(22,163,74,0.22)',
        borderColor: '#16a34a',
        borderWidth: 1.5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { display: false },
      scales: {
        yAxes: [{ ticks: { beginAtZero: true, callback: moneyTick } }]
      }
    }
  });

  new Chart(document.getElementById('chartTopProductos').getContext('2d'), {
    type: 'horizontalBar',
    data: {
      labels: labelsTop.length ? labelsTop : ['Sin datos'],
      datasets: [{
        label: 'Monto vendido (' + currencySymbol + ')',
        data: dataTop.length ? dataTop : [0],
        backgroundColor: 'rgba(245,158,11,0.26)',
        borderColor: '#d97706',
        borderWidth: 1.5
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      legend: { display: false },
      scales: {
        xAxes: [{ ticks: { beginAtZero: true, callback: moneyTick } }]
      }
    }
  });
})();
</script>
<?php
}

ob_end_flush();
?>
