<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Categorías";
$iconoPagina = "fa-tags";
require 'header.php';
if (usuarioTienePermiso('almacen')) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Inventario <i class="fa fa-chevron-right"></i> Categorías</div>
        <h1><span class="page-icon"><i class="fa fa-tags"></i></span> Categorías</h1>
        <p>Agrupa tus artículos para buscarlos y analizarlos más rápido.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" onclick="mostrarform(true)" id="btnagregar"><i class="fa fa-plus"></i> Nueva categoría</button>
      </div>
    </div>

    <div class="box" id="listadoregistros">
      <div class="box-body table-responsive">
        <table id="tbllistado" class="table table-striped table-bordered table-hover" style="width:100%">
          <thead><tr><th>Opciones</th><th>Nombre</th><th>Descripción</th><th>Estado</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <div class="box" id="formularioregistros">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-pencil"></i> <span id="formTitulo">Nueva categoría</span></h3>
        <div class="box-tools"><button class="btn btn-default btn-sm" onclick="cancelarform()" type="button"><i class="fa fa-arrow-left"></i> Volver al listado</button></div>
      </div>
      <div class="box-body">
        <form action="" name="formulario" id="formulario" method="POST" autocomplete="off">
          <input type="hidden" name="idcategoria" id="idcategoria">
          <div class="form-group col-lg-4 col-md-6 col-xs-12">
            <label for="nombre">Nombre <span class="req">*</span></label>
            <input class="form-control" type="text" name="nombre" id="nombre" maxlength="50" placeholder="Ej. Pernos y tuercas" required>
          </div>
          <div class="form-group col-lg-8 col-md-6 col-xs-12">
            <label for="descripcion">Descripción</label>
            <input class="form-control" type="text" name="descripcion" id="descripcion" maxlength="256" placeholder="Opcional">
          </div>
          <div class="form-group col-xs-12 form-actions-row">
            <button class="btn btn-primary" type="submit" id="btnGuardar"><i class="fa fa-save"></i> Guardar</button>
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
<script src="scripts/categoria.js?v=<?php echo e(APP_VERSION); ?>"></script>
