<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Vencimientos";
$iconoPagina = "fa-calendar-times-o";
require 'header.php';
if (usuarioTienePermiso('inventario') || usuarioTienePermiso('almacen')) {
  require_once "../modelos/Lote.php";
  $usaLotes = Lote::activo();
  $diasAlerta = $usaLotes ? Lote::diasAlerta() : 30;
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Inventario <i class="fa fa-chevron-right"></i> Vencimientos</div>
        <h1><span class="page-icon"><i class="fa fa-calendar-times-o"></i></span> Vencimientos y lotes</h1>
        <p>Lo que está por vencer primero se vende primero. Lo vencido no se puede vender: dalo de baja para que tu stock y tu kardex queden al día.</p>
      </div>
      <div class="page-actions">
        <a href="inventario.php" class="btn btn-default"><i class="fa fa-exchange"></i> Ajustes de inventario</a>
      </div>
    </div>

<?php if (!$usaLotes): ?>
    <div class="box"><div class="box-body">
      <div class="empty-state" style="display:block">
        <i class="fa fa-calendar-times-o"></i>
        <strong>El control de vencimientos no está activo</strong>
        Tu tipo de negocio actual no usa lotes ni fechas de vencimiento. Si vendes abarrotes, cambia el rubro en
        <a href="empresa.php">Configuración → Empresa</a>.
      </div>
    </div></div>
<?php else: ?>
    <div class="alert-cards">
      <a href="#" class="alert-card is-danger filtro-rapido" data-estado="VENCIDO"><div class="alert-ico"><i class="fa fa-ban"></i></div><div><strong id="resVencidos">—</strong><span id="resVencidosTxt">Lotes vencidos</span></div></a>
      <a href="#" class="alert-card is-warning filtro-rapido" data-estado="POR_VENCER"><div class="alert-ico"><i class="fa fa-hourglass-half"></i></div><div><strong id="resPorVencer">—</strong><span id="resPorVencerTxt">Por vencer en <?php echo (int)$diasAlerta; ?> días</span></div></a>
      <a href="#" class="alert-card is-info filtro-rapido" data-estado=""><div class="alert-ico"><i class="fa fa-cubes"></i></div><div><strong id="resLotes">—</strong><span>Lotes con stock</span></div></a>
    </div>

    <div class="box">
      <div class="box-body">
        <div class="table-toolbar">
          <div class="form-group"><label>Estado</label>
            <select id="f_estado" class="form-control input-sm">
              <option value="">Todos</option>
              <option value="VENCIDO">Vencidos</option>
              <option value="POR_VENCER" selected>Por vencer</option>
              <option value="VIGENTE">Vigentes</option>
              <option value="SIN_FECHA">Sin fecha</option>
            </select>
          </div>
          <div class="form-group"><label>Por vencer en (días)</label><input type="number" min="1" max="365" id="f_dias" class="form-control input-sm" value="<?php echo (int)$diasAlerta; ?>"></div>
          <div class="toolbar-actions">
            <button type="button" class="btn btn-primary btn-sm" id="btnFiltrar"><i class="fa fa-filter"></i> Filtrar</button>
          </div>
        </div>
        <div class="table-responsive">
          <table id="tbllotes" class="table table-striped table-bordered table-hover" style="width:100%">
            <thead><tr><th>Opciones</th><th>Artículo</th><th>Lote</th><th>Vence</th><th>Estado</th><th>Stock</th><th>Valor</th><th>Origen</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
<?php endif; ?>
  </section>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/vencimientos.js?v=<?php echo e(APP_VERSION); ?>"></script>
