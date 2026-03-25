<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
}else{

require 'header.php';
if ($_SESSION['almacen']==1 || $_SESSION['consultac']==1 || $_SESSION['consultav']==1) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box">
          <div class="box-header with-border">
            <h1 class="box-title">Centro Inteligente</h1>
          </div>
          <div class="panel-body procenter-panel">
            <ul class="nav nav-tabs" role="tablist">
              <li role="presentation" class="active"><a href="#tabKardex" aria-controls="tabKardex" role="tab" data-toggle="tab">Kardex</a></li>
              <li role="presentation"><a href="#tabAlertas" aria-controls="tabAlertas" role="tab" data-toggle="tab">Alertas</a></li>
              <li role="presentation"><a href="#tabUtilidad" aria-controls="tabUtilidad" role="tab" data-toggle="tab">Utilidad Real</a></li>
              <li role="presentation"><a href="#tabSugerencias" aria-controls="tabSugerencias" role="tab" data-toggle="tab">Compras Sugeridas</a></li>
            </ul>

            <div class="tab-content" style="margin-top:16px;">
              <div role="tabpanel" class="tab-pane active" id="tabKardex">
                <div class="row">
                  <div class="form-group col-md-5">
                    <label>Articulo</label>
                    <select id="kardex_articulo" class="form-control selectpicker" data-live-search="true"></select>
                  </div>
                  <div class="form-group col-md-2">
                    <label>Desde</label>
                    <input type="date" class="form-control" id="kardex_desde">
                  </div>
                  <div class="form-group col-md-2">
                    <label>Hasta</label>
                    <input type="date" class="form-control" id="kardex_hasta">
                  </div>
                  <div class="form-group col-md-3">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary form-control" id="btnKardexBuscar"><i class="fa fa-search"></i> Generar Kardex</button>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-12">
                    <div class="well well-sm" id="kardexResumen">Selecciona un articulo para ver su historial.</div>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-striped table-bordered procenter-table" id="tblkardex" style="width:100%;">
                    <thead>
                      <th>Fecha</th>
                      <th>Tipo</th>
                      <th>Documento</th>
                      <th>Tercero</th>
                      <th>Entrada</th>
                      <th>Salida</th>
                      <th>Saldo</th>
                      <th>Costo</th>
                      <th>Precio Ref.</th>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>

              <div role="tabpanel" class="tab-pane" id="tabAlertas">
                <div class="row">
                  <div class="col-md-6">
                    <h4>Stock minimo</h4>
                    <table class="table table-striped table-bordered procenter-table" id="tblalertstock" style="width:100%;">
                      <thead><th>Codigo</th><th>Articulo</th><th>Stock</th><th>Minimo</th><th>Faltante</th></thead>
                      <tbody></tbody>
                    </table>
                  </div>
                  <div class="col-md-6">
                    <h4>Sin movimiento</h4>
                    <div class="form-inline" style="margin-bottom:8px;">
                      <label>Dias:&nbsp;</label>
                      <input type="number" min="1" value="30" id="alerta_dias" class="form-control" style="width:100px;">
                      <button class="btn btn-default" id="btnAlertaSinMov"><i class="fa fa-refresh"></i> Actualizar</button>
                    </div>
                    <table class="table table-striped table-bordered procenter-table" id="tblsinmov" style="width:100%;">
                      <thead><th>Codigo</th><th>Articulo</th><th>Stock</th><th>Ult. movimiento</th></thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>
                <hr>
                <h4>Top vendidos</h4>
                <div class="row">
                  <div class="form-group col-md-2"><input type="date" id="top_desde" class="form-control"></div>
                  <div class="form-group col-md-2"><input type="date" id="top_hasta" class="form-control"></div>
                  <div class="form-group col-md-2"><button class="btn btn-default" id="btnTopVendidos"><i class="fa fa-search"></i> Filtrar</button></div>
                </div>
                <table class="table table-striped table-bordered procenter-table" id="tbltopvend" style="width:100%;">
                  <thead><th>Codigo</th><th>Articulo</th><th>Cantidad</th><th>Total</th></thead>
                  <tbody></tbody>
                </table>
              </div>

              <div role="tabpanel" class="tab-pane" id="tabUtilidad">
                <div class="row">
                  <div class="form-group col-md-2">
                    <label>Desde</label>
                    <input type="date" class="form-control" id="util_desde">
                  </div>
                  <div class="form-group col-md-2">
                    <label>Hasta</label>
                    <input type="date" class="form-control" id="util_hasta">
                  </div>
                  <div class="form-group col-md-3">
                    <label>Agrupar por</label>
                    <select class="form-control" id="util_agrupar">
                      <option value="producto">Producto</option>
                      <option value="categoria">Categoria</option>
                      <option value="vendedor">Vendedor</option>
                    </select>
                  </div>
                  <div class="form-group col-md-3">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary form-control" id="btnUtilidad"><i class="fa fa-bar-chart"></i> Calcular utilidad</button>
                  </div>
                </div>
                <table class="table table-striped table-bordered procenter-table" id="tblutilidad" style="width:100%;">
                  <thead><th>Grupo</th><th>Detalle</th><th>Cantidad</th><th>Venta</th><th>Costo</th><th>Utilidad</th></thead>
                  <tbody></tbody>
                </table>
              </div>

              <div role="tabpanel" class="tab-pane" id="tabSugerencias">
                <div class="row">
                  <div class="form-group col-md-3">
                    <label>Dias de analisis</label>
                    <input type="number" class="form-control" id="sug_dias_analisis" value="30" min="1">
                  </div>
                  <div class="form-group col-md-3">
                    <label>Dias de cobertura</label>
                    <input type="number" class="form-control" id="sug_dias_cobertura" value="15" min="1">
                  </div>
                  <div class="form-group col-md-3">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary form-control" id="btnSugerencias"><i class="fa fa-lightbulb-o"></i> Generar sugerencias</button>
                  </div>
                </div>
                <table class="table table-striped table-bordered procenter-table" id="tblsugerencias" style="width:100%;">
                  <thead><th>Codigo</th><th>Articulo</th><th>Stock</th><th>Minimo</th><th>Vendido</th><th>Prom/dia</th><th>Objetivo</th><th>Sugerido</th></thead>
                  <tbody></tbody>
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
}else{
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/procenter.js?v=20260321b"></script>
<?php
}
ob_end_flush();
?>
