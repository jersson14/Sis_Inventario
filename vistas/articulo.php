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
<?php if (negocioTiene('temporada')): ?>
          <div class="form-group col-lg-6 col-md-6 col-xs-12">
            <label for="temporada">Temporada</label>
            <input class="form-control" type="text" name="temporada" id="temporada" maxlength="40" placeholder="Ej. Verano 2026" list="listaTemporadas">
            <datalist id="listaTemporadas">
<?php foreach (dbAll("SELECT DISTINCT temporada FROM articulo WHERE temporada IS NOT NULL AND temporada<>'' ORDER BY temporada") as $tp): ?>
              <option value="<?php echo e(html_entity_decode($tp['temporada'], ENT_QUOTES, 'UTF-8')); ?>">
<?php endforeach; ?>
            </datalist>
          </div>
          <div class="form-group col-lg-6 col-md-6 col-xs-12">
            <label for="coleccion">Colección / marca</label>
            <input class="form-control" type="text" name="coleccion" id="coleccion" maxlength="40" placeholder="Ej. Casual, Escolar">
          </div>
<?php endif; ?>
          <div class="form-group col-lg-12 col-md-12 col-xs-12">
            <label for="descripcion">Descripción</label>
            <input class="form-control" type="text" name="descripcion" id="descripcion" maxlength="256" placeholder="Detalles, marca, presentación…">
          </div>

          <div class="form-section-title">Stock y precios</div>
          <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
            <label for="stock">Stock actual</label>
            <input class="form-control input-cantidad" type="number" step="1" min="0" name="stock" id="stock" value="0" required>
            <span class="help-block" id="stockAyuda">Al editar, usa <a href="inventario.php">Ajustes de inventario</a> para mover stock con trazabilidad.</span>
          </div>
          <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
            <label for="stock_minimo">Stock mínimo</label>
            <input class="form-control input-cantidad" type="number" step="1" min="0" name="stock_minimo" id="stock_minimo" value="1" required>
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

<?php if (negocioTiene('variantes')): ?>
          <div class="form-section-title">Tallas y colores</div>
          <input type="hidden" name="var_enviadas" value="1">
          <div class="col-xs-12 bloque-editable">
            <p class="text-soft" style="margin-top:0">Cada combinación lleva su propio stock y código de barras. El stock del artículo es la suma de todas. El stock inicial solo se indica al crear la combinación; después se mueve con compras, ventas y ajustes.</p>
            <div class="generador-variantes">
              <div class="form-group"><label for="gen_tallas">Tallas</label><input type="text" class="form-control" id="gen_tallas" placeholder="S, M, L, XL"></div>
              <div class="form-group"><label for="gen_colores">Colores</label><input type="text" class="form-control" id="gen_colores" placeholder="Negro, Blanco, Azul"></div>
              <button type="button" class="btn btn-default" onclick="generarVariantes()" title="Crear todas las combinaciones de tallas y colores"><i class="fa fa-th"></i> Generar combinaciones</button>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered tabla-editable" id="tblVariantes">
                <thead><tr><th style="width:110px">Talla</th><th style="width:150px">Color</th><th>Código de barras</th><th style="width:120px">Stock</th><th style="width:100px">Mínimo</th><th style="width:120px">Precio</th><th style="width:48px"></th></tr></thead>
                <tbody></tbody>
                <tfoot><tr><th colspan="3" class="text-right">Stock total</th><th id="varStockTotal">0</th><th colspan="3"></th></tr></tfoot>
              </table>
            </div>
            <button type="button" class="btn btn-default btn-sm" onclick="agregarVariante()" title="Agregar una talla/color"><i class="fa fa-plus"></i> Agregar talla/color</button>
            <span class="help-block">Precio en 0 = usa el precio de venta del artículo.</span>
          </div>
<?php endif; ?>
<?php if (negocioTiene('equivalencias')): ?>
          <div class="form-section-title">Presentaciones y empaques</div>
          <input type="hidden" name="pres_enviadas" value="1">
          <div class="col-xs-12 bloque-editable">
            <p class="text-soft" style="margin-top:0">Formas de vender o comprar este artículo además de la unidad base. Ej.: <strong>Caja x100</strong> contiene 100 <span class="unidad-base-txt">unidades</span>. El stock siempre se lleva en la unidad base.</p>
            <div class="table-responsive">
              <table class="table table-bordered tabla-editable" id="tblPresentaciones">
                <thead><tr><th>Nombre</th><th style="width:130px">Contiene (<span class="unidad-base-txt">und</span>)</th><th style="width:130px">Precio venta</th><th style="width:130px">Precio compra</th><th style="width:170px">Código de barras</th><th style="width:48px"></th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
            <button type="button" class="btn btn-default btn-sm" onclick="agregarPresentacion()" title="Agregar una presentación"><i class="fa fa-plus"></i> Agregar presentación</button>
          </div>
<?php endif; ?>
<?php if (negocioTiene('vencimientos') || negocioTiene('lotes')): ?>
          <div class="form-section-title" id="tituloLotes" style="display:none">Lotes en stock</div>
          <div class="col-xs-12 bloque-editable" id="bloqueLotes" style="display:none">
            <p class="text-soft" style="margin-top:0">Se crean al comprar o al registrar una entrada con fecha de vencimiento. Salen primero los que vencen antes. Para corregirlos usa <a href="vencimientos.php">Vencimientos</a> o <a href="inventario.php">Ajustes de inventario</a>.</p>
            <div class="table-responsive">
              <table class="table table-bordered table-condensed" id="tblLotesArticulo" style="max-width:720px">
                <thead><tr><th>Lote</th><th>Vence</th><th>Estado</th><th class="text-right">Stock</th><th class="text-right">Ingresó</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
            <small class="text-soft" id="lotesSinLote"></small>
          </div>
<?php endif; ?>
<?php if (negocioTiene('precio_mayor')): ?>
          <div class="form-section-title">Precio por mayor</div>
          <input type="hidden" name="escalas_enviadas" value="1">
          <div class="col-xs-12 bloque-editable">
            <p class="text-soft" style="margin-top:0">El punto de venta aplica el precio automáticamente cuando la cantidad llega al mínimo. Siempre en <span class="unidad-base-txt">unidades</span> base; las presentaciones usan su propio precio.</p>
            <div class="table-responsive">
              <table class="table table-bordered tabla-editable" id="tblEscalas" style="max-width:520px">
                <thead><tr><th>Desde (<span class="unidad-base-txt">und</span>)</th><th>Precio unitario</th><th style="width:48px"></th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
            <button type="button" class="btn btn-default btn-sm" onclick="agregarEscala()" title="Agregar un precio por mayor"><i class="fa fa-plus"></i> Agregar precio por mayor</button>
          </div>
<?php endif; ?>

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
