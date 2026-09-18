<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Notas de crédito";
$iconoPagina = "fa-undo";
require 'header.php';
if (usuarioTienePermiso('ventas')) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Ventas <i class="fa fa-chevron-right"></i> Notas de crédito</div>
        <h1><span class="page-icon"><i class="fa fa-undo"></i></span> Devoluciones y notas de crédito</h1>
        <p>Cada devolución o anulación genera una nota de crédito: devuelve el stock a su talla y lote, baja la deuda o devuelve el dinero (o lo deja como saldo a favor del cliente). Para registrar una, abre la venta en <a href="venta.php">Ventas</a> y usa <strong>Devolver</strong>.</p>
      </div>
      <div class="page-actions">
        <a href="venta.php" class="btn btn-default"><i class="fa fa-list"></i> Ventas</a>
      </div>
    </div>
    <div class="box">
      <div class="box-body">
        <div class="table-toolbar">
          <div class="form-group"><label>Desde</label><input type="date" id="nc_inicio" class="form-control input-sm" value="<?php echo date('Y-m-01'); ?>"></div>
          <div class="form-group"><label>Hasta</label><input type="date" id="nc_fin" class="form-control input-sm" value="<?php echo date('Y-m-d'); ?>"></div>
          <div class="toolbar-actions"><button type="button" class="btn btn-primary btn-sm" id="btnFiltrarNC"><i class="fa fa-filter"></i> Filtrar</button></div>
        </div>
        <div class="table-responsive">
          <table id="tblnotas" class="table table-striped table-bordered table-hover" style="width:100%">
            <thead><tr><th>Opciones</th><th>Fecha</th><th>Nota</th><th>Venta</th><th>Cliente</th><th>Tipo y motivo</th><th>Total</th><th>Reintegro</th><th>Registró</th></tr></thead>
            <tbody></tbody>
          </table>
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
<script>
var tablaNC = $("#tblnotas").DataTable({
  aProcessing: true, aServerSide: true, bDestroy: true, iDisplayLength: 15,
  dom: "Bfrtip",
  buttons: window.appDataTableButtons("Notas de crédito", true),
  order: [[1, "desc"]],
  columnDefs: [{ targets: 1, render: window.appOrdenPorDato }],
  ajax: {
    url: "../ajax/venta.php?op=listarNotas", type: "get", dataType: "json",
    data: function (d) { d.fecha_inicio = $("#nc_inicio").val(); d.fecha_fin = $("#nc_fin").val(); },
    error: function (e) { console.log(e.responseText); }
  }
});
$("#btnFiltrarNC").on("click", function () { tablaNC.ajax.reload(); });
</script>
