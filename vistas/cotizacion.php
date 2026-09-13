<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Cotizaciones";
$iconoPagina = "fa-file-text-o";
require 'header.php';
if (usuarioTienePermiso('ventas')) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Ventas <i class="fa fa-chevron-right"></i> Cotizaciones</div>
        <h1><span class="page-icon"><i class="fa fa-file-text-o"></i></span> Cotizaciones / proformas</h1>
        <p>Envía presupuestos a tus clientes sin mover stock. Cuando acepten, conviértelos en venta con un clic.</p>
      </div>
      <div class="page-actions" id="accionesListado">
        <button class="btn btn-primary" onclick="mostrarform(true)" id="btnagregar"><i class="fa fa-plus"></i> Nueva cotización</button>
      </div>
    </div>

    <div class="alert-cards" id="cotResumen">
      <div class="alert-card is-warning"><div class="alert-ico"><i class="fa fa-clock-o"></i></div><div><strong id="resPend">—</strong><span id="resPendTxt">Pendientes</span></div></div>
      <div class="alert-card is-success"><div class="alert-ico"><i class="fa fa-check"></i></div><div><strong id="resConv">—</strong><span>Convertidas en venta (30 días)</span></div></div>
      <div class="alert-card is-info"><div class="alert-ico"><i class="fa fa-percent"></i></div><div><strong id="resTasa">—</strong><span>Tasa de conversión (30 días)</span></div></div>
    </div>

    <div class="box" id="listadoregistros">
      <div class="box-body">
        <div class="table-toolbar">
          <div class="form-group"><label>Desde</label><input type="date" id="f_inicio" class="form-control input-sm"></div>
          <div class="form-group"><label>Hasta</label><input type="date" id="f_fin" class="form-control input-sm"></div>
          <div class="form-group"><label>Estado</label>
            <select id="f_estado" class="form-control input-sm"><option value="">Todas</option><option value="PENDIENTE">Pendientes</option><option value="ACEPTADA">Aceptadas</option><option value="CONVERTIDA">Convertidas</option><option value="RECHAZADA">Rechazadas</option><option value="VENCIDA">Vencidas</option></select>
          </div>
          <div class="toolbar-actions">
            <button type="button" class="btn btn-primary btn-sm" id="btnFiltrar"><i class="fa fa-filter"></i> Filtrar</button>
            <button type="button" class="btn btn-default btn-sm" id="btnLimpiar"><i class="fa fa-eraser"></i> Limpiar</button>
          </div>
        </div>
        <div class="table-responsive">
          <table id="tbllistado" class="table table-striped table-bordered table-hover" style="width:100%">
            <thead><tr><th>Opciones</th><th>Número</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Válida hasta</th><th>Total</th><th>Estado</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="formularioregistros">
      <form id="formulario" method="POST" autocomplete="off">
        <input type="hidden" name="idcotizacion" id="idcotizacion">
        <div class="pos-layout">
          <div class="pos-main">
            <div class="box">
              <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-file-text-o"></i> <span id="formTitulo">Nueva cotización</span> <span class="chip chip-primary" id="chipNumero">—</span></h3>
                <div class="box-tools"><button class="btn btn-default btn-sm" onclick="cancelarform()" type="button"><i class="fa fa-arrow-left"></i> Volver</button></div>
              </div>
              <div class="box-body">
                <div class="row">
                  <div class="form-group col-md-6"><label>Cliente <span class="req">*</span></label><select name="idcliente" id="idcliente" class="form-control selectpicker" data-live-search="true" data-width="100%" required></select></div>
                  <div class="form-group col-md-3"><label>Fecha</label><input type="datetime-local" class="form-control" name="fecha_hora" id="fecha_hora"></div>
                  <div class="form-group col-md-3"><label>Válida hasta <span class="req">*</span></label><input type="date" class="form-control" name="fecha_validez" id="fecha_validez" required></div>
                </div>
                <div class="row">
                  <div class="form-group col-md-2 col-xs-4"><label>Impuesto %</label><input type="number" step="0.01" min="0" max="100" class="form-control" name="impuesto" id="impuesto" value="0"></div>
                  <div class="form-group col-md-7 col-xs-8">
                    <label><i class="fa fa-barcode"></i> Código de producto</label>
                    <div class="input-group"><input type="text" class="form-control" id="codigo_rapido" placeholder="Escanea o escribe y presiona Enter"><span class="input-group-btn"><button class="btn btn-success" type="button" id="btnBuscarCodigo"><i class="fa fa-plus"></i></button></span></div>
                  </div>
                  <div class="form-group col-md-3 col-xs-12"><label>&nbsp;</label><button type="button" class="btn btn-catalog-open w-100" data-toggle="modal" data-target="#myModal" title="Abrir catálogo (F2)"><i class="fa fa-th-large"></i> Catálogo</button></div>
                </div>
                <div class="table-responsive">
                  <table id="detalles" class="table table-bordered table-hover">
                    <thead><tr><th style="width:52px"></th><th>Artículo</th><th>Unidad</th><th style="width:110px">Cantidad</th><th style="width:120px">Precio</th><th style="width:110px">Dscto.</th><th style="width:120px" class="text-right">Subtotal</th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><th colspan="6" class="text-right">TOTAL</th><th class="text-right"><h4 id="total" class="mb-0"><?php echo e(formatearMoneda(0)); ?></h4></th></tr></tfoot>
                  </table>
                  <div class="empty-state" id="detalleVacio"><i class="fa fa-file-text-o"></i><strong>Aún no hay artículos</strong>Agrega productos desde el catálogo (F2) o por código.</div>
                </div>
                <div class="row">
                  <div class="form-group col-md-6"><label>Observación (visible en el PDF)</label><input type="text" class="form-control" name="observacion" id="observacion" maxlength="300" placeholder="Ej. Incluye entrega en obra"></div>
                  <div class="form-group col-md-6"><label>Condiciones</label><input type="text" class="form-control" name="condiciones" id="condiciones" maxlength="300" placeholder="Ej. Pago 50% adelanto, saldo contra entrega"></div>
                </div>
              </div>
            </div>
          </div>
          <aside class="pos-side">
            <div class="pos-summary">
              <div class="pos-summary-title">Resumen</div>
              <div class="pos-total" id="posTotal"><?php echo e(formatearMoneda(0)); ?></div>
              <div class="pos-row"><span>Artículos</span><span id="posItems">0</span></div>
              <div class="pos-row"><span>Unidades</span><span id="posUnidades">0</span></div>
              <div class="pos-row"><span>Descuentos</span><span id="posDescuentos">—</span></div>
              <button class="btn btn-success btn-lg" type="submit" id="btnGuardar"><i class="fa fa-check"></i> Guardar cotización <small>(F4)</small></button>
              <button class="btn btn-default" onclick="cancelarform()" type="button"><i class="fa fa-times"></i> Cancelar</button>
              <div class="pos-shortcuts"><kbd>F2</kbd> catálogo &nbsp; <kbd>F4</kbd> guardar &nbsp; <kbd>Esc</kbd> cancelar<br>La cotización no descuenta stock.</div>
            </div>
          </aside>
        </div>
      </form>
    </div>
  </section>
