<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Clientes";
$iconoPagina = "fa-users";
$personaTipo = "Cliente";
$personaPermiso = usuarioTienePermiso('ventas');
require 'header.php';
if ($personaPermiso) {
  require '_persona_form.php';
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script>window.appPersonaTipo = "Cliente";</script>
<script src="scripts/persona.js?v=<?php echo e(APP_VERSION); ?>"></script>
