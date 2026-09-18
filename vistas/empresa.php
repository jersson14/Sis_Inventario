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
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-briefcase"></i> Tipo de negocio</h3>
            </div>
            <div class="box-body">
              <p class="text-soft" style="margin-top:0">Elige el rubro de tu tienda. Cada uno habilita las funciones que ese giro necesita y adapta los nombres de la interfaz.</p>
              <div class="rubro-grid">
<?php foreach (perfilesNegocio() as $clave => $def): ?>
                <label class="rubro-card" for="rubro_<?php echo e(strtolower($clave)); ?>">
                  <input type="radio" name="tipo_negocio" id="rubro_<?php echo e(strtolower($clave)); ?>" value="<?php echo e($clave); ?>">
                  <span class="rubro-icono"><i class="fa <?php echo e($def['icono']); ?>"></i></span>
                  <span class="rubro-datos">
                    <strong><?php echo e($def['nombre']); ?></strong>
                    <small><?php echo e($def['descripcion']); ?></small>
<?php if (!empty($def['capacidades'])): ?>
                    <span class="rubro-caps">
<?php   foreach ($def['capacidades'] as $cap): ?>
                      <span class="label bg-aqua" title="<?php echo e(capacidadesNegocio()[$cap]); ?>"><?php echo e(ucfirst(str_replace("_", " ", $cap))); ?></span>
<?php   endforeach; ?>
                    </span>
<?php endif; ?>
                  </span>
                </label>
<?php endforeach; ?>
              </div>
              <div class="row" id="grupoDiasVencimiento">
                <div class="form-group col-md-4 col-sm-6">
                  <label for="dias_alerta_vencimiento">Avisar vencimientos con</label>
                  <div class="input-group">
                    <input type="number" min="1" max="365" class="form-control" name="dias_alerta_vencimiento" id="dias_alerta_vencimiento" value="30">
                    <span class="input-group-addon">días de anticipación</span>
                  </div>
                  <span class="help-block">Solo en rubros con control de vencimientos (abarrotes).</span>
                </div>
              </div>
              <p class="text-soft" style="margin-bottom:0"><i class="fa fa-info-circle"></i> Cambiar de rubro no borra información: solo muestra u oculta campos. Los datos ya registrados se conservan.</p>
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
              </div>
            </div>
          </div>

          <div class="box" id="boxCaja">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-money"></i> Caja</h3></div>
            <div class="box-body">
              <div class="checkbox" style="margin:0"><label><input type="checkbox" name="arqueo_ciego" id="arqueo_ciego" value="1"> <strong>Arqueo ciego</strong> para vendedores y encargados</label></div>
              <p class="text-soft" style="margin:6px 0 0">Quien no es administrador cuenta el efectivo de su caja sin ver cuánto debería haber. El efectivo esperado y la diferencia solo los ve el administrador en <em>Caja diaria → Historial</em>. Así el conteo es honesto y los faltantes se detectan.</p>
            </div>
          </div>

          <div class="box" id="boxTicket">
            <div class="box-header with-border">
              <h3 class="box-title"><i class="fa fa-print"></i> Ticket e impresora</h3>
              <div class="box-tools"><a href="../reportes/exTicket.php?prueba=1" target="_blank" class="btn btn-default btn-sm" id="btnTicketPrueba" title="Abre un ticket de ejemplo con la configuración guardada"><i class="fa fa-eye"></i> Ver ticket de prueba</a></div>
            </div>
            <div class="box-body">
              <div class="row">
                <div class="form-group col-md-4 col-sm-6">
                  <label for="ticket_ancho">Ancho del papel</label>
                  <select class="form-control" name="ticket_ancho" id="ticket_ancho">
                    <option value="80">80 mm (ticketera estándar)</option>
                    <option value="58">58 mm (ticketera pequeña)</option>
                  </select>
                </div>
                <div class="form-group col-md-4 col-sm-6">
                  <label for="ticket_copias">Copias por venta</label>
                  <select class="form-control" name="ticket_copias" id="ticket_copias">
                    <option value="1">1 copia</option>
                    <option value="2">2 copias (cliente y tienda)</option>
                    <option value="3">3 copias</option>
                  </select>
                </div>
                <div class="form-group col-md-4 col-sm-12">
                  <label>Opciones</label>
                  <div class="checkbox" style="margin-top:4px"><label><input type="checkbox" name="ticket_auto_imprimir" id="ticket_auto_imprimir" value="1"> Imprimir solo al cobrar en el POS</label></div>
                  <div class="checkbox"><label><input type="checkbox" name="ticket_logo" id="ticket_logo" value="1"> Mostrar el logo</label></div>
                </div>
                <div class="form-group col-md-6"><label for="ticket_cabecera">Texto bajo el nombre de la tienda</label><input type="text" class="form-control" name="ticket_cabecera" id="ticket_cabecera" maxlength="200" placeholder="Ej.: Lun a Sáb 8am - 8pm · Síguenos en Facebook"></div>
                <div class="form-group col-md-6"><label for="mensaje_ticket">Mensaje al pie del ticket</label><input type="text" class="form-control" name="mensaje_ticket" id="mensaje_ticket" maxlength="160" placeholder="Gracias por su compra"></div>
                <div class="form-group col-md-12"><label for="ticket_leyenda">Aviso al pie (canje por comprobante electrónico)</label><input type="text" class="form-control" name="ticket_leyenda" id="ticket_leyenda" maxlength="250" placeholder="Vacío = sin aviso"></div>
                <div class="form-group col-md-4 col-sm-12">
                  <label>Código QR</label>
                  <div class="checkbox" style="margin-top:4px"><label><input type="checkbox" name="ticket_qr" id="ticket_qr" value="1"> Imprimir QR para que el cliente vea y descargue su comprobante</label></div>
                </div>
                <div class="form-group col-md-8 col-sm-12">
                  <label for="url_publica">Dirección pública del sistema</label>
                  <input type="url" class="form-control" name="url_publica" id="url_publica" maxlength="200" placeholder="Ej.: https://mitienda.pe">
                  <p class="help-block" id="urlPublicaAyuda">La dirección con la que el cliente abre el QR desde su celular. Vacía = la dirección con la que entras ahora.</p>
                </div>
              </div>
              <div class="callout-soft">
                <strong><i class="fa fa-info-circle"></i> Para que el ticket salga sin preguntar y abra el cajón:</strong>
                <ol style="margin:6px 0 0 18px;padding:0">
                  <li>Instala la ticketera y márcala como <em>impresora predeterminada</em> de Windows, con papel de 80 o 58 mm y márgenes en cero.</li>
                  <li>El <strong>cajón de dinero</strong> se abre desde el controlador de la ticketera: en <em>Preferencias de impresión</em> activa "Abrir cajón" (Cash drawer / Kick drawer) antes o después de imprimir.</li>
                  <li>Para imprimir sin la ventana de diálogo, abre el sistema en Chrome o Edge con la opción <code>--kiosk-printing</code> (acceso directo en la PC de caja). Sin esa opción el navegador mostrará la vista previa y solo tendrás que pulsar Enter.</li>
                </ol>
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
