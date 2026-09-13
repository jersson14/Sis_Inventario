<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Proveedores";
$iconoPagina = "fa-truck";
$personaTipo = "Proveedor";
$personaPermiso = usuarioTienePermiso('compras');
require 'header.php';
if ($personaPermiso) {
  require '_persona_form.php';
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script>window.appPersonaTipo = "Proveedor";</script>
<script src="scripts/persona.js?v=<?php echo e(APP_VERSION); ?>"></script>
