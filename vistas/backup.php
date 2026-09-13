<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Backup y restauración";
$iconoPagina = "fa-database";
require 'header.php';
if (usuarioTienePermiso('backup') || usuarioTienePermiso('acceso')) {
  $esAdmin = usuarioTienePermiso('acceso');
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Administración <i class="fa fa-chevron-right"></i> Backup</div>
        <h1><span class="page-icon"><i class="fa fa-database"></i></span> Backup y restauración</h1>
        <p>Genera copias de seguridad de toda la base de datos y descárgalas. Recomendado: una copia al cierre de cada día.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-success btn-lg" id="btnGenerarBackup"><i class="fa fa-cloud-download"></i> Generar copia ahora</button>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-8 col-md-7">
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-history"></i> Copias generadas</h3></div>
          <div class="box-body table-responsive">
            <table id="tblbackup" class="table table-striped table-bordered table-hover" style="width:100%">
              <thead><tr><th>Acciones</th><th>Tipo</th><th>Archivo</th><th>Tamaño</th><th>Usuario</th><th>Fecha</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-5">
        <?php if ($esAdmin) { ?>
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-upload"></i> Restaurar desde archivo</h3></div>
          <div class="box-body">
            <div class="alert alert-warning" style="font-size:13px"><i class="fa fa-exclamation-triangle"></i> <strong>Cuidado:</strong> restaurar reemplaza TODOS los datos actuales por los del archivo. Antes de hacerlo el sistema genera automáticamente una copia de seguridad previa.</div>
            <form id="formRestaurar" enctype="multipart/form-data">
              <div class="form-group">
                <label>Archivo .sql (máx. 64 MB)</label>
                <input type="file" name="archivo_sql" id="archivo_sql" class="form-control" accept=".sql" required>
              </div>
              <button class="btn btn-danger w-100" type="submit" id="btnRestaurar"><i class="fa fa-refresh"></i> Restaurar base de datos</button>
            </form>
          </div>
        </div>
        <?php } ?>
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-lightbulb-o"></i> Buenas prácticas</h3></div>
          <div class="box-body" style="font-size:13px;color:#475569">
            <ul style="padding-left:18px;margin:0">
              <li>Genera una copia al cerrar caja cada día.</li>
              <li>Descarga el archivo y guárdalo fuera de esta computadora (USB, nube).</li>
              <li>Conserva al menos las últimas 7 copias.</li>
              <li>Prueba restaurar en una instalación de prueba una vez al mes.</li>
            </ul>
          </div>
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
<script src="scripts/backup.js?v=<?php echo e(APP_VERSION); ?>"></script>
