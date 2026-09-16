<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Unidades de medida";
$iconoPagina = "fa-balance-scale";
require 'header.php';
if (usuarioTienePermiso('almacen')) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Inventario <i class="fa fa-chevron-right"></i> Unidades</div>
        <h1><span class="page-icon"><i class="fa fa-balance-scale"></i></span> Unidades de medida</h1>
        <p>Unidad, kilo, litro, caja, paquete… define cómo cuentas cada artículo.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" onclick="mostrarform(true)" id="btnagregar"><i class="fa fa-plus"></i> Nueva unidad</button>
      </div>
    </div>

    <div class="box" id="listadoregistros">
      <div class="box-body table-responsive">
        <table id="tbllistado" class="table table-striped table-bordered table-hover" style="width:100%">
          <thead><tr><th>Opciones</th><th>Nombre</th><th>Abreviatura</th><th>Descripción</th><th>Decimales</th><th>Estado</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <div class="box" id="formularioregistros">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-pencil"></i> <span id="formTitulo">Nueva unidad</span></h3>
        <div class="box-tools"><button class="btn btn-default btn-sm" onclick="cancelarform()" type="button"><i class="fa fa-arrow-left"></i> Volver al listado</button></div>
      </div>
      <div class="box-body">
        <form action="" name="formulario" id="formulario" method="POST" autocomplete="off">
          <input type="hidden" name="idunidad" id="idunidad">
          <div class="form-group col-lg-4 col-md-5 col-xs-12">
            <label for="nombre">Nombre <span class="req">*</span></label>
            <input class="form-control" type="text" name="nombre" id="nombre" maxlength="60" placeholder="Ej. Kilogramo" required>
          </div>
          <div class="form-group col-lg-2 col-md-3 col-xs-12">
            <label for="abreviatura">Abreviatura <span class="req">*</span></label>
            <input class="form-control" type="text" name="abreviatura" id="abreviatura" maxlength="10" placeholder="kg" required>
          </div>
          <div class="form-group col-lg-6 col-md-4 col-xs-12">
            <label for="descripcion">Descripción</label>
            <input class="form-control" type="text" name="descripcion" id="descripcion" maxlength="120" placeholder="Opcional">
          </div>
          <div class="form-group col-xs-12">
            <label class="checkbox-inline" style="font-weight:600">
              <input type="checkbox" name="permite_fraccion" id="permite_fraccion" value="1">
              Permite cantidades con decimales
            </label>
            <p class="help-block" style="margin:4px 0 0">Márcalo para unidades que se venden fraccionadas: metros, kilos, litros (1.5 m, 0.75 kg). Déjalo sin marcar para unidades, cajas o paquetes.<?php if (!negocioTiene('fracciones')): ?> <strong>Tu tipo de negocio actual no usa fracciones</strong>; este ajuste se aplicará si cambias a un rubro que las use.<?php endif; ?></p>
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
<script src="scripts/unidad.js?v=<?php echo e(APP_VERSION); ?>"></script>
