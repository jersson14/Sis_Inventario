<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Ajustes de inventario";
$iconoPagina = "fa-exchange";
require 'header.php';
if (usuarioTienePermiso('inventario') || usuarioTienePermiso('almacen')) {
  require_once "../modelos/Inventario.php";
  $motivos = Inventario::motivos();
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Inventario <i class="fa fa-chevron-right"></i> Ajustes</div>
        <h1><span class="page-icon"><i class="fa fa-exchange"></i></span> Ajustes de inventario</h1>
        <p>Entradas y salidas de stock que no son compras ni ventas: conteos, mermas, vencimientos, devoluciones o uso interno. Todo queda en el kardex.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-success" id="btnNuevaEntrada"><i class="fa fa-arrow-down"></i> Entrada</button>
        <button class="btn btn-danger" id="btnNuevaSalida"><i class="fa fa-arrow-up"></i> Salida</button>
      </div>
    </div>

    <div class="alert-cards" id="ajusteResumen">
      <div class="alert-card is-info"><div class="alert-ico"><i class="fa fa-list-ol"></i></div><div><strong id="resAjustes">—</strong><span>Ajustes en el periodo</span></div></div>
      <div class="alert-card is-success"><div class="alert-ico"><i class="fa fa-arrow-down"></i></div><div><strong id="resEntradas">—</strong><span id="resEntradasTxt">Unidades ingresadas</span></div></div>
      <div class="alert-card is-danger"><div class="alert-ico"><i class="fa fa-arrow-up"></i></div><div><strong id="resSalidas">—</strong><span id="resSalidasTxt">Unidades retiradas</span></div></div>
    </div>

    <div class="box">
      <div class="box-body">
        <div class="table-toolbar">
          <div class="form-group"><label>Desde</label><input type="date" id="f_inicio" class="form-control input-sm" value="<?php echo date('Y-m-01'); ?>"></div>
          <div class="form-group"><label>Hasta</label><input type="date" id="f_fin" class="form-control input-sm" value="<?php echo date('Y-m-d'); ?>"></div>
          <div class="form-group"><label>Tipo</label><select id="f_tipo" class="form-control input-sm"><option value="">Todos</option><option value="ENTRADA">Entradas</option><option value="SALIDA">Salidas</option></select></div>
          <div class="toolbar-actions">
            <button type="button" class="btn btn-primary btn-sm" id="btnFiltrar"><i class="fa fa-filter"></i> Filtrar</button>
            <a href="procenter.php" class="btn btn-default btn-sm"><i class="fa fa-line-chart"></i> Ver kardex</a>
          </div>
        </div>
        <div class="table-responsive">
          <table id="tblajustes" class="table table-striped table-bordered table-hover" style="width:100%">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Artículo</th><th>Motivo</th><th>Cantidad</th><th>Stock</th><th>Valor</th><th>Usuario</th><th>Observación</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="modalAjuste" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <form id="formAjuste" autocomplete="off">
      <input type="hidden" name="tipo" id="aj_tipo" value="ENTRADA">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="aj_titulo"><i class="fa fa-arrow-down"></i> Entrada de inventario</h4></div>
      <div class="modal-body">
        <div class="form-group">
          <label>Artículo <span class="req">*</span></label>
          <select name="idarticulo" id="aj_articulo" class="form-control selectpicker" data-live-search="true" data-width="100%" data-size="8" required></select>
          <span class="help-block" id="aj_info">Selecciona un artículo para ver su stock actual.</span>
        </div>
        <div class="row">
          <div class="form-group col-xs-6"><label>Cantidad <span class="req">*</span></label><input type="number" step="1" min="1" class="form-control input-lg" name="cantidad" id="aj_cantidad" required></div>
          <div class="form-group col-xs-6"><label>Costo unitario</label><input type="number" step="0.01" min="0" class="form-control" name="costo_unitario" id="aj_costo" placeholder="Precio de compra"><span class="help-block">Vacío = costo del artículo.</span></div>
        </div>
<?php if (negocioTiene('lotes') || negocioTiene('vencimientos')): ?>
        <div class="row lote-entrada">
          <div class="form-group col-xs-6"><label>Lote</label><input type="text" class="form-control" name="lote_codigo" id="aj_lote_codigo" maxlength="40" placeholder="Opcional"></div>
          <div class="form-group col-xs-6"><label>Fecha de vencimiento</label><input type="date" class="form-control" name="lote_vencimiento" id="aj_lote_vence"></div>
        </div>
        <div class="form-group lote-salida" style="display:none">
          <label>Retirar del lote</label>
          <select name="idlote" id="aj_idlote" class="form-control"><option value="0">Automático: primero lo vencido y lo que vence antes</option></select>
        </div>
<?php endif; ?>
        <div class="form-group">
          <label>Motivo <span class="req">*</span></label>
          <select name="motivo" id="aj_motivo" class="form-control" required>
            <?php foreach ($motivos as $k => $v) { ?><option value="<?php echo e($k); ?>"><?php echo e($v); ?></option><?php } ?>
          </select>
        </div>
        <div class="form-group"><label>Observación</label><input type="text" class="form-control" name="observacion" id="aj_obs" maxlength="200" placeholder="Detalle opcional"></div>
        <div class="alert alert-info mb-0" id="aj_preview" style="display:none"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="btnGuardarAjuste"><i class="fa fa-check"></i> Registrar ajuste</button></div>
    </form>
  </div></div>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/inventario.js?v=<?php echo e(APP_VERSION); ?>"></script>
