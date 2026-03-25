<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
}else{

require 'header.php';
if ($_SESSION['compras']==1 || $_SESSION['ventas']==1) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box">
          <div class="box-header with-border">
            <h1 class="box-title">Cuentas por Cobrar y Pagar</h1>
          </div>
          <div class="panel-body cuentas-panel">
            <ul class="nav nav-tabs" role="tablist">
              <li role="presentation" class="active"><a href="#cxctab" role="tab" data-toggle="tab">Cuentas por Cobrar</a></li>
              <li role="presentation"><a href="#cxptab" role="tab" data-toggle="tab">Cuentas por Pagar</a></li>
            </ul>

            <div class="tab-content" style="margin-top:16px;">
              <div role="tabpanel" class="tab-pane active" id="cxctab">
                <form id="formCobrar" class="row">
                  <div class="form-group col-md-3 col-sm-6 col-xs-12"><label>Cliente</label><select class="form-control selectpicker" data-live-search="true" id="cobrar_idcliente" name="idcliente" required></select></div>
                  <div class="form-group col-md-2 col-sm-6 col-xs-12"><label>Emision</label><input type="date" class="form-control" id="cobrar_emision" name="fecha_emision" required></div>
                  <div class="form-group col-md-2 col-sm-6 col-xs-12"><label>Vencimiento</label><input type="date" class="form-control" id="cobrar_venc" name="fecha_vencimiento" required></div>
                  <div class="form-group col-md-2 col-sm-6 col-xs-12"><label>Documento</label><input type="text" class="form-control" id="cobrar_doc" name="documento_ref"></div>
                  <div class="form-group col-md-2 col-sm-6 col-xs-12"><label>Monto</label><input type="number" step="0.01" min="0.01" class="form-control" id="cobrar_monto" name="monto_total" required></div>
                  <div class="form-group col-md-1 col-sm-6 col-xs-12"><label>&nbsp;</label><button class="btn btn-success form-control" type="submit"><i class="fa fa-save"></i></button></div>
                  <div class="form-group col-md-12 col-sm-12 col-xs-12"><input type="text" class="form-control" id="cobrar_obs" name="observacion" placeholder="Observacion"></div>
                </form>

                <div class="table-responsive">
                <table id="tblcobrar" class="table table-striped table-bordered table-condensed table-hover cuentas-table" style="width:100%;">
                  <thead><th>Opc.</th><th>Emision</th><th>Vence</th><th>Cliente</th><th>Documento</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Estado</th></thead>
                  <tbody></tbody>
                </table>
                </div>
              </div>

              <div role="tabpanel" class="tab-pane" id="cxptab">
                <form id="formPagar" class="row">
                  <div class="form-group col-md-3 col-sm-6 col-xs-12"><label>Proveedor</label><select class="form-control selectpicker" data-live-search="true" id="pagar_idproveedor" name="idproveedor" required></select></div>
                  <div class="form-group col-md-2 col-sm-6 col-xs-12"><label>Emision</label><input type="date" class="form-control" id="pagar_emision" name="fecha_emision" required></div>
                  <div class="form-group col-md-2 col-sm-6 col-xs-12"><label>Vencimiento</label><input type="date" class="form-control" id="pagar_venc" name="fecha_vencimiento" required></div>
                  <div class="form-group col-md-2 col-sm-6 col-xs-12"><label>Documento</label><input type="text" class="form-control" id="pagar_doc" name="documento_ref"></div>
                  <div class="form-group col-md-2 col-sm-6 col-xs-12"><label>Monto</label><input type="number" step="0.01" min="0.01" class="form-control" id="pagar_monto" name="monto_total" required></div>
                  <div class="form-group col-md-1 col-sm-6 col-xs-12"><label>&nbsp;</label><button class="btn btn-primary form-control" type="submit"><i class="fa fa-save"></i></button></div>
                  <div class="form-group col-md-12 col-sm-12 col-xs-12"><input type="text" class="form-control" id="pagar_obs" name="observacion" placeholder="Observacion"></div>
                </form>

                <div class="table-responsive">
                <table id="tblpagar" class="table table-striped table-bordered table-condensed table-hover cuentas-table" style="width:100%;">
                  <thead><th>Opc.</th><th>Emision</th><th>Vence</th><th>Proveedor</th><th>Documento</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Estado</th></thead>
                  <tbody></tbody>
                </table>
                </div>
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
<script src="scripts/cuentas.js?v=20260321b"></script>
<?php
}
ob_end_flush();
?>
