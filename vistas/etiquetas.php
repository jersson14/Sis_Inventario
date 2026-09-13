<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Etiquetas de código de barras";
$iconoPagina = "fa-barcode";
require 'header.php';
if (usuarioTienePermiso('almacen')) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Inventario <i class="fa fa-chevron-right"></i> Etiquetas</div>
        <h1><span class="page-icon"><i class="fa fa-barcode"></i></span> Impresión de etiquetas</h1>
        <p>Selecciona artículos, indica cuántas etiquetas necesitas de cada uno y genera la hoja para imprimir.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" id="btnGenerar"><i class="fa fa-print"></i> Generar e imprimir</button>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-8 col-md-7">
        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-cubes"></i> Artículos</h3>
            <div class="box-tools">
              <button class="btn btn-default btn-xs" id="btnTodos"><i class="fa fa-check-square-o"></i> Marcar todos</button>
              <button class="btn btn-default btn-xs" id="btnNinguno"><i class="fa fa-square-o"></i> Ninguno</button>
              <button class="btn btn-default btn-xs" id="btnBajoMinimo" title="Marcar los que están bajo el stock mínimo"><i class="fa fa-exclamation-triangle"></i> Bajo mínimo</button>
            </div>
          </div>
          <div class="box-body table-responsive">
            <table id="tblarticulos" class="table table-striped table-hover" style="width:100%">
              <thead><tr><th style="width:36px"></th><th>Artículo</th><th>Código</th><th>Precio</th><th>Stock</th><th style="width:110px">Etiquetas</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-5">
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-sliders"></i> Formato</h3></div>
          <div class="box-body">
            <div class="form-group"><label>Tamaño de etiqueta</label>
              <select id="opTamano" class="form-control">
                <option value="peq">Pequeña (50 × 25 mm)</option>
                <option value="med" selected>Mediana (60 × 35 mm)</option>
                <option value="gra">Grande (80 × 45 mm)</option>
              </select>
            </div>
            <div class="form-group"><label>Contenido</label>
              <label class="login-remember" style="display:flex;margin-bottom:6px"><input type="checkbox" id="opNombre" checked> <span>Nombre del artículo</span></label>
              <label class="login-remember" style="display:flex;margin-bottom:6px"><input type="checkbox" id="opPrecio" checked> <span>Precio de venta</span></label>
              <label class="login-remember" style="display:flex;margin-bottom:6px"><input type="checkbox" id="opEmpresa" checked> <span>Nombre de la empresa</span></label>
            </div>
            <div class="form-group"><label>Cantidad por defecto</label><input type="number" id="opCantidad" class="form-control" value="1" min="1" max="500"></div>
            <div class="alert alert-info mb-0" style="font-size:13px"><i class="fa fa-info-circle"></i> Se abrirá una vista de impresión. Usa papel adhesivo A4 o una impresora de etiquetas y ajusta márgenes a 0.</div>
          </div>
        </div>
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-eye"></i> Vista previa</h3></div>
          <div class="box-body" style="text-align:center"><div id="preview"><span class="text-soft">Marca un artículo para ver la vista previa.</span></div></div>
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
<script src="../public/js/JsBarcode.all.min.js"></script>
<script>window.appEmpresaNombre = <?php echo json_encode(isset($brandNombre) ? $brandNombre : PRO_NOMBRE); ?>; window.appSimbolo = <?php echo json_encode(obtenerSimboloMoneda()); ?>;</script>
<script src="scripts/etiquetas.js?v=<?php echo e(APP_VERSION); ?>"></script>