</div>

<div class="modal fade modal-catalog" id="myModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header catalog-modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-th-large"></i> Catálogo de artículos</h4><p class="catalog-subtitle">Los precios se toman del artículo; puedes cambiarlos en la cotización</p></div>
    <div class="modal-body">
      <table id="tblarticulos" class="table table-striped table-bordered table-hover" style="width:100%">
        <thead><tr><th>Agregar</th><th>Nombre</th><th>Categoría</th><th>Unidad</th><th>Código</th><th>Stock</th><th>Precio</th><th>Imagen</th></tr></thead><tbody></tbody>
      </table>
    </div>
    <div class="modal-footer"><button class="btn btn-default" type="button" data-dismiss="modal">Cerrar</button></div>
  </div></div>
</div>

<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-file-text-o"></i> Cotización <span id="detNumero"></span></h4></div>
    <div class="modal-body"><div class="row" id="detCabecera"></div><div class="table-responsive"><table class="table table-bordered table-condensed" id="detTabla"></table></div></div>
    <div class="modal-footer"><a id="detPdf" href="#" target="_blank" class="btn btn-info"><i class="fa fa-file-pdf-o"></i> PDF</a><a id="detVender" href="#" class="btn btn-success"><i class="fa fa-shopping-cart"></i> Convertir en venta</a><button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button></div>
  </div></div>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/cotizacion.js?v=<?php echo e(APP_VERSION); ?>"></script>
