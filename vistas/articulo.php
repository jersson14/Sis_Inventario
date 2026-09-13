<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Artículos";
$iconoPagina = "fa-cube";
require 'header.php';
if (usuarioTienePermiso('almacen')) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Inventario <i class="fa fa-chevron-right"></i> Artículos</div>
        <h1><span class="page-icon"><i class="fa fa-cube"></i></span> Artículos</h1>
        <p>Catálogo de productos con stock, precios, códigos de barras e imagen.</p>
      </div>
      <div class="page-actions">
        <a target="_blank" href="../reportes/rptarticulos.php" class="btn btn-default" title="Reporte PDF del inventario"><i class="fa fa-file-pdf-o"></i> Reporte PDF</a>
        <button class="btn btn-primary" onclick="mostrarform(true)" id="btnagregar"><i class="fa fa-plus"></i> Nuevo artículo</button>
      </div>
    </div>

    <div class="box" id="listadoregistros">
      <div class="box-body table-responsive">
        <table id="tbllistado" class="table table-striped table-bordered table-hover" style="width:100%">
          <thead>
            <tr>
              <th>Opciones</th>
              <th>Nombre</th>
              <th>Categoría</th>
              <th>Unidad</th>
              <th>Código</th>
              <th>Stock</th>
              <th>Mínimo</th>
              <th>P. Compra</th>
              <th>P. Venta</th>
              <th>Imagen</th>
              <th>Descripción</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <div class="box" id="formularioregistros">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-pencil"></i> <span id="formTitulo">Nuevo artículo</span></h3>
        <div class="box-tools">
          <button class="btn btn-default btn-sm" onclick="cancelarform()" type="button"><i class="fa fa-arrow-left"></i> Volver al listado</button>
        </div>
      </div>
      <div class="box-body">
        <form action="" name="formulario" id="formulario" method="POST" autocomplete="off">
          <input type="hidden" name="idarticulo" id="idarticulo">
          <div class="form-section-title">Datos generales</div>
          <div class="form-group col-lg-5 col-md-6 col-xs-12">
            <label for="nombre">Nombre <span class="req">*</span></label>
            <input class="form-control" type="text" name="nombre" id="nombre" maxlength="100" placeholder="Ej. Perno hexagonal 1/2 x 2" required>
          </div>
          <div class="form-group col-lg-4 col-md-6 col-xs-12">
            <label for="idcategoria">Categoría <span class="req">*</span></label>
            <select name="idcategoria" id="idcategoria" class="form-control selectpicker" data-live-search="true" data-width="100%" required></select>
          </div>
          <div class="form-group col-lg-3 col-md-6 col-xs-12">
            <label for="idunidad">Unidad de medida <span class="req">*</span></label>
            <select name="idunidad" id="idunidad" class="form-control selectpicker" data-live-search="true" data-width="100%" required></select>
          </div>
          <div class="form-group col-lg-12 col-md-12 col-xs-12">
            <label for="descripcion">Descripción</label>
            <input class="form-control" type="text" name="descripcion" id="descripcion" maxlength="256" placeholder="Detalles, marca, presentación…">
          </div>

          <div class="form-section-title">Stock y precios</div>
          <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
            <label for="stock">Stock actual</label>
            <input class="form-control" type="number" step="1" min="0" name="stock" id="stock" value="0" required>
            <span class="help-block" id="stockAyuda">Al editar, usa <a href="inventario.php">Ajustes de inventario</a> para mover stock con trazabilidad.</span>
          </div>
          <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
            <label for="stock_minimo">Stock mínimo</label>
            <input class="form-control" type="number" step="1" min="0" name="stock_minimo" id="stock_minimo" value="1" required>
            <span class="help-block">Se alerta cuando el stock baja de aquí.</span>
          </div>
          <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
            <label for="precio_compra">Precio de compra</label>
            <div class="input-group">
              <span class="input-group-addon"><?php echo e(obtenerSimboloMoneda()); ?></span>
              <input class="form-control" type="number" step="0.01" min="0" name="precio_compra" id="precio_compra" value="0.00">
            </div>
          </div>
          <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
            <label for="precio_venta">Precio de venta</label>
            <div class="input-group">
              <span class="input-group-addon"><?php echo e(obtenerSimboloMoneda()); ?></span>
              <input class="form-control" type="number" step="0.01" min="0" name="precio_venta" id="precio_venta" value="0.00">
            </div>
            <span class="help-block" id="margenAyuda">Margen: —</span>
          </div>

          <div class="form-section-title">Código de barras e imagen</div>
          <div class="form-group col-lg-6 col-md-6 col-xs-12">
            <label for="codigo">Código <span class="req">*</span></label>
            <div class="input-group">
              <input class="form-control" type="text" name="codigo" id="codigo" placeholder="Escanea o genera un código" maxlength="50" required>
              <span class="input-group-btn">
                <button class="btn btn-default" type="button" onclick="generarCodigoArticulo()" title="Generar un código automático"><i class="fa fa-magic"></i> Generar</button>
                <button class="btn btn-default" type="button" onclick="generarbarcode()" title="Previsualizar código de barras"><i class="fa fa-barcode"></i></button>
                <button class="btn btn-default" type="button" onclick="imprimir()" title="Imprimir etiqueta"><i class="fa fa-print"></i></button>
              </span>
            </div>
            <div id="print" class="mt-10" style="background:#fff;display:inline-block;padding:6px;border:1px dashed #cbd5e1;border-radius:8px;">
              <svg id="barcode"></svg>
            </div>
          </div>
          <div class="form-group col-lg-6 col-md-6 col-xs-12">
            <label for="imagen">Imagen (JPG, PNG, WEBP · máx. 3 MB)</label>
            <input class="form-control" type="file" name="imagen" id="imagen" accept="image/*">
            <input type="hidden" name="imagenactual" id="imagenactual">
            <img src="" alt="" class="img-preview" id="imagenmuestra">
          </div>

          <div class="form-group col-xs-12 form-actions-row">
            <button class="btn btn-primary" type="submit" id="btnGuardar"><i class="fa fa-save"></i> Guardar artículo</button>
            <button class="btn btn-default" onclick="cancelarform()" type="button"><i class="fa fa-times"></i> Cancelar</button>
          </div>
        </form>
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
<script src="../public/js/jquery.PrintArea.js"></script>
<script src="scripts/articulo.js?v=<?php echo e(APP_VERSION); ?>"></script>
