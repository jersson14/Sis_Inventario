<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Ventas";
$iconoPagina = "fa-shopping-cart";
require 'header.php';
if (usuarioTienePermiso('ventas')) {
  $simbolo = obtenerSimboloMoneda();
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Ventas <i class="fa fa-chevron-right"></i> Punto de venta</div>
        <h1><span class="page-icon"><i class="fa fa-shopping-cart"></i></span> Ventas</h1>
        <p>Registra ventas al contado o al crédito, imprime comprobantes y controla tu caja.</p>
      </div>
      <div class="page-actions" id="accionesListado">
        <div class="chip chip-primary" id="chipResumenDia" title="Ventas aceptadas de hoy"><i class="fa fa-calendar-check-o"></i> Hoy: <span>—</span></div>
        <a href="caja.php" class="btn btn-default" title="Ir a caja diaria"><i class="fa fa-money"></i> Caja</a>
        <button class="btn btn-primary" onclick="mostrarform(true)" id="btnagregar"><i class="fa fa-plus"></i> Nueva venta <small style="opacity:.7">(Alt+V)</small></button>
      </div>
    </div>

    <!-- LISTADO -->
    <div class="box" id="listadoregistros">
      <div class="box-body">
        <div class="table-toolbar">
          <div class="form-group">
            <label>Desde</label>
            <input type="date" id="filtro_venta_inicio" class="form-control input-sm">
          </div>
          <div class="form-group">
            <label>Hasta</label>
            <input type="date" id="filtro_venta_fin" class="form-control input-sm">
          </div>
          <div class="form-group">
            <label>Estado</label>
            <select id="filtro_venta_estado" class="form-control input-sm">
              <option value="">Todos</option>
              <option value="Aceptado">Aceptadas</option>
              <option value="Anulado">Anuladas</option>
            </select>
          </div>
          <div class="form-group">
            <label>Tipo de pago</label>
            <select id="filtro_venta_pago" class="form-control input-sm">
              <option value="">Todos</option>
              <option value="CONTADO">Contado</option>
              <option value="CREDITO">Crédito</option>
            </select>
          </div>
          <div class="toolbar-actions">
            <button type="button" class="btn btn-primary btn-sm" id="btnFiltrarVenta"><i class="fa fa-filter"></i> Filtrar</button>
            <button type="button" class="btn btn-default btn-sm" id="btnLimpiarFiltroVenta"><i class="fa fa-eraser"></i> Limpiar</button>
          </div>
        </div>
        <div class="table-responsive">
          <table id="tbllistado" class="table table-striped table-bordered table-hover" style="width:100%">
            <thead>
              <tr>
                <th>Opciones</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Vendedor</th>
                <th>Comprobante</th>
                <th>Número</th>
                <th>Total</th>
                <th>Pago</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- FORMULARIO POS -->
    <div id="formularioregistros">
      <form action="" name="formulario" id="formulario" method="POST" autocomplete="off">
        <input type="hidden" name="idventa" id="idventa">
        <div class="pos-layout">
          <div class="pos-main">
            <div class="box">
              <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cart-plus"></i> Nueva venta</h3>
                <div class="box-tools">
                  <button class="btn btn-default btn-sm" onclick="cancelarform()" type="button" id="btnCancelarTop"><i class="fa fa-arrow-left"></i> Volver al listado</button>
                </div>
              </div>
              <div class="box-body">
                <div class="row">
                  <div class="form-group col-lg-7 col-md-7 col-xs-12">
                    <label for="idcliente">Cliente <span class="req">*</span></label>
                    <div class="input-group" style="display:flex;gap:6px;">
                      <select name="idcliente" id="idcliente" class="form-control selectpicker" data-live-search="true" data-width="100%" data-size="8" required></select>
                      <button type="button" class="btn btn-default" id="btnNuevoCliente" data-toggle="modal" data-target="#modalClienteVenta" title="Registrar cliente rápido" style="flex:0 0 auto;"><i class="fa fa-user-plus"></i></button>
                    </div>
                  </div>
                  <div class="form-group col-lg-5 col-md-5 col-xs-12">
                    <label for="fecha_hora">Fecha y hora <span class="req">*</span></label>
                    <input class="form-control" type="datetime-local" name="fecha_hora" id="fecha_hora" required>
                  </div>
                </div>
                <div class="row">
                  <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                    <label for="tipo_comprobante">Comprobante</label>
                    <select name="tipo_comprobante" id="tipo_comprobante" class="form-control">
                      <option value="Boleta">Boleta</option>
                      <option value="Factura">Factura</option>
                      <option value="Ticket">Ticket</option>
                    </select>
                  </div>
                  <div class="form-group col-lg-2 col-md-2 col-sm-3 col-xs-6">
                    <label for="serie_comprobante">Serie</label>
                    <input class="form-control" type="text" name="serie_comprobante" id="serie_comprobante" maxlength="7" placeholder="B001">
                  </div>
                  <div class="form-group col-lg-3 col-md-3 col-sm-3 col-xs-6">
                    <label for="num_comprobante">Número <small class="text-soft">(auto)</small></label>
                    <input class="form-control" type="text" name="num_comprobante" id="num_comprobante" maxlength="10" placeholder="Automático">
                  </div>
                  <div class="form-group col-lg-2 col-md-2 col-sm-6 col-xs-6">
                    <label for="impuesto">Impuesto %</label>
                    <input class="form-control" type="number" step="0.01" min="0" max="100" name="impuesto" id="impuesto" value="0">
                  </div>
                  <div class="form-group col-lg-2 col-md-2 col-sm-6 col-xs-6">
                    <label>&nbsp;</label>
                    <button id="btnAgregarArt" type="button" class="btn btn-catalog-open w-100" data-toggle="modal" data-target="#myModal" title="Abrir catálogo (F2)"><i class="fa fa-th-large"></i> Catálogo</button>
                  </div>
                </div>

                <div class="form-group">
                  <label for="codigo_rapido"><i class="fa fa-barcode"></i> Código de barras / código del producto</label>
                  <div class="input-group">
                    <input type="text" class="form-control" id="codigo_rapido" placeholder="Escanea o escribe el código y presiona Enter" autocomplete="off">
                    <span class="input-group-btn">
                      <button class="btn btn-success" type="button" id="btnBuscarCodigo"><i class="fa fa-plus"></i> Agregar</button>
                    </span>
                  </div>
                </div>

                <div class="table-responsive">
                  <table id="detalles" class="table table-bordered table-hover">
                    <thead>
                      <tr>
                        <th style="width:52px"></th>
                        <th>Artículo</th>
                        <th>Unidad</th>
                        <th style="width:110px">Cantidad</th>
                        <th style="width:120px">Precio</th>
                        <th style="width:110px">Dscto.</th>
                        <th style="width:120px" class="text-right">Subtotal</th>
                        <th style="width:44px"></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                      <tr>
                        <th colspan="6" class="text-right">TOTAL</th>
                        <th class="text-right"><h4 id="total" class="mb-0"><?php echo e(formatearMoneda(0)); ?></h4><input type="hidden" name="total_venta" id="total_venta"></th>
                        <th></th>
                      </tr>
                    </tfoot>
                  </table>
                  <div class="empty-state" id="detalleVacio">
                    <i class="fa fa-barcode"></i>
                    <strong>Aún no hay artículos</strong>
                    Escanea un código, escribe el código del producto o abre el catálogo (F2).
                  </div>
                </div>
              </div>
            </div>
          </div>

          <aside class="pos-side">
            <div class="pos-summary">
              <div class="pos-summary-title">Resumen de la venta</div>
              <div class="pos-total" id="posTotal"><?php echo e(formatearMoneda(0)); ?></div>
              <div class="pos-row"><span>Artículos</span><span id="ventasItemsSeleccionados">0</span></div>
              <div class="pos-row"><span>Unidades</span><span id="posUnidades">0</span></div>
              <div class="pos-row"><span>Descuentos</span><span id="posDescuentos"><?php echo e($simbolo); ?> 0.00</span></div>

              <div style="margin-top:14px">
                <div class="pos-summary-title">Forma de pago</div>
                <div class="pago-toggle">
                  <button type="button" class="btn btn-default btn-sm active" data-pago="CONTADO"><i class="fa fa-money"></i> Contado</button>
                  <button type="button" class="btn btn-default btn-sm" data-pago="CREDITO"><i class="fa fa-calendar"></i> Crédito</button>
                </div>
                <input type="hidden" name="tipo_pago" id="tipo_pago" value="CONTADO">
              </div>
              <div style="margin-top:10px" id="grupoMedioPago">
                <label style="color:#cbd5e1;font-size:12px">Medio de pago</label>
                <select name="medio_pago" id="medio_pago" class="form-control input-sm">
                  <option value="EFECTIVO">Efectivo</option>
                  <option value="TARJETA">Tarjeta</option>
                  <option value="TRANSFERENCIA">Transferencia</option>
                  <option value="YAPE">Yape</option>
                  <option value="PLIN">Plin</option>
                  <option value="OTRO">Otro</option>
                </select>
              </div>
              <div style="margin-top:10px;display:none" id="grupoVencimiento">
                <label style="color:#cbd5e1;font-size:12px">Fecha de vencimiento del crédito</label>
                <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control input-sm">
                <small style="color:#94a3b8">Se creará una cuenta por cobrar automáticamente.</small>
              </div>
              <div style="margin-top:10px">
                <label style="color:#cbd5e1;font-size:12px">Observación</label>
                <input type="text" name="observacion" id="observacion" class="form-control input-sm" maxlength="200" placeholder="Opcional">
              </div>

              <button class="btn btn-success btn-lg" type="submit" id="btnGuardar"><i class="fa fa-check"></i> Registrar venta <small>(F4)</small></button>
              <button class="btn btn-default" onclick="cancelarform()" type="button" id="btnCancelar"><i class="fa fa-times"></i> Cancelar</button>
              <div class="pos-shortcuts">
                <kbd>F2</kbd> catálogo &nbsp; <kbd>Ctrl+B</kbd> código &nbsp; <kbd>F4</kbd> guardar &nbsp; <kbd>Esc</kbd> cancelar
              </div>
            </div>
          </aside>
        </div>
      </form>
    </div>
  </section>
</div>

<!-- Modal catálogo -->
<div class="modal fade modal-catalog" id="myModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header catalog-modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title"><i class="fa fa-th-large"></i> Catálogo de artículos</h4>
        <p class="catalog-subtitle">Haz clic en "Agregar" o escribe para buscar por nombre, código o categoría</p>
      </div>
      <div class="modal-body">
        <div class="catalog-info-row">
          <div class="catalog-tip"><i class="fa fa-lightbulb-o"></i> Si un producto ya está en la venta, al agregarlo de nuevo se incrementa su cantidad.</div>
          <div class="catalog-counter"><span id="ventasItemsSeleccionadosModal">0</span> artículos en la venta</div>
        </div>
        <table id="tblarticulos" class="table table-striped table-bordered table-hover" style="width:100%">
          <thead><tr><th>Agregar</th><th>Nombre</th><th>Categoría</th><th>Unidad</th><th>Código</th><th>Stock</th><th>Precio</th><th>Imagen</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" type="button" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal cliente rápido -->
<div class="modal fade" id="modalClienteVenta" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formClienteRapido" autocomplete="off">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title"><i class="fa fa-user-plus"></i> Nuevo cliente</h4>
        </div>
        <div class="modal-body">
          <div class="form-group"><label>Nombre <span class="req">*</span></label><input type="text" class="form-control" name="nombre" id="cli_nombre" maxlength="100" required></div>
          <div class="row">
            <div class="form-group col-xs-5"><label>Tipo doc.</label><select class="form-control" name="tipo_documento" id="cli_tipo_documento"><option value="DNI">DNI</option><option value="RUC">RUC</option><option value="CEDULA">Cédula</option></select></div>
            <div class="form-group col-xs-7"><label>N° documento</label><input type="text" class="form-control" name="num_documento" id="cli_num_documento" maxlength="20"></div>
          </div>
          <div class="form-group"><label>Dirección</label><input type="text" class="form-control" name="direccion" id="cli_direccion" maxlength="70"></div>
          <div class="row">
            <div class="form-group col-xs-6"><label>Teléfono</label><input type="text" class="form-control" name="telefono" id="cli_telefono" maxlength="20"></div>
            <div class="form-group col-xs-6"><label>Email</label><input type="email" class="form-control" name="email" id="cli_email" maxlength="50"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-primary" id="btnGuardarClienteRapido"><i class="fa fa-save"></i> Guardar cliente</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal detalle de venta -->
<div class="modal fade" id="modalDetalleVenta" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-file-text-o"></i> Detalle de venta <span id="detTitulo"></span></h4>
      </div>
      <div class="modal-body">
        <div class="row" id="detCabecera"></div>
        <div class="table-responsive">
          <table class="table table-bordered table-condensed" id="detTabla"></table>
        </div>
      </div>
      <div class="modal-footer">
        <a id="detImprimir" href="#" target="_blank" class="btn btn-info"><i class="fa fa-print"></i> Imprimir comprobante</a>
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
<script src="scripts/venta.js?v=<?php echo e(APP_VERSION); ?>"></script>
