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

    <div class="alert-borrador" id="avisoBorradorListado" style="display:none">
      <i class="fa fa-pencil-square-o"></i>
      <span>Tienes una compra sin terminar <strong id="avisoBorradorResumen"></strong>.</span>
      <button type="button" class="btn btn-primary btn-sm" onclick="mostrarform(true)"><i class="fa fa-play"></i> Continuar</button>
      <button type="button" class="btn btn-default btn-sm" onclick="descartarBorrador()"><i class="fa fa-trash"></i> Descartar</button>
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

    <!-- FORMULARIO DE COMPRA (pantalla completa) -->
    <div id="formularioregistros" class="caja-pantalla">
      <form action="" name="formulario" id="formulario" method="POST" autocomplete="off">
        <input type="hidden" name="idingreso" id="idingreso">

        <div class="caja-topbar">
          <div class="caja-topbar-titulo"><span class="page-icon"><i class="fa fa-cart-arrow-down"></i></span> Nueva compra</div>
          <div class="caja-autoguardado" id="estadoBorrador" title="La compra se guarda sola en esta PC mientras la registras"><i class="fa fa-cloud"></i> <span>Autoguardado activo</span></div>
          <div class="caja-topbar-acciones">
            <button type="button" class="btn btn-default btn-sm btn-pantalla-completa" onclick="appAlternarPantallaCompleta()" title="Pantalla completa (F11)"><i class="fa fa-expand"></i></button>
            <button type="button" class="btn btn-default btn-sm" onclick="cancelarform()" title="Volver al listado (Esc)"><i class="fa fa-arrow-left"></i> Listado</button>
          </div>
        </div>

        <div class="pos-layout">
          <div class="pos-main">
            <div class="box caja-doc">
              <div class="box-body">
                <div class="caja-doc-grid">
                  <div class="form-group campo-proveedor">
                    <label for="idproveedor">Proveedor <span class="req">*</span></label>
                    <div style="display:flex;gap:6px;">
                      <select name="idproveedor" id="idproveedor" class="form-control selectpicker" data-live-search="true" data-width="100%" data-size="8" required></select>
                      <button type="button" class="btn btn-default" id="btnNuevoProveedor" data-toggle="modal" data-target="#modalProveedorIngreso" title="Registrar proveedor rápido" style="flex:0 0 auto;"><i class="fa fa-plus"></i></button>
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="tipo_comprobante">Comprobante</label>
                    <select name="tipo_comprobante" id="tipo_comprobante" class="form-control"><option value="Boleta">Boleta</option><option value="Factura">Factura</option><option value="Ticket">Ticket / Guía</option></select>
                  </div>
                  <div class="form-group"><label for="serie_comprobante">Serie</label><input class="form-control" type="text" name="serie_comprobante" id="serie_comprobante" maxlength="7" placeholder="F001"></div>
                  <div class="form-group"><label for="num_comprobante">Número</label><input class="form-control" type="text" name="num_comprobante" id="num_comprobante" maxlength="10" placeholder="Del comprobante"></div>
                  <div class="form-group campo-fecha"><label for="fecha_hora">Fecha y hora <span class="req">*</span></label><input class="form-control" type="datetime-local" name="fecha_hora" id="fecha_hora" required></div>
                  <div class="form-group campo-impuesto"><label for="impuesto">Impuesto %</label><input class="form-control" type="number" step="0.01" min="0" max="100" name="impuesto" id="impuesto" value="0"></div>
                </div>
              </div>
            </div>

            <div class="box caja-detalle">
              <div class="box-body">
                <div class="buscador-articulos">
                  <div class="buscador-input">
                    <i class="fa fa-search"></i>
                    <input type="text" class="form-control input-lg" id="buscarArticulo" placeholder="Escanea el código o escribe el nombre del producto…" autocomplete="off" aria-label="Buscar artículo" aria-autocomplete="list" aria-controls="buscadorResultados">
                    <kbd>F3</kbd>
                  </div>
                  <button id="btnAgregarArt" type="button" class="btn btn-catalog-open btn-lg" data-toggle="modal" data-target="#myModal" title="Abrir catálogo (F2)"><i class="fa fa-th-large"></i> Catálogo</button>
                  <ul class="buscador-resultados" id="buscadorResultados" role="listbox"></ul>
                </div>

                <div class="alert-borrador alert-borrador-sm" id="avisoBorradorForm" style="display:none">
                  <i class="fa fa-history"></i> <span id="avisoBorradorFormTexto"></span>
                  <button type="button" class="btn btn-link btn-xs" onclick="descartarBorrador(true)">Empezar de cero</button>
                </div>

                <div class="detalle-scroll">
                  <table id="detalles" class="table table-hover tabla-detalle-grande">
                    <thead><tr><th style="width:40px">#</th><th class="col-articulo">Artículo</th><th style="width:90px">Unidad</th><th style="width:120px">Cantidad</th><th style="width:130px">P. compra</th><th style="width:130px">P. venta</th><th style="width:130px" class="text-right">Subtotal</th><th style="width:44px"></th></tr></thead>
                    <tbody></tbody>
                  </table>
                  <div class="empty-state" id="detalleVacio"><i class="fa fa-barcode"></i><strong>Aún no hay artículos</strong>Escanea o escribe arriba (F3), o abre el catálogo (F2). Con <kbd>Enter</kbd> pasas de cantidad a precio y vuelves al buscador.</div>
                </div>
                <input type="hidden" name="total_compra" id="total_compra">
              </div>
            </div>
          </div>

          <aside class="pos-side">
            <div class="pos-summary">
              <div class="pos-summary-title">Total de la compra</div>
              <div class="pos-total" id="posTotal"><?php echo e(formatearMoneda(0)); ?></div>
              <div class="pos-row"><span>Artículos</span><span id="comprasItemsSeleccionados">0</span></div>
              <div class="pos-row"><span>Unidades</span><span id="posUnidades">0</span></div>
              <div class="pos-row" id="filaImpuesto" style="display:none"><span>Incluye impuesto</span><span id="posImpuesto">0.00</span></div>

              <div class="pos-bloque">
                <div class="pos-summary-title">Forma de pago</div>
                <div class="pago-toggle">
                  <button type="button" class="btn btn-default btn-sm active" data-pago="CONTADO"><i class="fa fa-money"></i> Contado</button>
                  <button type="button" class="btn btn-default btn-sm" data-pago="CREDITO"><i class="fa fa-calendar"></i> Crédito</button>
                </div>
                <input type="hidden" name="tipo_pago" id="tipo_pago" value="CONTADO">
              </div>

              <div class="pos-bloque" id="grupoMedioPago">
                <div class="pos-summary-title">¿Cómo pagaste?</div>
                <div class="medio-grid" data-target="#medio_pago">
                  <button type="button" class="active" data-medio="EFECTIVO"><i class="fa fa-money"></i>Efectivo</button>
                  <button type="button" data-medio="DEPOSITO"><i class="fa fa-university"></i>Depósito</button>
                  <button type="button" data-medio="TRANSFERENCIA"><i class="fa fa-exchange"></i>Transferencia</button>
                  <button type="button" data-medio="YAPE"><i class="fa fa-mobile"></i>Yape</button>
                  <button type="button" data-medio="PLIN"><i class="fa fa-mobile"></i>Plin</button>
                  <button type="button" data-medio="TARJETA"><i class="fa fa-credit-card"></i>Tarjeta</button>
                </div>
                <input type="hidden" name="medio_pago" id="medio_pago" value="EFECTIVO">
                <div id="grupoCuentaPago" style="display:none">
                  <label for="cuenta_pago">Cuenta o banco del proveedor</label>
                  <input type="text" name="cuenta_pago" id="cuenta_pago" class="form-control input-sm" maxlength="80" placeholder="Ej.: BCP 191-1234567-0-12">
                  <label for="num_operacion">N° de operación</label>
                  <input type="text" name="num_operacion" id="num_operacion" class="form-control input-sm" maxlength="40" placeholder="Del voucher o la app">
                </div>
                <small class="pos-ayuda" id="ayudaCaja">Con caja abierta, el pago se registra como egreso de tu caja.</small>
              </div>

              <div class="pos-bloque" id="grupoVencimiento" style="display:none">
                <label for="fecha_vencimiento">Vencimiento del crédito</label>
                <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control input-sm">
                <small class="pos-ayuda">Se creará una cuenta por pagar automáticamente.</small>
              </div>

              <div class="pos-bloque">
                <label for="observacion">Observación</label>
                <input type="text" name="observacion" id="observacion" class="form-control input-sm" maxlength="200" placeholder="Opcional">
              </div>

              <button class="btn btn-success btn-lg" type="submit" id="btnGuardar"><i class="fa fa-check"></i> Registrar compra <small>(F4)</small></button>
              <button class="btn btn-default" onclick="cancelarform()" type="button" id="btnCancelar"><i class="fa fa-times"></i> Cancelar</button>
              <div class="pos-shortcuts"><kbd>F3</kbd> buscar &nbsp; <kbd>F2</kbd> catálogo &nbsp; <kbd>F4</kbd> guardar &nbsp; <kbd>Esc</kbd> salir</div>
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
<script src="../public/js/app-pos.js?v=<?php echo e(APP_VERSION); ?>"></script>
<script src="scripts/ingreso.js?v=<?php echo e(APP_VERSION); ?>"></script>
