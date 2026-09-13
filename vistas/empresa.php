<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Empresa y marca";
$iconoPagina = "fa-building";
require 'header.php';
if (usuarioTienePermiso('empresa') || usuarioTienePermiso('acceso')) {
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Administración <i class="fa fa-chevron-right"></i> Empresa</div>
        <h1><span class="page-icon"><i class="fa fa-building"></i></span> Empresa y marca</h1>
        <p>Estos datos aparecen en el login, el panel, los comprobantes impresos y los reportes.</p>
      </div>
    </div>

    <form id="empresaForm" method="POST" enctype="multipart/form-data" autocomplete="off">
      <div class="row">
        <div class="col-lg-8 col-md-7">
          <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-id-card-o"></i> Datos de la empresa</h3></div>
            <div class="box-body">
              <div class="row">
                <div class="form-group col-md-6"><label>Nombre comercial <span class="req">*</span></label><input type="text" class="form-control" name="nombre_comercial" id="nombre_comercial" maxlength="120" required></div>
                <div class="form-group col-md-6"><label>Razón social</label><input type="text" class="form-control" name="razon_social" id="razon_social" maxlength="150"></div>
                <div class="form-group col-md-4"><label>RUC / NIT / ID fiscal</label><input type="text" class="form-control" name="ruc" id="ruc" maxlength="20"></div>
                <div class="form-group col-md-8"><label>Dirección</label><input type="text" class="form-control" name="direccion" id="direccion" maxlength="180"></div>
                <div class="form-group col-md-3"><label>Teléfono</label><input type="text" class="form-control" name="telefono" id="telefono" maxlength="30"></div>
                <div class="form-group col-md-3"><label>Celular / WhatsApp</label><input type="text" class="form-control" name="celular" id="celular" maxlength="30"></div>
                <div class="form-group col-md-3"><label>Correo</label><input type="email" class="form-control" name="correo" id="correo" maxlength="120"></div>
                <div class="form-group col-md-3"><label>Web</label><input type="text" class="form-control" name="web" id="web" maxlength="120" placeholder="https://…"></div>
              </div>
            </div>
          </div>

          <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-file-text-o"></i> Comprobantes e impuestos</h3></div>
            <div class="box-body">
              <div class="row">
                <div class="form-group col-md-3 col-sm-6"><label>Serie boleta</label><input type="text" class="form-control" name="serie_boleta" id="serie_boleta" maxlength="10" value="B001"></div>
                <div class="form-group col-md-3 col-sm-6"><label>Serie factura</label><input type="text" class="form-control" name="serie_factura" id="serie_factura" maxlength="10" value="F001"></div>
                <div class="form-group col-md-3 col-sm-6"><label>Serie ticket</label><input type="text" class="form-control" name="serie_ticket" id="serie_ticket" maxlength="10" value="T001"></div>
                <div class="form-group col-md-3 col-sm-6"><label>Serie cotización</label><input type="text" class="form-control" name="serie_cotizacion" id="serie_cotizacion" maxlength="6" value="COT"></div>
                <div class="form-group col-md-3 col-sm-6"><label>Impuesto por defecto (%)</label><input type="number" step="0.01" min="0" max="100" class="form-control" name="impuesto_default" id="impuesto_default" value="18.00"></div>
                <div class="form-group col-md-4 col-sm-6"><label>Moneda</label>
                  <select class="form-control" name="moneda" id="moneda">
                    <option value="PEN">PEN — Sol peruano (S/)</option>
                    <option value="USD">USD — Dólar ($)</option>
                    <option value="EUR">EUR — Euro (€)</option>
                    <option value="MXN">MXN — Peso mexicano</option>
                    <option value="COP">COP — Peso colombiano</option>
                    <option value="CLP">CLP — Peso chileno</option>
                    <option value="ARS">ARS — Peso argentino</option>
                    <option value="BOB">BOB — Boliviano</option>
                    <option value="UYU">UYU — Peso uruguayo</option>
                    <option value="PYG">PYG — Guaraní</option>
                    <option value="BRL">BRL — Real</option>
                    <option value="GTQ">GTQ — Quetzal</option>
                    <option value="CRC">CRC — Colón</option>
                    <option value="DOP">DOP — Peso dominicano</option>
                    <option value="HNL">HNL — Lempira</option>
                    <option value="NIO">NIO — Córdoba</option>
                  </select>
                </div>
                <div class="form-group col-md-8 col-sm-6"><label>Mensaje al pie del ticket</label><input type="text" class="form-control" name="mensaje_ticket" id="mensaje_ticket" maxlength="160" placeholder="Gracias por su compra"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4 col-md-5">
          <div class="box">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-paint-brush"></i> Marca</h3></div>
            <div class="box-body">
              <div class="form-group">
                <label>Logo (JPG, PNG, WEBP · máx. 3 MB)</label>
                <div style="text-align:center;padding:14px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;margin-bottom:8px">
                  <img id="logomuestra" src="../public/img/brand-store.svg" alt="logo" style="max-height:110px;max-width:100%;border-radius:10px;background:#fff;padding:6px">
                </div>
                <input type="file" class="form-control" name="logo" id="logo" accept="image/*">
                <input type="hidden" name="logoactual" id="logoactual">
              </div>
              <div class="row">
                <div class="form-group col-xs-6"><label>Color primario</label><input type="color" class="form-control" name="color_primario" id="color_primario" value="#0f766e" style="padding:3px;height:42px"></div>
                <div class="form-group col-xs-6"><label>Color secundario</label><input type="color" class="form-control" name="color_secundario" id="color_secundario" value="#f59e0b" style="padding:3px;height:42px"></div>
              </div>
              <div id="previewMarca" style="border-radius:12px;padding:14px;color:#fff;background:linear-gradient(135deg,#0f766e,#0b4f4a)">
                <div style="font-size:11px;letter-spacing:1px;opacity:.85">VISTA PREVIA</div>
                <div style="font-weight:800;font-size:16px" id="previewNombre">Mi Tienda</div>
                <span id="previewAccent" style="display:inline-block;margin-top:8px;padding:4px 10px;border-radius:999px;background:#f59e0b;color:#1f2937;font-size:11px;font-weight:700">Botón de acento</span>
              </div>
              <p class="help-block mt-10">Los colores se aplican al menú, botones y comprobantes.</p>
            </div>
          </div>
          <button class="btn btn-primary btn-lg w-100" type="submit" id="btnGuardarEmpresa"><i class="fa fa-save"></i> Guardar configuración</button>
        </div>
      </div>
    </form>
  </section>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="scripts/empresa.js?v=<?php echo e(APP_VERSION); ?>"></script>
