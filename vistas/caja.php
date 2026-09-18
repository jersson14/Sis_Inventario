<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Caja diaria";
$iconoPagina = "fa-money";
require 'header.php';
if (usuarioTienePermiso('caja') || usuarioTienePermiso('ventas')) {
  $esAdmin = usuarioTienePermiso('acceso');
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Finanzas <i class="fa fa-chevron-right"></i> Caja</div>
        <h1><span class="page-icon"><i class="fa fa-money"></i></span> Caja diaria</h1>
        <p>Abre tu caja al iniciar el día: las ventas al contado y los cobros se registran solos. Ciérrala con arqueo al terminar.</p>
      </div>
      <div class="page-actions" id="cajaAcciones">
        <button class="btn btn-success" id="btnAbrirCaja" data-toggle="modal" data-target="#modalAbrirCaja"><i class="fa fa-unlock"></i> Abrir caja</button>
        <button class="btn btn-default" id="btnMovimiento" data-toggle="modal" data-target="#modalMovimiento"><i class="fa fa-exchange"></i> Movimiento manual</button>
        <button class="btn btn-danger" id="btnCerrarCaja" data-toggle="modal" data-target="#modalCerrarCaja"><i class="fa fa-lock"></i> Cerrar caja</button>
      </div>
    </div>

    <div id="cajaEstadoCerrada" class="box" style="display:none">
      <div class="box-body">
        <div class="empty-state">
          <i class="fa fa-lock"></i>
          <strong>No tienes una caja abierta</strong>
          Abre una caja con el monto inicial en efectivo para empezar a registrar ventas y movimientos del día.
          <div class="mt-10"><button class="btn btn-success btn-lg" data-toggle="modal" data-target="#modalAbrirCaja"><i class="fa fa-unlock"></i> Abrir caja ahora</button></div>
        </div>
      </div>
    </div>

    <div id="cajaEstadoAbierta" style="display:none">
      <div class="row dashboard-kpis">
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12"><div class="kpi-card kpi-dark"><div class="kpi-icon"><i class="fa fa-unlock"></i></div><div class="kpi-meta"><span>Caja abierta</span><strong id="kpiApertura">—</strong><small id="kpiAperturaFecha"></small></div></div></div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12"><div class="kpi-card kpi-sales"><div class="kpi-icon"><i class="fa fa-arrow-down"></i></div><div class="kpi-meta"><span>Ingresos</span><strong id="kpiIngresos">—</strong><small id="kpiMovs"></small></div></div></div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12"><div class="kpi-card kpi-month"><div class="kpi-icon"><i class="fa fa-arrow-up"></i></div><div class="kpi-meta"><span>Egresos</span><strong id="kpiEgresos">—</strong></div></div></div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-xs-12"><div class="kpi-card kpi-profit"><div class="kpi-icon"><i class="fa fa-calculator"></i></div><div class="kpi-meta"><span>Efectivo esperado en caja</span><strong id="kpiSistema">—</strong><small id="kpiOtrosMedios">Apertura + efectivo que entró − efectivo que salió</small></div></div></div>
      </div>
      <div class="row">
        <div class="col-lg-4 col-md-5 col-xs-12">
          <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-credit-card"></i> Por medio de pago</h3></div>
            <div class="box-body" id="cajaMedios"><div class="text-soft">Sin movimientos aún.</div></div>
          </div>
        </div>
        <div class="col-lg-8 col-md-7 col-xs-12">
          <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-list"></i> Movimientos de la caja abierta</h3></div>
            <div class="box-body table-responsive">
              <table id="tblmovcaja" class="table table-striped table-bordered table-hover" style="width:100%">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Monto</th><th>Usuario</th><th>Medio</th><th>Ref.</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-history"></i> Historial de cajas</h3>
        <?php if ($esAdmin) { ?>
        <div class="box-tools">
          <label class="login-remember" style="font-size:13px"><input type="checkbox" id="chkTodasCajas"> <span>Ver cajas de todos los usuarios</span></label>
        </div>
        <?php } ?>
      </div>
      <div class="box-body table-responsive">
        <table id="tblhistcaja" class="table table-striped table-bordered table-hover" style="width:100%">
          <thead><tr><th>#</th><th>Apertura</th><th>Cierre</th><th>Inicial</th><th>Ingresos</th><th>Egresos</th><th>Sistema</th><th>Real</th><th>Diferencia</th><th>Estado</th><th>Usuario</th><th></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </section>
</div>

<!-- Modal abrir caja -->
<div class="modal fade" id="modalAbrirCaja" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
    <form id="formAbrirCaja" autocomplete="off">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-unlock"></i> Abrir caja</h4></div>
      <div class="modal-body">
        <div class="form-group"><label>Monto inicial en efectivo <span class="req">*</span></label><input type="number" step="0.01" min="0" class="form-control input-lg" name="monto_apertura" id="monto_apertura" placeholder="0.00" required></div>
        <div class="form-group"><label>Observación</label><input type="text" class="form-control" name="observacion" id="obs_apertura" maxlength="200" placeholder="Opcional"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success" id="btnConfirmarAbrir"><i class="fa fa-unlock"></i> Abrir</button></div>
    </form>
  </div></div>
</div>

<!-- Modal movimiento -->
<div class="modal fade" id="modalMovimiento" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
    <form id="formMovimientoCaja" autocomplete="off">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-exchange"></i> Movimiento manual</h4></div>
      <div class="modal-body">
        <div class="form-group"><label>Tipo</label>
          <select class="form-control" name="tipo" id="mov_tipo"><option value="INGRESO">Ingreso (entra dinero)</option><option value="EGRESO">Egreso (sale dinero)</option></select>
        </div>
        <div class="form-group"><label>Concepto <span class="req">*</span></label><input type="text" class="form-control" name="concepto" id="mov_concepto" maxlength="120" placeholder="Ej. Pago de movilidad" required></div>
        <div class="row">
          <div class="form-group col-xs-6"><label>Monto <span class="req">*</span></label><input type="number" step="0.01" min="0.01" class="form-control" name="monto" id="mov_monto" required></div>
          <div class="form-group col-xs-6"><label>Medio</label><select class="form-control" name="medio_pago" id="mov_medio"><option value="EFECTIVO">Efectivo</option><option value="DEPOSITO">Depósito en cuenta</option><option value="TARJETA">Tarjeta</option><option value="TRANSFERENCIA">Transferencia</option><option value="YAPE">Yape</option><option value="PLIN">Plin</option><option value="OTRO">Otro</option></select></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="btnConfirmarMov"><i class="fa fa-plus"></i> Registrar</button></div>
    </form>
  </div></div>
</div>

<!-- Modal cerrar caja -->
<div class="modal fade" id="modalCerrarCaja" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
    <form id="formCerrarCaja" autocomplete="off">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-lock"></i> Cerrar caja (arqueo)</h4></div>
      <div class="modal-body">
        <div class="alert alert-info" style="font-size:13px">Efectivo esperado: <strong id="cierreSistema">—</strong>. Cuenta solo los billetes y monedas del cajón; Yape, tarjeta, transferencias y depósitos no se cuentan aquí (<span id="cierreOtros">—</span>). El sistema calcula la diferencia.</div>
        <div class="form-group"><label>Monto real contado <span class="req">*</span></label><input type="number" step="0.01" min="0" class="form-control input-lg" name="monto_cierre_real" id="monto_cierre_real" placeholder="0.00" required></div>
        <div class="form-group"><label>Observación de cierre</label><input type="text" class="form-control" name="observacion" id="obs_cierre" maxlength="200" placeholder="Opcional"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger" id="btnConfirmarCerrar"><i class="fa fa-lock"></i> Cerrar caja</button></div>
    </form>
  </div></div>
</div>

<!-- Modal detalle caja -->
<div class="modal fade" id="modalDetalleCaja" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-file-text-o"></i> Detalle de caja <span id="detCajaTitulo"></span></h4></div>
    <div class="modal-body" id="detCajaBody"></div>
    <div class="modal-footer"><button type="button" class="btn btn-info" id="btnImprimirCaja"><i class="fa fa-print"></i> Imprimir</button><button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button></div>
  </div></div>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/caja.js?v=<?php echo e(APP_VERSION); ?>"></script>
