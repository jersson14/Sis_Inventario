<?php
$appCurrencyCode = function_exists('obtenerMonedaEmpresaCodigo') ? obtenerMonedaEmpresaCodigo() : 'PEN';
$appCurrencySymbol = function_exists('obtenerSimboloMoneda') ? obtenerSimboloMoneda($appCurrencyCode) : 'S/';
$appCsrf = function_exists('csrfToken') ? csrfToken() : '';
$appPermisosJs = array();
if (function_exists('mapaPermisos')) {
  foreach (mapaPermisos() as $clave => $id) {
    $appPermisosJs[$clave] = usuarioTienePermiso($clave);
  }
}
?>
  <footer class="main-footer">
    <div class="pull-right hidden-xs">
      <strong><?php echo e(PRO_NOMBRE); ?></strong> v<?php echo e(APP_VERSION); ?>
    </div>
    &copy; <?php echo date('Y'); ?> Todos los derechos reservados.
  </footer>
</div><!-- /.wrapper -->

<script src="../public/js/jquery.min.js?v=<?php echo e(APP_VERSION); ?>"></script>
<script src="../public/js/bootstrap.min.js?v=<?php echo e(APP_VERSION); ?>"></script>
<script src="../public/js/adminlte.min.js"></script>
<script src="../public/datatables/jquery.dataTables.min.js"></script>
<script src="../public/datatables/dataTables.buttons.min.js"></script>
<script src="../public/datatables/buttons.colVis.min.js"></script>
<script src="../public/datatables/buttons.html5.min.js"></script>
<script src="../public/datatables/jszip.min.js"></script>
<script src="../public/datatables/pdfmake.min.js"></script>
<script src="../public/datatables/vfs_fonts.js"></script>
<script src="../public/datatables/datatables.min.js"></script>
<script src="../public/js/bootbox.min.js"></script>
<script src="../public/js/bootstrap-select.min.js"></script>
<script>
window.appCsrfToken = <?php echo json_encode($appCsrf); ?>;
window.appCurrencyCode = <?php echo json_encode($appCurrencyCode); ?>;
window.appCurrencySymbol = <?php echo json_encode($appCurrencySymbol); ?>;
window.appUser = <?php echo json_encode(array(
  'id' => (int)$_SESSION['idusuario'],
  'nombre' => (string)$_SESSION['nombre'],
  'login' => (string)$_SESSION['login'],
  'permisos' => $appPermisosJs
), JSON_UNESCAPED_UNICODE); ?>;
// Perfil de negocio: los modulos consultan window.appNegocioTiene('lotes') para
// mostrar u ocultar lo que corresponde al rubro configurado.
window.appNegocio = <?php echo json_encode(function_exists('negocioParaJs') ? negocioParaJs() : array('perfil' => 'GENERAL', 'capacidades' => array()), JSON_UNESCAPED_UNICODE); ?>;
window.appNegocioTiene = function (capacidad) {
  return !!(window.appNegocio && window.appNegocio.capacidades && window.appNegocio.capacidades.indexOf(capacidad) !== -1);
};
// Cantidades: enteras salvo que el articulo admita fraccion (metros, kilos).
window.appNormalizarCantidad = function (valor, permiteFraccion, minimo) {
  var num = parseFloat(String(valor == null ? "" : valor).replace(",", "."));
  if (!isFinite(num) || num < 0) { num = 0; }
  num = permiteFraccion ? Math.round(num * 1000) / 1000 : Math.round(num);
  if (typeof minimo === "number" && num < minimo) { num = minimo; }
  return num;
};
window.appCantidad = function (valor) {
  var num = Math.round((parseFloat(valor) || 0) * 1000) / 1000;
  return num.toLocaleString("es-PE", { minimumFractionDigits: 0, maximumFractionDigits: 3 });
};
window.appMoney = function(value, decimals) {
  var num = Number(value);
  if (!isFinite(num)) { num = 0; }
  var dec = (typeof decimals === "number") ? decimals : 2;
  return window.appCurrencySymbol + " " + num.toLocaleString("es-PE", { minimumFractionDigits: dec, maximumFractionDigits: dec });
};
</script>
<script src="../public/js/app-notify.js?v=<?php echo e(APP_VERSION); ?>"></script>
<script src="../public/js/app-datatable.js?v=<?php echo e(APP_VERSION); ?>"></script>
<script src="../public/js/app-core.js?v=<?php echo e(APP_VERSION); ?>"></script>
