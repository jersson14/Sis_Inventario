<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
  header("Location: login.html");
}else{

require 'header.php';
if ($_SESSION['acceso']==1) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box">
          <div class="box-header with-border">
            <h1 class="box-title">Backup y Restauracion</h1>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-6">
                <h4>Generar backup</h4>
                <button class="btn btn-success" id="btnGenerarBackup"><i class="fa fa-database"></i> Generar copia ahora</button>
              </div>
              <div class="col-md-6">
                <h4>Restaurar desde archivo SQL</h4>
                <form id="formRestaurar" enctype="multipart/form-data">
                  <div class="input-group">
                    <input type="file" name="archivo_sql" id="archivo_sql" class="form-control" accept=".sql" required>
                    <span class="input-group-btn">
                      <button class="btn btn-danger" type="submit"><i class="fa fa-upload"></i> Restaurar</button>
                    </span>
                  </div>
                </form>
              </div>
            </div>
            <hr>
            <table id="tblbackup" class="table table-striped table-bordered table-condensed table-hover">
              <thead><th>Descargar</th><th>Tipo</th><th>Archivo</th><th>Tamano</th><th>Usuario</th><th>Fecha</th></thead>
              <tbody></tbody>
            </table>
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
<script src="scripts/backup.js"></script>
<?php
}
ob_end_flush();
?>
