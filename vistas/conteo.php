<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Toma de inventario";
$iconoPagina = "fa-barcode";
require 'header.php';
if (usuarioTienePermiso('inventario') || usuarioTienePermiso('almacen')) {
  require_once "../modelos/Lote.php";
  $usaLotes = Lote::activo();
  $categorias = dbAll("SELECT idcategoria, nombre FROM categoria WHERE condicion=1 ORDER BY nombre");
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Inventario <i class="fa fa-chevron-right"></i> Toma de inventario</div>
        <h1><span class="page-icon"><i class="fa fa-barcode"></i></span> Toma de inventario</h1>
        <p>Cuenta tu almacén con el lector de barras. El sistema compara lo contado con su stock, te muestra las diferencias en unidades y soles, y al aplicar hace los ajustes (incluidos lotes y tallas).</p>
      </div>
      <div class="page-actions">
        <a href="inventario.php" class="btn btn-default"><i class="fa fa-exchange"></i> Ajustes de inventario</a>
      </div>
    </div>

    <!-- Sin conteo abierto: iniciar uno -->
    <div id="panelInicio" style="display:none">
      <div class="row">
        <div class="col-md-7">
          <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-play-circle"></i> Empezar un conteo</h3></div>
            <form id="formNuevoConteo" autocomplete="off">
              <div class="box-body">
                <div class="form-group">
                  <label for="nc_nombre">Nombre</label>
                  <input type="text" class="form-control" name="nombre" id="nc_nombre" maxlength="80" placeholder="Conteo <?php echo date('d/m/Y'); ?>">
                </div>
<?php require_once "../modelos/Stock.php"; if (Stock::multiAlmacen()) { $almActualC = Stock::almacenActual(); ?>
                <div class="form-group">
                  <label for="nc_almacen">Almacén</label>
                  <select class="form-control" name="idalmacen" id="nc_almacen"<?php echo usuarioTienePermiso('almacenes') ? '' : ' disabled'; ?>>
                    <?php foreach (Stock::almacenes() as $alC) { ?><option value="<?php echo (int)$alC['idalmacen']; ?>"<?php echo (int)$alC['idalmacen'] === $almActualC ? ' selected' : ''; ?>><?php echo e(html_entity_decode($alC['nombre'], ENT_QUOTES, 'UTF-8')); ?></option><?php } ?>
                  </select>
                  <span class="help-block">Se compara y se ajusta el stock de este almacén.</span>
                </div>
<?php } ?>
                <div class="form-group">
                  <label for="nc_categoria">¿Qué vas a contar?</label>
                  <select class="form-control" name="idcategoria" id="nc_categoria">
                    <option value="0">Todo el almacén</option>
                    <?php foreach ($categorias as $c) { ?><option value="<?php echo (int)$c['idcategoria']; ?>">Solo la categoría: <?php echo e(html_entity_decode($c['nombre'], ENT_QUOTES, 'UTF-8')); ?></option><?php } ?>
                  </select>
                  <span class="help-block">Contar por categoría (un pasillo, una sección) es más fácil de terminar en un día.</span>
                </div>
<?php if ($usaLotes) { ?>
                <div class="checkbox"><label><input type="checkbox" name="por_lote" id="nc_por_lote" value="1" checked> Contar cada lote por separado (código y vencimiento)</label></div>
                <p class="text-soft" style="margin-top:-4px">Así el sistema sabe cuánto queda de cada lote y qué vence primero. Si lo desmarcas, se cuenta el total del producto y las bajas salen primero de lo vencido.</p>
<?php } ?>
                <div class="form-group">
                  <label for="nc_obs">Observación</label>
                  <input type="text" class="form-control" name="observacion" id="nc_obs" maxlength="200" placeholder="Opcional: quién cuenta, turno…">
                </div>
              </div>
              <div class="box-footer">
                <button type="submit" class="btn btn-primary btn-lg" id="btnEmpezar"><i class="fa fa-barcode"></i> Empezar a contar</button>
              </div>
            </form>
          </div>
        </div>
        <div class="col-md-5">
          <div class="callout-soft">
            <strong><i class="fa fa-lightbulb-o"></i> Cómo hacer un buen conteo</strong>
            <ol style="margin:8px 0 0 18px;padding:0">
              <li>Mejor con la tienda cerrada. Si vendes mientras cuentas no pasa nada: el sistema ajusta solo la diferencia de lo que contaste.</li>
              <li>Escanea cada unidad, o escribe la cantidad y luego escanea (también sirve <code>12*código</code>). Una caja con su propio código suma su equivalencia.</li>
              <li>Pueden contar varias personas a la vez, cada una desde su PC o celular, en el mismo conteo.</li>
              <li>Revisa <em>Pendientes</em> antes de aplicar: son los productos con stock que nadie contó.</li>
              <li>Al aplicar, el sistema genera los ajustes con motivo <em>Conteo físico</em>; quedan en el kardex y en el reporte del conteo.</li>
            </ol>
          </div>
          <div class="callout-soft" style="margin-top:12px">
            <strong><i class="fa fa-usb"></i> El lector de barras</strong>
            <p style="margin:6px 0 0">Funciona conectado por USB como un teclado. El sistema reconoce la lectura aunque tu lector no envíe Enter al final. Si no lee nada, revisa en su manual que esté en modo <em>USB HID / teclado</em>.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Conteo abierto -->
    <div id="panelConteo" style="display:none">
      <div class="conteo-cabecera">
        <div>
          <h2 id="cNombre">—</h2>
          <div class="text-soft" id="cDatos">—</div>
        </div>
        <div class="conteo-acciones">
          <button type="button" class="btn btn-default" id="btnPendientes" title="Productos con stock que aún nadie contó"><i class="fa fa-list-ul"></i> Pendientes <span class="badge" id="badgePendientes">0</span></button>
          <button type="button" class="btn btn-default" id="btnActualizar" title="Ver lo que cuentan las otras personas"><i class="fa fa-refresh"></i> Actualizar</button>
          <button type="button" class="btn btn-success" id="btnRevisar" title="Ver los ajustes que se harán y aplicarlos"><i class="fa fa-check-square-o"></i> Revisar y aplicar</button>
          <button type="button" class="btn btn-danger" id="btnAnular" title="Cancelar este conteo sin tocar el stock"><i class="fa fa-ban"></i> Anular</button>
        </div>
      </div>

      <div class="alert-cards">
        <div class="alert-card is-info"><div class="alert-ico"><i class="fa fa-cubes"></i></div><div><strong id="rContados">—</strong><span id="rContadosTxt">Artículos contados</span></div></div>
        <div class="alert-card is-success"><div class="alert-ico"><i class="fa fa-plus-circle"></i></div><div><strong id="rSobrante">—</strong><span>Sobrante (a costo)</span></div></div>
        <div class="alert-card is-danger"><div class="alert-ico"><i class="fa fa-minus-circle"></i></div><div><strong id="rFaltante">—</strong><span>Faltante (a costo)</span></div></div>
        <div class="alert-card is-warning"><div class="alert-ico"><i class="fa fa-hourglass-half"></i></div><div><strong id="rPendientes">—</strong><span id="rPendientesTxt">Pendientes por contar</span></div></div>
      </div>

      <div class="box conteo-lector">
        <div class="box-body">
          <div class="conteo-lector-fila">
            <div class="conteo-cantidad">
              <label for="cCantidad">Cantidad</label>
              <input type="number" id="cCantidad" class="form-control input-lg" value="1" min="0" step="any" title="Cuántas unidades suma la próxima lectura">
            </div>
            <div class="conteo-scan">
              <label for="cScan">Escanea o escribe el código</label>
              <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-barcode"></i></span>
                <input type="text" id="cScan" class="form-control input-lg" placeholder="Código de barras, de caja, de talla o de lote · o busca por nombre" autocomplete="off" autofocus>
                <span class="input-group-btn"><button type="button" class="btn btn-primary btn-lg" id="btnScan" title="Registrar"><i class="fa fa-level-down fa-rotate-90"></i></button></span>
              </div>
            </div>
          </div>
          <div id="ultimaLectura" class="conteo-ultima vacia">
            <i class="fa fa-barcode"></i> Esperando la primera lectura…
          </div>
          <div id="loteRecordado" class="conteo-recordado" style="display:none"></div>
        </div>
      </div>

      <div class="box">
        <div class="box-body">
          <div class="table-toolbar">
            <div class="form-group"><label>Buscar</label><input type="search" id="fLineas" class="form-control input-sm" placeholder="Artículo, código o lote"></div>
            <div class="form-group"><label>Mostrar</label>
              <select id="fDif" class="form-control input-sm">
                <option value="">Todo lo contado</option>
                <option value="dif">Solo con diferencia</option>
                <option value="falta">Solo faltantes</option>
                <option value="sobra">Solo sobrantes</option>
              </select>
            </div>
            <div class="toolbar-actions"><span class="text-soft" id="lineasTotal"></span></div>
          </div>
          <div class="table-responsive">
            <table class="table table-bordered table-hover conteo-tabla" id="tblLineas">
              <thead><tr><th>Artículo</th><th>Talla / lote</th><th class="text-right" style="width:130px">Contado</th><th class="text-right">Sistema</th><th class="text-right">Diferencia</th><th class="text-right">Valor</th><th>Contó</th><th style="width:44px"></th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="box" id="boxHistorial">
      <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-history"></i> Conteos anteriores</h3></div>
      <div class="box-body">
        <div class="table-responsive">
          <table id="tblConteos" class="table table-striped table-bordered table-hover" style="width:100%">
            <thead><tr><th>Opciones</th><th>Fecha</th><th>Nombre</th><th>Alcance</th><th>Estado</th><th>Líneas</th><th>Ajustes</th><th>Sobrante / faltante</th><th>Inició</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Elegir talla/color o lote -->
<div class="modal fade" id="modalElegir" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="elegirTitulo">Elige</h4></div>
    <div class="modal-body">
      <p class="text-soft" id="elegirAyuda">Toca una opción o presiona su número.</p>
      <div id="elegirOpciones" class="conteo-opciones"></div>
      <div id="elegirLoteNuevo" class="conteo-lote-nuevo" style="display:none">
        <div class="row">
          <div class="form-group col-xs-6"><label for="lnCodigo">Código del lote</label><input type="text" id="lnCodigo" class="form-control" maxlength="40" placeholder="Opcional"></div>
          <div class="form-group col-xs-6"><label for="lnVence">Vence</label><input type="date" id="lnVence" class="form-control"></div>
        </div>
        <button type="button" class="btn btn-primary" id="btnLoteNuevo"><i class="fa fa-plus"></i> Contar en este lote nuevo</button>
      </div>
      <div class="checkbox" id="elegirRecordarGrupo" style="display:none"><label><input type="checkbox" id="elegirRecordar" checked> Usar este lote en las próximas lecturas de este producto</label></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar (Esc)</button></div>
  </div></div>
</div>

<!-- Codigo no reconocido: buscar por nombre -->
<div class="modal fade" id="modalBuscar" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-search"></i> Buscar producto</h4></div>
    <div class="modal-body">
      <p class="text-danger" id="buscarAviso"></p>
      <input type="search" id="buscarTexto" class="form-control" placeholder="Nombre o código" autocomplete="off">
      <div class="list-group conteo-resultados" id="buscarResultados" style="margin-top:10px"></div>
    </div>
  </div></div>
</div>

<!-- Pendientes -->
<div class="modal fade" id="modalPendientes" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-list-ul"></i> Pendientes por contar</h4></div>
    <div class="modal-body">
      <p class="text-soft">Productos con stock en el sistema que nadie ha contado todavía. Si de verdad no hay ninguno, usa <strong>No hay</strong>; si al aplicar marcas "poner en cero lo no contado" se ajustarán todos juntos.</p>
      <div class="table-responsive" style="max-height:60vh">
        <table class="table table-condensed table-hover" id="tblPendientes">
          <thead><tr><th>Artículo</th><th>Categoría</th><th class="text-right">Stock</th><th class="text-right">Valor</th><th style="width:170px"></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div></div>
</div>

<!-- Revisar y aplicar -->
<div class="modal fade" id="modalAplicar" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-check-square-o"></i> Revisar y aplicar el conteo</h4></div>
    <div class="modal-body">
      <div class="checkbox" style="margin-top:0"><label><input type="checkbox" id="apCero"> <strong>Poner en cero lo que no se contó</strong> <span id="apCeroTxt" class="text-soft"></span></label></div>
      <p class="text-soft" style="margin-top:-6px">Márcalo solo si contaste <em>todo</em> lo del alcance: los productos, tallas y lotes sin contar se darán de baja.</p>
      <div class="alert-cards" style="margin-bottom:10px">
        <div class="alert-card is-info"><div class="alert-ico"><i class="fa fa-exchange"></i></div><div><strong id="apAjustes">—</strong><span>Ajustes a generar</span></div></div>
        <div class="alert-card is-success"><div class="alert-ico"><i class="fa fa-plus-circle"></i></div><div><strong id="apSobrante">—</strong><span>Entradas (sobrante)</span></div></div>
        <div class="alert-card is-danger"><div class="alert-ico"><i class="fa fa-minus-circle"></i></div><div><strong id="apFaltante">—</strong><span>Salidas (faltante)</span></div></div>
      </div>
      <div class="table-responsive" style="max-height:45vh">
        <table class="table table-condensed table-bordered" id="tblPlan">
          <thead><tr><th>Artículo</th><th>Talla / lote</th><th>Ajuste</th><th class="text-right">Cantidad</th><th class="text-right">Valor</th><th>Nota</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <p class="text-danger" id="apSinPermiso" style="display:none"><i class="fa fa-lock"></i> Para aplicar el conteo se necesita el permiso "Ajustes de inventario". Pide a un encargado que lo aplique.</p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-default" data-dismiss="modal">Seguir contando</button>
      <button type="button" class="btn btn-success" id="btnAplicar"><i class="fa fa-check"></i> Aplicar ajustes y cerrar el conteo</button>
    </div>
  </div></div>
</div>

<!-- Ver un conteo del historial -->
<div class="modal fade" id="modalVer" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="verTitulo">Conteo</h4></div>
    <div class="modal-body">
      <div class="text-soft" id="verDatos" style="margin-bottom:10px"></div>
      <div class="table-responsive" style="max-height:60vh">
        <table class="table table-condensed table-bordered" id="tblVer">
          <thead><tr><th>Artículo</th><th>Talla / lote</th><th class="text-right">Contado</th><th class="text-right">Sistema</th><th class="text-right">Diferencia</th><th class="text-right">Ajustado</th><th class="text-right">Valor</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <a href="#" target="_blank" class="btn btn-default" id="verReporte"><i class="fa fa-print"></i> Imprimir reporte</a>
      <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
    </div>
  </div></div>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/conteo.js?v=<?php echo e(APP_VERSION); ?>"></script>
