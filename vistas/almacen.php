<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Almacenes";
$iconoPagina = "fa-building-o";
require 'header.php';
if (usuarioTienePermiso('almacenes') || usuarioTienePermiso('inventario') || usuarioTienePermiso('almacen')) {
  require_once "../modelos/Stock.php";
  $puedeGestionar = usuarioTienePermiso('almacenes');
  $almacenes = Stock::almacenes();
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Inventario <i class="fa fa-chevron-right"></i> Almacenes</div>
        <h1><span class="page-icon"><i class="fa fa-building-o"></i></span> Almacenes y transferencias</h1>
        <p>Tienda, depósito, otro local: cada almacén tiene su propio stock. Vendes y ajustas en el almacén donde trabajas, y mueves mercadería entre almacenes con transferencias (con sus tallas y lotes).</p>
      </div>
      <div class="page-actions">
<?php if ($puedeGestionar) { ?>
        <button type="button" class="btn btn-default" id="btnNuevoAlmacen" title="Crear un almacén"><i class="fa fa-plus"></i> Almacén</button>
        <button type="button" class="btn btn-primary" id="btnNuevaTransferencia" title="Enviar mercadería de un almacén a otro"><i class="fa fa-exchange"></i> Nueva transferencia</button>
<?php } ?>
      </div>
    </div>

    <div id="almacenesCards" class="almacen-cards"></div>

    <div class="nav-tabs-custom">
      <ul class="nav nav-tabs">
        <li class="active"><a href="#tabTransferencias" data-toggle="tab"><i class="fa fa-exchange"></i> Transferencias <span class="badge" id="badgeTransito" style="display:none"></span></a></li>
        <li><a href="#tabStock" data-toggle="tab" id="lnkStock"><i class="fa fa-cubes"></i> Stock por almacén</a></li>
      </ul>
      <div class="tab-content">
        <div class="tab-pane active" id="tabTransferencias">
          <div class="table-toolbar">
            <div class="form-group"><label>Desde</label><input type="date" id="tr_inicio" class="form-control input-sm" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>"></div>
            <div class="form-group"><label>Hasta</label><input type="date" id="tr_fin" class="form-control input-sm" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="form-group"><label>Estado</label>
              <select id="tr_estado" class="form-control input-sm"><option value="">Todas</option><option value="ENVIADA">En camino</option><option value="RECIBIDA">Recibidas</option><option value="ANULADA">Anuladas</option></select>
            </div>
            <div class="toolbar-actions"><button type="button" class="btn btn-primary btn-sm" id="btnFiltrarTr"><i class="fa fa-filter"></i> Filtrar</button></div>
          </div>
          <div class="table-responsive">
            <table id="tblTransferencias" class="table table-striped table-bordered table-hover" style="width:100%">
              <thead><tr><th>Opciones</th><th>Fecha</th><th>N°</th><th>Origen → destino</th><th>Contenido</th><th>Valor</th><th>Estado</th><th>Registró</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <div class="tab-pane" id="tabStock">
          <div class="table-toolbar">
            <div class="form-group"><label>Buscar</label><input type="search" id="st_q" class="form-control input-sm" placeholder="Nombre o código"></div>
            <div class="form-group"><label>&nbsp;</label><div class="checkbox" style="margin:4px 0 0"><label><input type="checkbox" id="st_con" checked> Solo con stock</label></div></div>
            <div class="toolbar-actions"><button type="button" class="btn btn-primary btn-sm" id="btnFiltrarSt"><i class="fa fa-filter"></i> Filtrar</button></div>
          </div>
          <div class="table-responsive">
            <table id="tblStockAlm" class="table table-striped table-bordered table-hover" style="width:100%"><thead></thead><tbody></tbody></table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Almacen -->
<div class="modal fade" id="modalAlmacen" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
    <form id="formAlmacen" autocomplete="off">
      <input type="hidden" name="idalmacen" id="al_id">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="alTitulo"><i class="fa fa-building-o"></i> Almacén</h4></div>
      <div class="modal-body">
        <div class="form-group"><label for="al_nombre">Nombre <span class="req">*</span></label><input type="text" class="form-control" name="nombre" id="al_nombre" maxlength="60" placeholder="Ej.: Depósito, Tienda 2" required></div>
        <div class="form-group"><label for="al_direccion">Dirección</label><input type="text" class="form-control" name="direccion" id="al_direccion" maxlength="150"></div>
        <div class="form-group"><label for="al_responsable">Responsable</label><input type="text" class="form-control" name="responsable" id="al_responsable" maxlength="80"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="btnGuardarAlmacen"><i class="fa fa-check"></i> Guardar</button></div>
    </form>
  </div></div>
</div>

<!-- Nueva transferencia -->
<div class="modal fade" id="modalTransferencia" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <form id="formTransferencia" autocomplete="off">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-exchange"></i> Nueva transferencia</h4></div>
      <div class="modal-body">
        <div class="row">
          <div class="form-group col-sm-5">
            <label for="tr_origen">Sale de</label>
            <select id="tr_origen" class="form-control">
              <?php foreach ($almacenes as $a) { ?><option value="<?php echo (int)$a['idalmacen']; ?>"><?php echo e(html_entity_decode($a['nombre'], ENT_QUOTES, 'UTF-8')); ?></option><?php } ?>
            </select>
          </div>
          <div class="col-sm-2 text-center hidden-xs" style="padding-top:30px"><i class="fa fa-long-arrow-right fa-2x text-soft"></i></div>
          <div class="form-group col-sm-5">
            <label for="tr_destino">Llega a</label>
            <select id="tr_destino" class="form-control">
              <?php foreach ($almacenes as $a) { ?><option value="<?php echo (int)$a['idalmacen']; ?>"><?php echo e(html_entity_decode($a['nombre'], ENT_QUOTES, 'UTF-8')); ?></option><?php } ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label for="tr_scan">Agregar producto</label>
          <div class="input-group">
            <span class="input-group-addon"><i class="fa fa-barcode"></i></span>
            <input type="text" id="tr_scan" class="form-control input-lg" placeholder="Escanea o escribe el código, o busca por nombre" autocomplete="off">
          </div>
          <div class="list-group" id="trSugerencias" style="margin:4px 0 0"></div>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-condensed tr-lineas" id="trLineas">
            <thead><tr><th>Producto</th><th>Talla / lote</th><th class="text-right">Hay en origen</th><th class="text-right" style="width:130px">Enviar</th><th style="width:40px"></th></tr></thead>
            <tbody><tr class="vacio"><td colspan="5" class="text-center text-soft">Escanea o busca los productos que vas a enviar.</td></tr></tbody>
          </table>
        </div>
        <div class="row">
          <div class="form-group col-sm-8"><label for="tr_obs">Observación</label><input type="text" id="tr_obs" class="form-control" maxlength="200" placeholder="Opcional: quién lleva, vehículo, guía…"></div>
          <div class="col-sm-4"><div class="checkbox" style="margin-top:26px"><label title="Cuando el destino está en el mismo local: se recibe en el acto"><input type="checkbox" id="tr_ya"> Llega al instante (recibir ahora)</label></div></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="btnEnviarTr"><i class="fa fa-paper-plane"></i> Enviar</button></div>
    </form>
  </div></div>
</div>

<!-- Ver / recibir transferencia -->
<div class="modal fade" id="modalVerTr" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="verTrTitulo">Transferencia</h4></div>
    <div class="modal-body">
      <div id="verTrDatos" class="text-soft" style="margin-bottom:10px"></div>
      <p class="text-soft" id="verTrAyuda" style="display:none"><i class="fa fa-info-circle"></i> Revisa lo que llegó. Si algo no llegó completo, corrige la cantidad: la diferencia se da de baja como faltante del traslado.</p>
      <div class="table-responsive">
        <table class="table table-bordered table-condensed" id="verTrTabla">
          <thead><tr><th>Producto</th><th>Lote</th><th class="text-right">Enviado</th><th class="text-right" style="width:140px">Recibido</th><th class="text-right">Costo</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="form-group" id="verTrObsGrupo" style="display:none"><label for="verTrObs">Observación de la recepción</label><input type="text" id="verTrObs" class="form-control" maxlength="200"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-danger pull-left" id="btnAnularTr" style="display:none" title="La mercadería vuelve al almacén de origen"><i class="fa fa-ban"></i> Anular</button>
      <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
      <button type="button" class="btn btn-success" id="btnRecibirTr" style="display:none"><i class="fa fa-check"></i> Confirmar recepción</button>
    </div>
  </div></div>
</div>
<script>window.almPuedeGestionar = <?php echo $puedeGestionar ? 'true' : 'false'; ?>;</script>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/almacen.js?v=<?php echo e(APP_VERSION); ?>"></script>
