<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
}else{

require 'header.php';

if ($_SESSION['almacen']==1) {
 ?>
  <div class="content-wrapper">
    <section class="content">
      <div class="row">
        <div class="col-md-12">
          <div class="box">
            <div class="box-header with-border">
              <h1 class="box-title">Unidades de medida <button class="btn btn-success" onclick="mostrarform(true)"><i class="fa fa-plus-circle"></i> Agregar</button></h1>
            </div>

            <div class="panel-body table-responsive" id="listadoregistros">
              <table id="tbllistado" class="table table-striped table-bordered table-condensed table-hover">
                <thead>
                  <th>Opciones</th>
                  <th>Nombre</th>
                  <th>Abrev.</th>
                  <th>Descripcion</th>
                  <th>Estado</th>
                </thead>
                <tbody></tbody>
                <tfoot>
                  <th>Opciones</th>
                  <th>Nombre</th>
                  <th>Abrev.</th>
                  <th>Descripcion</th>
                  <th>Estado</th>
                </tfoot>
              </table>
            </div>

            <div class="panel-body" id="formularioregistros">
              <form name="formulario" id="formulario" method="POST">
                <div class="form-group col-lg-4 col-md-4 col-xs-12">
                  <label>Nombre(*)</label>
                  <input class="form-control" type="hidden" name="idunidad" id="idunidad">
                  <input class="form-control" type="text" name="nombre" id="nombre" maxlength="60" placeholder="Ejemplo: Kilogramo" required>
                </div>
                <div class="form-group col-lg-2 col-md-2 col-xs-12">
                  <label>Abreviatura(*)</label>
                  <input class="form-control" type="text" name="abreviatura" id="abreviatura" maxlength="10" placeholder="kg" required>
                </div>
                <div class="form-group col-lg-6 col-md-6 col-xs-12">
                  <label>Descripcion</label>
                  <input class="form-control" type="text" name="descripcion" id="descripcion" maxlength="120" placeholder="Uso interno de la unidad">
                </div>
                <div class="form-group col-lg-12 col-md-12 col-sm-12 col-xs-12">
                  <button class="btn btn-primary" type="submit" id="btnGuardar"><i class="fa fa-save"></i> Guardar</button>
                  <button class="btn btn-danger" onclick="cancelarform()" type="button"><i class="fa fa-arrow-circle-left"></i> Cancelar</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
<?php
}else{
  require 'noacceso.php';
}

require 'footer.php';
?>
<script src="scripts/unidad.js"></script>
<?php
}

ob_end_flush();
?>
