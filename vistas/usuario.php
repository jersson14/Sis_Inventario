<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$modoPerfil = (isset($_GET['perfil']) && $_GET['perfil'] == '1');
$idPerfilSolicitado = isset($_GET['idusuario']) ? (int)$_GET['idusuario'] : 0;
$idSesion = (int)$_SESSION['idusuario'];
$perfilPropio = $modoPerfil && ($idPerfilSolicitado === $idSesion);
$esAdmin = usuarioTienePermiso('acceso');
$tituloPagina = $perfilPropio ? "Mi perfil" : "Usuarios";
$iconoPagina = $perfilPropio ? "fa-user" : "fa-users";
require 'header.php';
if ($esAdmin || $perfilPropio) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> <?php echo $perfilPropio ? 'Mi perfil' : 'Administración <i class="fa fa-chevron-right"></i> Usuarios'; ?></div>
        <h1><span class="page-icon"><i class="fa <?php echo $iconoPagina; ?>"></i></span> <?php echo $perfilPropio ? 'Mi perfil' : 'Usuarios y permisos'; ?></h1>
        <p><?php echo $perfilPropio ? 'Actualiza tus datos personales, tu foto y tu contraseña.' : 'Crea cuentas para tu equipo y define qué módulos puede usar cada uno.'; ?></p>
      </div>
      <?php if (!$perfilPropio) { ?>
      <div class="page-actions">
        <button class="btn btn-primary" onclick="mostrarform(true)" id="btnagregar"><i class="fa fa-user-plus"></i> Nuevo usuario</button>
      </div>
      <?php } ?>
    </div>

    <?php if (!$perfilPropio) { ?>
    <div class="box" id="listadoregistros">
      <div class="box-body table-responsive">
        <table id="tbllistado" class="table table-striped table-bordered table-hover" style="width:100%">
          <thead><tr><th>Opciones</th><th>Nombre</th><th>Tipo doc.</th><th>N° doc.</th><th>Teléfono</th><th>Email</th><th>Usuario</th><th>Cargo</th><th>Foto</th><th>Último acceso</th><th>Estado</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
    <?php } ?>

    <div class="row" id="formularioregistros">
      <div class="<?php echo $perfilPropio ? 'col-lg-8 col-md-7' : 'col-xs-12'; ?>">
        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-pencil"></i> <span id="formTitulo"><?php echo $perfilPropio ? 'Mis datos' : 'Nuevo usuario'; ?></span></h3>
            <?php if (!$perfilPropio) { ?><div class="box-tools"><button class="btn btn-default btn-sm" onclick="cancelarform()" type="button"><i class="fa fa-arrow-left"></i> Volver al listado</button></div><?php } ?>
          </div>
          <div class="box-body">
            <form action="" name="formulario" id="formulario" method="POST" autocomplete="off">
              <input type="hidden" name="idusuario" id="idusuario">
              <div class="form-section-title">Datos personales</div>
              <div class="form-group col-lg-6 col-md-6 col-xs-12">
                <label for="nombre">Nombre completo <span class="req">*</span></label>
                <input class="form-control" type="text" name="nombre" id="nombre" maxlength="100" required>
              </div>
              <div class="form-group col-lg-3 col-md-3 col-xs-12">
                <label for="tipo_documento">Tipo de documento</label>
                <select name="tipo_documento" id="tipo_documento" class="form-control"><option value="DNI">DNI</option><option value="RUC">RUC</option><option value="CEDULA">Cédula</option><option value="PASAPORTE">Pasaporte</option><option value="OTRO">Otro</option></select>
              </div>
              <div class="form-group col-lg-3 col-md-3 col-xs-12">
                <label for="num_documento">N° de documento</label>
                <input type="text" class="form-control" name="num_documento" id="num_documento" maxlength="20">
              </div>
              <div class="form-group col-lg-6 col-md-6 col-xs-12"><label for="direccion">Dirección</label><input class="form-control" type="text" name="direccion" id="direccion" maxlength="70"></div>
              <div class="form-group col-lg-3 col-md-3 col-xs-12"><label for="telefono">Teléfono</label><input class="form-control" type="text" name="telefono" id="telefono" maxlength="20"></div>
              <div class="form-group col-lg-3 col-md-3 col-xs-12"><label for="email">Email</label><input class="form-control" type="email" name="email" id="email" maxlength="70"></div>

              <div class="form-section-title">Acceso al sistema</div>
              <div class="form-group col-lg-4 col-md-4 col-xs-12">
                <label for="cargo">Cargo</label>
                <input class="form-control" type="text" name="cargo" id="cargo" maxlength="20" placeholder="Ej. Vendedor">
              </div>
