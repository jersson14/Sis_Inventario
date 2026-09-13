<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Compras / Ingresos";
$iconoPagina = "fa-truck";
require 'header.php';
if (usuarioTienePermiso('compras')) {
  $simbolo = obtenerSimboloMoneda();
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Compras <i class="fa fa-chevron-right"></i> Ingresos</div>
        <h1><span class="page-icon"><i class="fa fa-truck"></i></span> Compras e ingresos de mercadería</h1>
        <p>Registra lo que compras a tus proveedores: el stock y los precios de referencia se actualizan solos.</p>
      </div>
      <div class="page-actions" id="accionesListado">
        <a href="proveedor.php" class="btn btn-default"><i class="fa fa-truck"></i> Proveedores</a>
        <button class="btn btn-primary" onclick="mostrarform(true)" id="btnagregar"><i class="fa fa-plus"></i> Nueva compra <small style="opacity:.7">(Alt+C)</small></button>
      </div>
    </div>

    <div class="box" id="listadoregistros">
      <div class="box-body">
        <div class="table-toolbar">
          <div class="form-group"><label>Desde</label><input type="date" id="filtro_ingreso_inicio" class="form-control input-sm"></div>
          <div class="form-group"><label>Hasta</label><input type="date" id="filtro_ingreso_fin" class="form-control input-sm"></div>
          <div class="form-group"><label>Estado</label>
            <select id="filtro_ingreso_estado" class="form-control input-sm"><option value="">Todos</option><option value="Aceptado">Aceptados</option><option value="Anulado">Anulados</option></select>
          </div>
          <div class="form-group"><label>Tipo de pago</label>
            <select id="filtro_ingreso_pago" class="form-control input-sm"><option value="">Todos</option><option value="CONTADO">Contado</option><option value="CREDITO">Crédito</option></select>
          </div>
          <div class="toolbar-actions">
            <button type="button" class="btn btn-primary btn-sm" id="btnFiltrarIngreso"><i class="fa fa-filter"></i> Filtrar</button>
            <button type="button" class="btn btn-default btn-sm" id="btnLimpiarFiltroIngreso"><i class="fa fa-eraser"></i> Limpiar</button>
          </div>
        </div>
        <div class="table-responsive">
          <table id="tbllistado" class="table table-striped table-bordered table-hover" style="width:100%">
            <thead><tr><th>Opciones</th><th>Fecha</th><th>Proveedor</th><th>Usuario</th><th>Comprobante</th><th>Número</th><th>Total</th><th>Pago</th><th>Estado</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="formularioregistros">
      <form action="" name="formulario" id="formulario" method="POST" autocomplete="off">
        <input type="hidden" name="idingreso" id="idingreso">
        <div class="pos-layout">
          <div class="pos-main">
            <div class="box">
              <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cart-arrow-down"></i> Nueva compra</h3>
                <div class="box-tools"><button class="btn btn-default btn-sm" onclick="cancelarform()" type="button"><i class="fa fa-arrow-left"></i> Volver al listado</button></div>
              </div>
              <div class="box-body">
                <div class="row">
                  <div class="form-group col-lg-7 col-md-7 col-xs-12">
                    <label for="idproveedor">Proveedor <span class="req">*</span></label>
                    <div style="display:flex;gap:6px;">
                      <select name="idproveedor" id="idproveedor" class="form-control selectpicker" data-live-search="true" data-width="100%" data-size="8" required></select>
                      <button type="button" class="btn btn-default" id="btnNuevoProveedor" data-toggle="modal" data-target="#modalProveedorIngreso" title="Registrar proveedor rápido" style="flex:0 0 auto;"><i class="fa fa-plus"></i></button>
                    </div>
                  </div>
                  <div class="form-group col-lg-5 col-md-5 col-xs-12">
                    <label for="fecha_hora">Fecha y hora <span class="req">*</span></label>
                    <input class="form-control" type="datetime-local" name="fecha_hora" id="fecha_hora" required>
                  </div>
                </div>
                <div class="row">
                  <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                    <label for="tipo_comprobante">Comprobante del proveedor</label>
                    <select name="tipo_comprobante" id="tipo_comprobante" class="form-control"><option value="Boleta">Boleta</option><option value="Factura">Factura</option><option value="Ticket">Ticket / Guía</option></select>
                  </div>
                  <div class="form-group col-lg-2 col-md-2 col-sm-3 col-xs-6"><label for="serie_comprobante">Serie</label><input class="form-control" type="text" name="serie_comprobante" id="serie_comprobante" maxlength="7" placeholder="F001"></div>
                  <div class="form-group col-lg-3 col-md-3 col-sm-3 col-xs-6"><label for="num_comprobante">Número</label><input class="form-control" type="text" name="num_comprobante" id="num_comprobante" maxlength="10" placeholder="Del comprobante"></div>
                  <div class="form-group col-lg-2 col-md-2 col-sm-6 col-xs-6"><label for="impuesto">Impuesto %</label><input class="form-control" type="number" step="0.01" min="0" max="100" name="impuesto" id="impuesto" value="0"></div>
                  <div class="form-group col-lg-2 col-md-2 col-sm-6 col-xs-6"><label>&nbsp;</label><button id="btnAgregarArt" type="button" class="btn btn-catalog-open w-100" data-toggle="modal" data-target="#myModal" title="Abrir catálogo (F2)"><i class="fa fa-th-large"></i> Catálogo</button></div>
                </div>
                <div class="table-responsive">
                  <table id="detalles" class="table table-bordered table-hover">
                    <thead><tr><th style="width:52px"></th><th>Artículo</th><th>Unidad</th><th style="width:110px">Cantidad</th><th style="width:120px">P. Compra</th><th style="width:120px">P. Venta</th><th style="width:120px" class="text-right">Subtotal</th><th style="width:44px"></th></tr></thead>
                    <tbody></tbody>
                    <tfoot><tr><th colspan="6" class="text-right">TOTAL</th><th class="text-right"><h4 id="total" class="mb-0"><?php echo e(formatearMoneda(0)); ?></h4><input type="hidden" name="total_compra" id="total_compra"></th><th></th></tr></tfoot>
                  </table>
                  <div class="empty-state" id="detalleVacio"><i class="fa fa-cubes"></i><strong>Aún no hay artículos</strong>Abre el catálogo (F2) y agrega los productos que ingresan.</div>
                </div>
              </div>
            </div>
          </div>
          <aside class="pos-side">
            <div class="pos-summary">
              <div class="pos-summary-title">Resumen de la compra</div>
              <div class="pos-total" id="posTotal"><?php echo e(formatearMoneda(0)); ?></div>
              <div class="pos-row"><span>Artículos</span><span id="comprasItemsSeleccionados">0</span></div>
              <div class="pos-row"><span>Unidades</span><span id="posUnidades">0</span></div>
              <div style="margin-top:14px">
                <div class="pos-summary-title">Forma de pago</div>
                <div class="pago-toggle">
                  <button type="button" class="btn btn-default btn-sm active" data-pago="CONTADO"><i class="fa fa-money"></i> Contado</button>
                  <button type="button" class="btn btn-default btn-sm" data-pago="CREDITO"><i class="fa fa-calendar"></i> Crédito</button>
                </div>
                <input type="hidden" name="tipo_pago" id="tipo_pago" value="CONTADO">
              </div>
              <div style="margin-top:10px">
                <label style="color:#cbd5e1;font-size:12px">Medio de pago</label>
                <select name="medio_pago" id="medio_pago" class="form-control input-sm"><option value="EFECTIVO">Efectivo</option><option value="TARJETA">Tarjeta</option><option value="TRANSFERENCIA">Transferencia</option><option value="YAPE">Yape</option><option value="PLIN">Plin</option><option value="OTRO">Otro</option></select>
              </div>
              <div style="margin-top:10px;display:none" id="grupoVencimiento">
                <label style="color:#cbd5e1;font-size:12px">Vencimiento del crédito</label>
                <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control input-sm">
                <small style="color:#94a3b8">Se creará una cuenta por pagar automáticamente.</small>
              </div>
              <div style="margin-top:10px">
                <label style="color:#cbd5e1;font-size:12px">Observación</label>
                <input type="text" name="observacion" id="observacion" class="form-control input-sm" maxlength="200" placeholder="Opcional">
              </div>
              <button class="btn btn-success btn-lg" type="submit" id="btnGuardar"><i class="fa fa-check"></i> Registrar compra <small>(F4)</small></button>
              <button class="btn btn-default" onclick="cancelarform()" type="button" id="btnCancelar"><i class="fa fa-times"></i> Cancelar</button>
              <div class="pos-shortcuts"><kbd>F2</kbd> catálogo &nbsp; <kbd>F4</kbd> guardar &nbsp; <kbd>Esc</kbd> cancelar</div>
            </div>
          </aside>
        </div>
      </form>
    </div>
  </section>
</div>

<div class="modal fade modal-catalog" id="myModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header catalog-modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title"><i class="fa fa-th-large"></i> Catálogo de artículos</h4>
        <p class="catalog-subtitle">Selecciona los productos que ingresan al almacén</p>
      </div>
      <div class="modal-body">
        <div class="catalog-info-row">
          <div class="catalog-tip"><i class="fa fa-lightbulb-o"></i> Si agregas un producto repetido se incrementa su cantidad. ¿No existe? <a href="articulo.php?nuevo=1" target="_blank">Créalo aquí</a>.</div>
          <div class="catalog-counter"><span id="comprasItemsSeleccionadosModal">0</span> artículos en la compra</div>
        </div>
        <table id="tblarticulos" class="table table-striped table-bordered table-hover" style="width:100%">
          <thead><tr><th>Agregar</th><th>Nombre</th><th>Categoría</th><th>Unidad</th><th>Código</th><th>Stock</th><th>Últ. costo</th><th>Imagen</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="modal-footer"><button class="btn btn-default" type="button" data-dismiss="modal">Cerrar</button></div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalProveedorIngreso" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formProveedorRapido" autocomplete="off">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title"><i class="fa fa-truck"></i> Nuevo proveedor</h4>
        </div>
        <div class="modal-body">
          <div class="form-group"><label>Nombre o razón social <span class="req">*</span></label><input type="text" class="form-control" name="nombre" id="prv_nombre" maxlength="100" required></div>
          <div class="row">
            <div class="form-group col-xs-5"><label>Tipo doc.</label><select class="form-control" name="tipo_documento" id="prv_tipo_documento"><option value="RUC">RUC</option><option value="DNI">DNI</option><option value="CEDULA">Cédula</option></select></div>
            <div class="form-group col-xs-7"><label>N° documento</label><input type="text" class="form-control" name="num_documento" id="prv_num_documento" maxlength="20"></div>
          </div>
          <div class="form-group"><label>Dirección</label><input type="text" class="form-control" name="direccion" id="prv_direccion" maxlength="70"></div>
          <div class="row">
            <div class="form-group col-xs-6"><label>Teléfono</label><input type="text" class="form-control" name="telefono" id="prv_telefono" maxlength="20"></div>
            <div class="form-group col-xs-6"><label>Email</label><input type="email" class="form-control" name="email" id="prv_email" maxlength="50"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-primary" id="btnGuardarProveedorRapido"><i class="fa fa-save"></i> Guardar proveedor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalDetalleIngreso" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-file-text-o"></i> Detalle de compra <span id="detTitulo"></span></h4>
      </div>
      <div class="modal-body">
        <div class="row" id="detCabecera"></div>
        <div class="table-responsive"><table class="table table-bordered table-condensed" id="detTabla"></table></div>
      </div>
      <div class="modal-footer">
        <a id="detImprimir" href="#" target="_blank" class="btn btn-info"><i class="fa fa-print"></i> Imprimir</a>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/ingreso.js?v=<?php echo e(APP_VERSION); ?>"></script>
