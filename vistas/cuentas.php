<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Cuentas por cobrar y pagar";
$iconoPagina = "fa-file-text";
require 'header.php';
if (usuarioTienePermiso('cuentas') || usuarioTienePermiso('ventas') || usuarioTienePermiso('compras')) {
  $puedeCobrar = usuarioTienePermiso('cuentas') || usuarioTienePermiso('ventas');
  $puedePagar = usuarioTienePermiso('cuentas') || usuarioTienePermiso('compras');
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Finanzas <i class="fa fa-chevron-right"></i> Cuentas</div>
        <h1><span class="page-icon"><i class="fa fa-file-text"></i></span> Cuentas por cobrar y por pagar</h1>
        <p>Créditos otorgados a clientes y deudas con proveedores. Las ventas y compras al crédito se registran aquí automáticamente.</p>
      </div>
    </div>

    <div class="alert-cards" id="cuentasResumen">
      <div class="alert-card is-success"><div class="alert-ico"><i class="fa fa-arrow-circle-down"></i></div><div><strong id="resCxcPend">—</strong><span>Por cobrar (pendiente)</span></div></div>
      <div class="alert-card is-danger"><div class="alert-ico"><i class="fa fa-exclamation-circle"></i></div><div><strong id="resCxcVenc">—</strong><span id="resCxcVencTxt">Por cobrar vencido</span></div></div>
      <div class="alert-card is-info"><div class="alert-ico"><i class="fa fa-arrow-circle-up"></i></div><div><strong id="resCxpPend">—</strong><span>Por pagar (pendiente)</span></div></div>
      <div class="alert-card is-warning"><div class="alert-ico"><i class="fa fa-clock-o"></i></div><div><strong id="resCxpVenc">—</strong><span id="resCxpVencTxt">Por pagar vencido</span></div></div>
    </div>

    <div class="box">
      <div class="box-body cuentas-panel">
        <ul class="nav nav-tabs" role="tablist">
          <?php if ($puedeCobrar) { ?><li role="presentation" class="active"><a href="#cxctab" role="tab" data-toggle="tab"><i class="fa fa-arrow-circle-down"></i> Por cobrar (clientes)</a></li><?php } ?>
          <?php if ($puedePagar) { ?><li role="presentation" class="<?php echo $puedeCobrar ? '' : 'active'; ?>"><a href="#cxptab" role="tab" data-toggle="tab"><i class="fa fa-arrow-circle-up"></i> Por pagar (proveedores)</a></li><?php } ?>
        </ul>

        <div class="tab-content">
          <?php if ($puedeCobrar) { ?>
          <div role="tabpanel" class="tab-pane active" id="cxctab">
            <div class="table-toolbar">
              <div class="form-group"><label>Estado</label>
                <select id="filtro_cobrar_estado" class="form-control input-sm"><option value="TODOS">Todas</option><option value="PENDIENTE" selected>Pendientes</option><option value="VENCIDA">Vencidas</option><option value="PAGADO">Pagadas</option><option value="ANULADO">Anuladas</option></select>
              </div>
              <div class="toolbar-actions">
                <button type="button" class="btn btn-primary btn-sm" id="btnNuevaCobrar" data-toggle="modal" data-target="#modalNuevaCuenta" data-tipo="cobrar"><i class="fa fa-plus"></i> Registrar cuenta por cobrar</button>
              </div>
            </div>
            <div class="table-responsive">
              <table id="tblcobrar" class="table table-striped table-bordered table-hover cuentas-table" style="width:100%">
                <thead><tr><th>Opciones</th><th>Emisión</th><th>Vence</th><th>Cliente</th><th>Documento</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Estado</th><th>Días</th><th>ID</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
          <?php } ?>
          <?php if ($puedePagar) { ?>
          <div role="tabpanel" class="tab-pane <?php echo $puedeCobrar ? '' : 'active'; ?>" id="cxptab">
            <div class="table-toolbar">
              <div class="form-group"><label>Estado</label>
                <select id="filtro_pagar_estado" class="form-control input-sm"><option value="TODOS">Todas</option><option value="PENDIENTE" selected>Pendientes</option><option value="VENCIDA">Vencidas</option><option value="PAGADO">Pagadas</option><option value="ANULADO">Anuladas</option></select>
              </div>
              <div class="toolbar-actions">
                <button type="button" class="btn btn-primary btn-sm" id="btnNuevaPagar" data-toggle="modal" data-target="#modalNuevaCuenta" data-tipo="pagar"><i class="fa fa-plus"></i> Registrar cuenta por pagar</button>
              </div>
            </div>
            <div class="table-responsive">
              <table id="tblpagar" class="table table-striped table-bordered table-hover cuentas-table" style="width:100%">
                <thead><tr><th>Opciones</th><th>Emisión</th><th>Vence</th><th>Proveedor</th><th>Documento</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Estado</th><th>Días</th><th>ID</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Modal nueva cuenta -->
<div class="modal fade" id="modalNuevaCuenta" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <form id="formNuevaCuenta" autocomplete="off">
      <input type="hidden" id="nc_tipo" value="cobrar">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="nc_titulo"><i class="fa fa-plus"></i> Registrar cuenta</h4></div>
      <div class="modal-body">
        <div class="form-group"><label id="nc_labelPersona">Cliente <span class="req">*</span></label>
          <select class="form-control selectpicker" data-live-search="true" data-width="100%" id="nc_idpersona" required></select>
        </div>
        <div class="row">
          <div class="form-group col-xs-6"><label>Emisión <span class="req">*</span></label><input type="date" class="form-control" id="nc_emision" required></div>
          <div class="form-group col-xs-6"><label>Vencimiento <span class="req">*</span></label><input type="date" class="form-control" id="nc_venc" required></div>
        </div>
        <div class="row">
          <div class="form-group col-xs-6"><label>Documento de referencia</label><input type="text" class="form-control" id="nc_doc" maxlength="40" placeholder="Ej. F001-000123"></div>
          <div class="form-group col-xs-6"><label>Monto <span class="req">*</span></label><input type="number" step="0.01" min="0.01" class="form-control" id="nc_monto" required></div>
        </div>
        <div class="form-group"><label>Observación</label><input type="text" class="form-control" id="nc_obs" maxlength="200"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="btnGuardarCuenta"><i class="fa fa-save"></i> Guardar</button></div>
    </form>
  </div></div>
</div>

<!-- Modal abonar -->
<div class="modal fade" id="modalAbono" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
    <form id="formAbono" autocomplete="off">
      <input type="hidden" id="ab_tipo"><input type="hidden" id="ab_idcuenta">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="ab_titulo"><i class="fa fa-money"></i> Registrar abono</h4></div>
      <div class="modal-body">
        <div class="alert alert-info" style="font-size:13px">Saldo pendiente: <strong id="ab_saldo">—</strong></div>
        <div class="form-group"><label>Monto <span class="req">*</span></label><input type="number" step="0.01" min="0.01" class="form-control input-lg" id="ab_monto" required></div>
        <div class="form-group"><label>Medio de pago</label><select class="form-control" id="ab_medio"><option value="EFECTIVO">Efectivo</option><option value="TARJETA">Tarjeta</option><option value="TRANSFERENCIA">Transferencia</option><option value="YAPE">Yape</option><option value="PLIN">Plin</option><option value="OTRO">Otro</option></select></div>
        <div class="form-group"><label>Observación</label><input type="text" class="form-control" id="ab_obs" maxlength="150"></div>
        <button type="button" class="btn btn-default btn-sm w-100" id="btnAbonoTotal"><i class="fa fa-check-square-o"></i> Pagar el saldo completo</button>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success" id="btnConfirmarAbono"><i class="fa fa-check"></i> Registrar</button></div>
    </form>
  </div></div>
</div>

<!-- Modal historial de pagos -->
<div class="modal fade" id="modalPagos" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-history"></i> Historial de pagos</h4></div>
    <div class="modal-body" id="pagosBody"></div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button></div>
  </div></div>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/cuentas.js?v=<?php echo e(APP_VERSION); ?>"></script>