<?php require_once "../modelos/Stock.php"; if ($esAdmin && !$perfilPropio && Stock::multiAlmacen()) { ?>
              <div class="form-group col-lg-4 col-md-4 col-xs-12 pull-right">
                <label for="idalmacen_usuario"><i class="fa fa-building-o"></i> Almacén donde trabaja</label>
                <select class="form-control" name="idalmacen" id="idalmacen_usuario">
                  <?php foreach (Stock::almacenes() as $alU) { ?><option value="<?php echo (int)$alU['idalmacen']; ?>"<?php echo (int)$alU['principal'] === 1 ? ' data-principal="1"' : ''; ?>><?php echo e(html_entity_decode($alU['nombre'], ENT_QUOTES, 'UTF-8')); ?></option><?php } ?>
                </select>
                <span class="help-block">Vende, compra y ajusta stock en este almacén al iniciar sesión.</span>
              </div>
<?php } ?>
              <div class="form-group col-lg-4 col-md-4 col-xs-12">
                <label for="login">Usuario (login) <span class="req">*</span></label>
                <input class="form-control" type="text" name="login" id="login" maxlength="20" placeholder="nombre de usuario" required <?php echo $perfilPropio && !$esAdmin ? 'readonly' : ''; ?>>
              </div>
              <div class="form-group col-lg-4 col-md-4 col-xs-12">
                <label for="clave">Contraseña <span id="claveReq" class="req">*</span></label>
                <input class="form-control" type="password" name="clave" id="clave" maxlength="64" placeholder="Mín. 8 caracteres, letras y números" autocomplete="new-password">
                <span class="help-block" id="claveAyuda">Déjala vacía para no cambiarla.</span>
              </div>
              <div class="form-group col-lg-6 col-md-6 col-xs-12">
                <label>Foto</label>
                <input class="form-control" type="file" name="imagen" id="imagen" accept="image/*">
                <input type="hidden" name="imagenactual" id="imagenactual">
                <img src="" alt="" class="img-preview" id="imagenmuestra">
              </div>
              <?php if ($esAdmin && !$perfilPropio) { ?>
              <div class="form-group col-xs-12">
                <label>Rol <small class="text-soft">(marca los permisos típicos del puesto; después puedes ajustarlos uno por uno)</small></label>
                <div class="rol-grid" id="rolPlantillas" role="group" aria-label="Plantillas de rol">
                  <?php foreach (plantillasRol() as $claveRol => $rol) { ?>
                  <button type="button" class="rol-card" data-rol="<?php echo e($claveRol); ?>" aria-pressed="false">
                    <i class="fa <?php echo e($rol['icono']); ?>"></i>
                    <strong><?php echo e($rol['nombre']); ?></strong>
                    <small><?php echo e($rol['descripcion']); ?></small>
                  </button>
                  <?php } ?>
                </div>
              </div>
              <?php } ?>
              <div class="form-group col-xs-12">
                <label>Permisos por módulo <?php echo $esAdmin ? '' : '<small class="text-soft">(solo un administrador puede cambiarlos)</small>'; ?></label>
                <ul id="permisos" class="permisos-list permisos-detalle"></ul>
              </div>
              <div class="form-group col-xs-12 form-actions-row">
                <button class="btn btn-primary" type="submit" id="btnGuardar"><i class="fa fa-save"></i> Guardar</button>
                <?php if (!$perfilPropio) { ?><button class="btn btn-default" onclick="cancelarform()" type="button"><i class="fa fa-times"></i> Cancelar</button><?php } ?>
              </div>
            </form>
          </div>
        </div>
      </div>

      <?php if ($perfilPropio) { ?>
      <div class="col-lg-4 col-md-5">
        <div class="box" id="cambiarClave">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-key"></i> Cambiar contraseña</h3></div>
          <div class="box-body">
            <form id="formClave" autocomplete="off">
              <div class="form-group"><label>Contraseña actual</label><input type="password" class="form-control" id="clave_actual" name="clave_actual" required autocomplete="current-password"></div>
              <div class="form-group"><label>Nueva contraseña</label><input type="password" class="form-control" id="clave_nueva" name="clave_nueva" required autocomplete="new-password" placeholder="Mín. 8 caracteres, letras y números"></div>
              <div class="form-group"><label>Confirmar nueva contraseña</label><input type="password" class="form-control" id="clave_confirma" name="clave_confirma" required autocomplete="new-password"></div>
              <button class="btn btn-warning w-100" type="submit" id="btnCambiarClave"><i class="fa fa-key"></i> Actualizar contraseña</button>
            </form>
          </div>
        </div>
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-info-circle"></i> Mi cuenta</h3></div>
          <div class="box-body" id="resumenPerfil"><div class="skeleton" style="height:60px"></div></div>
        </div>
      </div>
      <?php } ?>
    </div>
  </section>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script>
window.appPerfilMode = <?php echo $perfilPropio ? 'true' : 'false'; ?>;
window.appPerfilId = <?php echo $perfilPropio ? $idSesion : 'null'; ?>;
window.appEsAdmin = <?php echo $esAdmin ? 'true' : 'false'; ?>;
// Plantillas de rol: cargo sugerido e ids de permiso que marcan
window.appPlantillasRol = <?php
  $mapaIds = mapaPermisos();
  $plantillasJs = array();
  foreach (plantillasRol() as $claveRol => $rol) {
    $plantillasJs[$claveRol] = array('cargo' => $rol['cargo'], 'permisos' => array_map(function ($p) use ($mapaIds) { return $mapaIds[$p]; }, $rol['permisos']));
  }
  echo json_encode($plantillasJs, JSON_UNESCAPED_UNICODE);
?>;
</script>
<script src="scripts/usuario.js?v=<?php echo e(APP_VERSION); ?>"></script>
