<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Permisos";
$iconoPagina = "fa-key";
require 'header.php';
if (usuarioTienePermiso('acceso')) {
 ?>
    <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">

      <!-- Default box -->
      <div class="row">
        <div class="col-md-12">
      <div class="box">
<div class="box-header with-border">
  <h1 class="box-title">Permisos <button id="btnagregar" class="btn btn-success" onclick="mostrarform(true)"><i class="fa fa-plus-circle"></i>Agregar</button></h1>
  <div class="box-tools pull-right">
    
  </div>
</div>
<!--box-header-->
<!--centro-->
<div class="panel-body table-responsive" id="listadoregistros">
  <table id="tbllistado" class="table table-striped table-bordered table-condensed table-hover">
    <thead>
     <th>Nombre</th>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
      <th>Nombre</th>

    </tfoot>   
  </table>
</div>
<!--fin centro-->
      </div>
      </div>
      </div>
      <!-- /.box -->

    </section>
    <!-- /.content -->
  </div>
<?php 
}else{
 require 'noacceso.php'; 
}
require 'footer.php'
 ?>
 <script src="scripts/permiso.js?v=<?php echo e(APP_VERSION); ?>"></script>
 