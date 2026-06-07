<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
} else {

require 'header.php';

if ($_SESSION['consultac']==1 || $_SESSION['consultav']==1) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box">
          <div class="box-header with-border">
            <h1 class="box-title">Centro de Reportes Comerciales</h1>
          </div>

          <div class="panel-body table-responsive reportes-panel">
            <div class="row">
              <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <label>Fecha inicio</label>
                <input type="date" class="form-control" id="fecha_inicio_reportes" value="<?php echo date("Y-m-01"); ?>">
              </div>
              <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <label>Fecha fin</label>
                <input type="date" class="form-control" id="fecha_fin_reportes" value="<?php echo date("Y-m-d"); ?>">
              </div>
              <div class="form-group col-lg-2 col-md-2 col-sm-4 col-xs-12">
                <label>Top productos</label>
                <select id="limite_top" class="form-control">
                  <option value="10">Top 10</option>
                  <option value="20" selected>Top 20</option>
                  <option value="30">Top 30</option>
                  <option value="50">Top 50</option>
                </select>
              </div>
              <div class="form-group col-lg-2 col-md-2 col-sm-4 col-xs-12">
                <label>Clientes/Proveedores</label>
                <select id="tipo_persona_reporte" class="form-control">
                  <option value="TODOS" selected>Todos</option>
                  <option value="CLIENTE">Solo clientes</option>
                  <option value="PROVEEDOR">Solo proveedores</option>
                </select>
              </div>
              <div class="form-group col-lg-2 col-md-2 col-sm-4 col-xs-12">
                <label>&nbsp;</label>
                <button type="button" id="btnActualizarReportes" class="btn btn-primary form-control">
                  <i class="fa fa-refresh"></i> Actualizar
                </button>
              </div>
            </div>

            <ul class="nav nav-tabs" role="tablist" style="margin-top:8px;">
              <li class="active"><a href="#tabUtilidad" role="tab" data-toggle="tab">1) Utilidad por período</a></li>
              <li><a href="#tabTop" role="tab" data-toggle="tab">2) Top productos</a></li>
              <li><a href="#tabStock" role="tab" data-toggle="tab">3) Stock crítico</a></li>
              <li><a href="#tabKardex" role="tab" data-toggle="tab">4) Kardex valorizado</a></li>
              <li><a href="#tabPersonas" role="tab" data-toggle="tab">5) Clientes / Proveedores</a></li>
            </ul>

            <div class="tab-content" style="padding-top:12px;">
              <div class="tab-pane active" id="tabUtilidad">
                <table id="tblutilidad" class="table table-striped table-bordered table-condensed table-hover" style="width:100%;">
                  <thead>
                    <th>Código</th>
                    <th>Artículo</th>
                    <th>Categoría</th>
                    <th>Cant. Vendida</th>
                    <th>Venta Total</th>
                    <th>Costo Est.</th>
                    <th>Utilidad</th>
                    <th>Margen</th>
                  </thead>
                  <tbody></tbody>
                  <tfoot>
                    <th>Código</th>
                    <th>Artículo</th>
                    <th>Categoría</th>
                    <th>Cant. Vendida</th>
                    <th>Venta Total</th>
                    <th>Costo Est.</th>
                    <th>Utilidad</th>
                    <th>Margen</th>
                  </tfoot>
                </table>
              </div>

              <div class="tab-pane" id="tabTop">
                <h4><i class="fa fa-arrow-up text-green"></i> Más vendidos</h4>
                <table id="tbltopmas" class="table table-striped table-bordered table-condensed table-hover" style="width:100%;">
                  <thead>
                    <th>Rank</th>
                    <th>Código</th>
                    <th>Artículo</th>
                    <th>Categoría</th>
                    <th>Cantidad</th>
                    <th>Total Vendido</th>
                  </thead>
                  <tbody></tbody>
                  <tfoot>
                    <th>Rank</th>
                    <th>Código</th>
                    <th>Artículo</th>
                    <th>Categoría</th>
                    <th>Cantidad</th>
                    <th>Total Vendido</th>
                  </tfoot>
                </table>
                <hr>
                <h4><i class="fa fa-arrow-down text-red"></i> Menos vendidos</h4>
                <table id="tbltopmenos" class="table table-striped table-bordered table-condensed table-hover" style="width:100%;">
                  <thead>
                    <th>Rank</th>
                    <th>Código</th>
                    <th>Artículo</th>
                    <th>Categoría</th>
                    <th>Cantidad</th>
                    <th>Total Vendido</th>
                  </thead>
                  <tbody></tbody>
                  <tfoot>
                    <th>Rank</th>
                    <th>Código</th>
                    <th>Artículo</th>
                    <th>Categoría</th>
                    <th>Cantidad</th>
                    <th>Total Vendido</th>
                  </tfoot>
                </table>
              </div>

              <div class="tab-pane" id="tabStock">
                <table id="tblstock" class="table table-striped table-bordered table-condensed table-hover" style="width:100%;">
                  <thead>
                    <th>Código</th>
                    <th>Artículo</th>
                    <th>Categoría</th>
                    <th>Stock</th>
                    <th>Mínimo</th>
                    <th>Alerta</th>
                    <th>Últ. Movimiento</th>
                    <th>Días sin mov.</th>
                  </thead>
                  <tbody></tbody>
                  <tfoot>
                    <th>Código</th>
                    <th>Artículo</th>
                    <th>Categoría</th>
                    <th>Stock</th>
                    <th>Mínimo</th>
                    <th>Alerta</th>
                    <th>Últ. Movimiento</th>
                    <th>Días sin mov.</th>
                  </tfoot>
                </table>
              </div>

              <div class="tab-pane" id="tabKardex">
                <table id="tblkardex" class="table table-striped table-bordered table-condensed table-hover" style="width:100%;">
                  <thead>
                    <th>Código</th>
                    <th>Artículo</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Saldo</th>
                    <th>Costo Prom.</th>
                    <th>Valor Stock</th>
                  </thead>
                  <tbody></tbody>
                  <tfoot>
                    <th>Código</th>
                    <th>Artículo</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Saldo</th>
                    <th>Costo Prom.</th>
                    <th>Valor Stock</th>
                  </tfoot>
                </table>
              </div>

              <div class="tab-pane" id="tabPersonas">
                <table id="tblpersonas" class="table table-striped table-bordered table-condensed table-hover" style="width:100%;">
                  <thead>
                    <th>Tipo</th>
                    <th>Nombre</th>
                    <th>Documento</th>
                    <th>Teléfono</th>
                    <th>Operaciones</th>
                    <th>Total</th>
                    <th>Últ. Movimiento</th>
                  </thead>
                  <tbody></tbody>
                  <tfoot>
                    <th>Tipo</th>
                    <th>Nombre</th>
                    <th>Documento</th>
                    <th>Teléfono</th>
                    <th>Operaciones</th>
                    <th>Total</th>
                    <th>Últ. Movimiento</th>
                  </tfoot>
                </table>
              </div>
            </div>
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
<script src="scripts/reportes.js?v=20260325c"></script>
<?php
}
ob_end_flush();
?>
