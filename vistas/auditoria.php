<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Auditoría";
$iconoPagina = "fa-shield";
require 'header.php';
if (usuarioTienePermiso('acceso')) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Administración <i class="fa fa-chevron-right"></i> Auditoría</div>
        <h1><span class="page-icon"><i class="fa fa-shield"></i></span> Auditoría del sistema</h1>
        <p>Registro de quién hizo qué y cuándo: ventas, anulaciones, cambios de usuarios, configuración, backups e inicios de sesión.</p>
      </div>
    </div>

    <div class="box">
      <div class="box-body">
        <ul class="nav nav-tabs" role="tablist">
          <li role="presentation" class="active"><a href="#tabAcciones" role="tab" data-toggle="tab"><i class="fa fa-list"></i> Acciones</a></li>
          <li role="presentation"><a href="#tabIntentos" role="tab" data-toggle="tab"><i class="fa fa-sign-in"></i> Intentos de acceso</a></li>
        </ul>
        <div class="tab-content">
          <div role="tabpanel" class="tab-pane active" id="tabAcciones">
            <div class="table-toolbar">
              <div class="form-group"><label>Desde</label><input type="date" id="f_inicio" class="form-control input-sm" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>"></div>
              <div class="form-group"><label>Hasta</label><input type="date" id="f_fin" class="form-control input-sm" value="<?php echo date('Y-m-d'); ?>"></div>
              <div class="form-group"><label>Módulo</label><select id="f_modulo" class="form-control input-sm"><option value="">Todos</option></select></div>
              <div class="form-group"><label>Usuario</label><select id="f_usuario" class="form-control input-sm"><option value="">Todos</option></select></div>
              <div class="toolbar-actions"><button type="button" class="btn btn-primary btn-sm" id="btnFiltrar"><i class="fa fa-filter"></i> Filtrar</button></div>
            </div>
            <div class="table-responsive">
              <table id="tblauditoria" class="table table-striped table-bordered table-hover" style="width:100%">
                <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
          <div role="tabpanel" class="tab-pane" id="tabIntentos">
            <p class="text-soft">Últimos 100 intentos de inicio de sesión. Tras <?php echo (int)LOGIN_MAX_INTENTOS; ?> fallos en <?php echo (int)LOGIN_BLOQUEO_MINUTOS; ?> minutos el usuario o la IP quedan bloqueados temporalmente.</p>
            <div class="table-responsive">
              <table id="tblintentos" class="table table-striped table-bordered table-hover" style="width:100%">
                <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>IP</th><th>Resultado</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
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
<script src="scripts/auditoria.js?v=<?php echo e(APP_VERSION); ?>"></script>
