<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Importar desde Excel";
$iconoPagina = "fa-file-excel-o";
require 'header.php';
$puedeArt = usuarioTienePermiso('almacen');
$puedeCli = usuarioTienePermiso('ventas');
$puedeProv = usuarioTienePermiso('compras');
if ($puedeArt || $puedeCli || $puedeProv) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Inventario <i class="fa fa-chevron-right"></i> Importar</div>
        <h1><span class="page-icon"><i class="fa fa-file-excel-o"></i></span> Importar desde Excel / CSV</h1>
        <p>Carga cientos de artículos, clientes o proveedores en segundos. Descarga la plantilla, complétala y súbela: verás una vista previa antes de confirmar.</p>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-4 col-md-5">
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title"><span class="step-n">1</span> ¿Qué vas a importar?</h3></div>
          <div class="box-body">
            <div class="form-group">
              <select id="tipo" class="form-control">
                <?php if ($puedeArt) { ?><option value="articulos">Artículos</option><?php } ?>
                <?php if ($puedeCli) { ?><option value="clientes">Clientes</option><?php } ?>
                <?php if ($puedeProv) { ?><option value="proveedores">Proveedores</option><?php } ?>
              </select>
            </div>
            <label>Descarga la plantilla</label>
            <div class="d-flex gap-8 mb-10">
              <a class="btn btn-success btn-sm" id="btnPlantillaXlsx" href="#" target="_blank"><i class="fa fa-file-excel-o"></i> Excel (.xlsx)</a>
              <a class="btn btn-default btn-sm" id="btnPlantillaCsv" href="#" target="_blank"><i class="fa fa-file-text-o"></i> CSV</a>
            </div>
            <div class="alert alert-info mb-0" style="font-size:12.5px" id="ayudaColumnas"></div>
          </div>
        </div>

        <div class="box">
          <div class="box-header with-border"><h3 class="box-title"><span class="step-n">2</span> Sube el archivo</h3></div>
          <div class="box-body">
            <form id="formArchivo" enctype="multipart/form-data">
              <div class="form-group">
                <input type="file" class="form-control" name="archivo" id="archivo" accept=".xlsx,.csv,.txt" required>
                <span class="help-block">Excel (.xlsx) o CSV. Máximo 10 MB y 5000 filas por archivo. La primera fila debe ser la cabecera.</span>
              </div>
              <button type="submit" class="btn btn-primary w-100" id="btnPrevisualizar"><i class="fa fa-search"></i> Analizar y previsualizar</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-8 col-md-7">
        <div class="box" id="boxPreview" style="display:none">
          <div class="box-header with-border">
            <h3 class="box-title"><span class="step-n">3</span> Vista previa</h3>
            <div class="box-tools"><span class="chip" id="chipArchivo"></span></div>
          </div>
          <div class="box-body">
            <div class="alert-cards" style="grid-template-columns:repeat(3,1fr);margin-bottom:12px">
              <div class="alert-card is-success"><div class="alert-ico"><i class="fa fa-plus"></i></div><div><strong id="resCrear">0</strong><span>Nuevos</span></div></div>
              <div class="alert-card is-info"><div class="alert-ico"><i class="fa fa-refresh"></i></div><div><strong id="resActualizar">0</strong><span>Existentes</span></div></div>
              <div class="alert-card is-danger"><div class="alert-ico"><i class="fa fa-exclamation-triangle"></i></div><div><strong id="resError">0</strong><span>Con errores (se omiten)</span></div></div>
            </div>
            <div id="avisoColumnas"></div>
            <div id="opcionesImport" class="well" style="padding:12px 14px;margin-bottom:12px">
              <label class="login-remember" style="display:flex;margin-bottom:6px"><input type="checkbox" id="opActualizar" checked> <span>Actualizar los registros existentes (nombre, precios, categoría…)</span></label>
              <label class="login-remember opt-art" style="display:flex;margin-bottom:6px"><input type="checkbox" id="opCategorias" checked> <span>Crear automáticamente las categorías que no existan</span></label>
              <label class="login-remember opt-art" style="display:flex"><input type="checkbox" id="opStock"> <span>Ajustar el stock de los existentes al valor del archivo (genera ajustes de inventario por conteo)</span></label>
            </div>
            <div class="table-responsive" style="max-height:420px;overflow:auto">
              <table class="table table-condensed table-bordered table-hover" id="tblPreview"><thead></thead><tbody></tbody></table>
            </div>
            <div class="form-actions-row">
              <button type="button" class="btn btn-success btn-lg" id="btnImportar"><i class="fa fa-upload"></i> Confirmar importación</button>
              <button type="button" class="btn btn-default" id="btnCancelar"><i class="fa fa-times"></i> Cancelar</button>
            </div>
          </div>
        </div>
        <div class="box" id="boxInicio">
          <div class="box-body">
            <div class="empty-state"><i class="fa fa-cloud-upload"></i><strong>Aquí verás la vista previa</strong>Sube un archivo para analizar qué se creará, qué se actualizará y qué filas tienen errores. Nada se guarda hasta que confirmes.</div>
          </div>
        </div>
        <div class="box" id="boxResultado" style="display:none">
          <div class="box-body"><div id="resultadoImport"></div><div class="mt-10"><button class="btn btn-primary" id="btnOtra"><i class="fa fa-plus"></i> Importar otro archivo</button> <a class="btn btn-default" id="linkVer" href="articulo.php"><i class="fa fa-list"></i> Ver listado</a></div></div>
        </div>
      </div>
    </div>
  </section>
</div>
<style>.step-n{display:inline-flex;width:24px;height:24px;border-radius:7px;background:var(--brand-primary);color:#fff;font-size:12px;align-items:center;justify-content:center;margin-right:6px}</style>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/importar.js?v=<?php echo e(APP_VERSION); ?>"></script>
