<?php
require_once "../config/seguridad.php";
requiereLogin(false);
$tituloPagina = "Ventas";
$iconoPagina = "fa-shopping-cart";
require 'header.php';
if (usuarioTienePermiso('ventas')) {
  $simbolo = obtenerSimboloMoneda();
?>
<div class="content-wrapper">
  <section class="content">
    <div class="page-head">
      <div>
        <div class="breadcrumb-app"><a href="escritorio.php">Inicio</a> <i class="fa fa-chevron-right"></i> Ventas <i class="fa fa-chevron-right"></i> Punto de venta</div>
        <h1><span class="page-icon"><i class="fa fa-shopping-cart"></i></span> Ventas</h1>
        <p>Registra ventas al contado o al crédito, imprime el ticket y controla tu caja.</p>
      </div>
      <div class="page-actions" id="accionesListado">
        <div class="chip chip-primary" id="chipResumenDia" title="Ventas aceptadas de hoy"><i class="fa fa-calendar-check-o"></i> Hoy: <span>—</span></div>
        <a href="caja.php" class="btn btn-default" title="Ir a caja diaria"><i class="fa fa-money"></i> Caja</a>
        <button class="btn btn-primary" onclick="mostrarform(true)" id="btnagregar"><i class="fa fa-desktop"></i> Abrir punto de venta <small style="opacity:.7">(Alt+V)</small></button>
      </div>
    </div>

    <!-- LISTADO -->
    <div class="box" id="listadoregistros">
      <div class="box-body">
        <div class="table-toolbar">
          <div class="form-group">
            <label>Desde</label>
            <input type="date" id="filtro_venta_inicio" class="form-control input-sm">
          </div>
          <div class="form-group">
            <label>Hasta</label>
            <input type="date" id="filtro_venta_fin" class="form-control input-sm">
          </div>
          <div class="form-group">
            <label>Estado</label>
            <select id="filtro_venta_estado" class="form-control input-sm">
              <option value="">Todos</option>
              <option value="Aceptado">Aceptadas</option>
              <option value="Anulado">Anuladas</option>
            </select>
          </div>
          <div class="form-group">
            <label>Tipo de pago</label>
            <select id="filtro_venta_pago" class="form-control input-sm">
              <option value="">Todos</option>
              <option value="CONTADO">Contado</option>
              <option value="CREDITO">Crédito</option>
            </select>
          </div>
          <div class="toolbar-actions">
            <button type="button" class="btn btn-primary btn-sm" id="btnFiltrarVenta"><i class="fa fa-filter"></i> Filtrar</button>
            <button type="button" class="btn btn-default btn-sm" id="btnLimpiarFiltroVenta"><i class="fa fa-eraser"></i> Limpiar</button>
          </div>
        </div>
        <div class="table-responsive">
          <table id="tbllistado" class="table table-striped table-bordered table-hover" style="width:100%">
            <thead>
              <tr>
                <th>Opciones</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Vendedor</th>
                <th>Comprobante</th>
                <th>Número</th>
                <th>Total</th>
                <th>Pago</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PUNTO DE VENTA (pantalla completa) -->
    <div id="formularioregistros" class="caja-pantalla">
      <form action="" name="formulario" id="formulario" method="POST" autocomplete="off">
        <input type="hidden" name="idventa" id="idventa">
        <input type="hidden" name="idcotizacion" id="idcotizacion" value="">
        <input type="hidden" name="total_venta" id="total_venta">

        <div class="caja-topbar">
          <div class="caja-topbar-titulo"><span class="page-icon"><i class="fa fa-desktop"></i></span> Punto de venta</div>
          <div class="chip chip-primary" id="chipResumenDiaPos" title="<?php echo puedeVerTodasLasVentas() ? 'Ventas aceptadas de hoy' : 'Tus ventas aceptadas de hoy'; ?>"><i class="fa fa-calendar-check-o"></i> Hoy: <span>—</span></div>
          <div class="pos-caja" id="posCaja"></div>
<?php require_once "../modelos/Stock.php"; if (Stock::multiAlmacen()) { $almPos = Stock::almacenActual(); ?>
          <div class="dropdown pos-almacen">
<?php   if (usuarioTienePermiso('almacenes')) { ?>
            <a href="#" class="chip chip-almacen dropdown-toggle" data-toggle="dropdown" title="Se vende y descuenta stock de este almacén. Clic para cambiar."><i class="fa fa-building-o"></i> <?php echo e(Stock::nombre($almPos)); ?> <i class="fa fa-caret-down"></i></a>
            <ul class="dropdown-menu">
<?php     foreach (Stock::almacenes() as $alP) { ?>
              <li><a href="#" class="cambiar-almacen<?php echo (int)$alP['idalmacen'] === $almPos ? ' activo' : ''; ?>" data-id="<?php echo (int)$alP['idalmacen']; ?>"><i class="fa <?php echo (int)$alP['idalmacen'] === $almPos ? 'fa-check-circle' : 'fa-circle-thin'; ?>"></i> <?php echo e(html_entity_decode($alP['nombre'], ENT_QUOTES, 'UTF-8')); ?></a></li>
<?php     } ?>
            </ul>
<?php   } else { ?>
            <span class="chip chip-almacen" title="Se vende y descuenta stock de este almacén"><i class="fa fa-building-o"></i> <?php echo e(Stock::nombre($almPos)); ?></span>
<?php   } ?>
          </div>
<?php } ?>
          <div class="caja-topbar-acciones">
            <span class="caja-usuario hidden-xs"><i class="fa fa-user-circle"></i> <?php echo e($_SESSION['nombre']); ?></span>
            <button type="button" class="btn btn-default btn-sm btn-pantalla-completa" onclick="appAlternarPantallaCompleta()" title="Pantalla completa (F11)"><i class="fa fa-expand"></i></button>
            <button type="button" class="btn btn-default btn-sm" onclick="cancelarform()" title="Volver al listado de ventas (Esc)"><i class="fa fa-list"></i> Ventas</button>
          </div>
        </div>

        <div class="pos-app">
          <!-- Catalogo -->
          <section class="pos-catalogo" aria-label="Productos">
            <div class="pos-buscar">
              <i class="fa fa-barcode"></i>
              <input type="text" class="form-control input-lg" id="codigo_rapido" placeholder="Escanea el código o busca por nombre (F3)" autocomplete="off" aria-label="Buscar producto">
              <button class="btn btn-success btn-lg" type="button" id="btnBuscarCodigo" title="Agregar por código (Enter)"><i class="fa fa-plus"></i><span class="hidden-xs"> Agregar</span></button>
            </div>
            <div class="pos-categorias" id="posCategorias" role="tablist" aria-label="Categorías"></div>
            <div class="pos-grid" id="posGrid">
              <div class="pos-grid-vacio"><i class="fa fa-spinner fa-spin"></i> Cargando productos…</div>
            </div>
            <div class="pos-grid-pie" id="posGridPie"></div>
          </section>

          <!-- Ticket en curso -->
          <aside class="pos-ticket" aria-label="Venta en curso">
            <div class="pos-ultima" id="posUltimaVenta" style="display:none"></div>

            <div class="pos-ticket-cab">
              <div class="pos-cliente">
                <select name="idcliente" id="idcliente" class="form-control selectpicker" data-live-search="true" data-width="100%" data-size="8" required></select>
                <button type="button" class="btn btn-default" id="btnNuevoCliente" data-toggle="modal" data-target="#modalClienteVenta" title="Registrar cliente rápido"><i class="fa fa-user-plus"></i></button>
              </div>
              <div class="pos-comprobante" role="group" aria-label="Tipo de comprobante">
                <button type="button" class="active" data-comprobante="Boleta">Boleta</button>
                <button type="button" data-comprobante="Factura">Factura</button>
                <button type="button" data-comprobante="Ticket">Ticket</button>
                <button type="button" class="pos-mas" data-toggle="collapse" data-target="#posMasDatos" aria-expanded="false" title="Serie, número, fecha e impuesto"><i class="fa fa-sliders"></i></button>
              </div>
              <input type="hidden" name="tipo_comprobante" id="tipo_comprobante" value="Boleta">
              <div class="collapse" id="posMasDatos">
                <div class="pos-mas-grid">
                  <div><label for="serie_comprobante">Serie</label><input class="form-control input-sm" type="text" name="serie_comprobante" id="serie_comprobante" maxlength="7" placeholder="B001"></div>
                  <div><label for="num_comprobante">Número <small class="text-soft">(automático)</small></label><input class="form-control input-sm" type="text" name="num_comprobante" id="num_comprobante" maxlength="10" placeholder="Automático" readonly title="El número lo asigna el sistema en orden, sin saltos"></div>
                  <div><label for="impuesto">IGV %</label><input class="form-control input-sm" type="number" step="0.01" min="0" max="100" name="impuesto" id="impuesto" value="0" readonly title="Boleta y factura usan el IGV configurado en Empresa; la nota de venta no desglosa IGV"></div>
                  <div class="pos-mas-fecha"><label for="fecha_hora">Fecha y hora</label><input class="form-control input-sm" type="datetime-local" name="fecha_hora" id="fecha_hora" required></div>
                </div>
              </div>
            </div>

            <div class="pos-carrito">
              <table id="detalles" class="table tabla-carrito">
                <tbody></tbody>
              </table>
              <div class="empty-state" id="detalleVacio">
                <i class="fa fa-shopping-basket"></i>
                <strong>Carrito vacío</strong>
                Toca un producto o escanea su código.
              </div>
            </div>

            <div class="pos-totales">
              <div class="pos-tot-fila"><span>Artículos</span><span><span id="ventasItemsSeleccionados">0</span> · <span id="posUnidades">0</span> und</span></div>
              <div class="pos-tot-fila" id="filaDescuentos" style="display:none"><span>Descuentos</span><span id="posDescuentos"><?php echo e($simbolo); ?> 0.00</span></div>
              <div class="pos-tot-fila" id="filaIgv" style="display:none"><span>IGV incluido</span><span id="posIgv"><?php echo e($simbolo); ?> 0.00</span></div>
              <div class="pos-tot-total"><span>Total</span><span id="posTotal"><?php echo e(formatearMoneda(0)); ?></span></div>
              <div class="pos-tot-acciones">
                <button class="btn btn-default btn-lg" type="button" onclick="vaciarCarrito()" id="btnCancelar" title="Vaciar el carrito"><i class="fa fa-trash-o"></i></button>
                <button class="btn btn-success btn-lg btn-cobrar" type="button" id="btnGuardar" onclick="abrirCobro()"><i class="fa fa-money"></i> Cobrar <span id="btnCobrarTotal"></span> <small>(F4)</small></button>
              </div>
              <div class="pos-shortcuts"><kbd>F3</kbd> buscar &nbsp; <kbd>F4</kbd> cobrar &nbsp; <kbd>+</kbd>/<kbd>-</kbd> cantidad del último &nbsp; <kbd>Esc</kbd> salir</div>
            </div>
          </aside>
        </div>
      </form>
    </div>
  </section>
</div>

<!-- Modal de cobro: sus campos pertenecen a #formulario (atributo form) -->
<div class="modal fade modal-cobro" id="modalCobro" tabindex="-1" role="dialog" aria-labelledby="cobroTitulo" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="cobroTitulo"><i class="fa fa-money"></i> Cobrar</h4>
      </div>
      <div class="modal-body">
        <div class="cobro-total">
          <small>Total a cobrar</small>
          <strong id="cobroTotal"><?php echo e(formatearMoneda(0)); ?></strong>
        </div>

        <div class="pago-toggle cobro-tipo">
          <button type="button" class="btn btn-default active" data-pago="CONTADO"><i class="fa fa-money"></i> Contado</button>
          <button type="button" class="btn btn-default" data-pago="CREDITO"><i class="fa fa-calendar"></i> Crédito</button>
        </div>
        <input type="hidden" name="tipo_pago" id="tipo_pago" value="CONTADO" form="formulario">

        <div id="grupoMedioPago">
          <div class="medio-grid" data-target="#medio_pago">
            <button type="button" class="active" data-medio="EFECTIVO"><i class="fa fa-money"></i>Efectivo</button>
            <button type="button" data-medio="YAPE"><i class="fa fa-mobile"></i>Yape</button>
            <button type="button" data-medio="PLIN"><i class="fa fa-mobile"></i>Plin</button>
            <button type="button" data-medio="TARJETA"><i class="fa fa-credit-card"></i>Tarjeta</button>
            <button type="button" data-medio="TRANSFERENCIA"><i class="fa fa-exchange"></i>Transferencia</button>
            <button type="button" data-medio="DEPOSITO"><i class="fa fa-university"></i>Depósito</button>
          </div>
          <input type="hidden" name="medio_pago" id="medio_pago" value="EFECTIVO" form="formulario">

          <div id="grupoEfectivo">
            <label for="monto_recibido">Recibido del cliente</label>
            <input type="number" step="0.01" min="0" class="form-control input-lg cobro-recibido" name="monto_recibido" id="monto_recibido" form="formulario" placeholder="Monto exacto" inputmode="decimal">
            <div class="cobro-rapidos" id="cobroRapidos"></div>
            <div class="cobro-vuelto" id="cobroVuelto"><span>Vuelto</span><strong>—</strong></div>
          </div>
          <div id="grupoOperacion" style="display:none">
            <label for="num_operacion">N° de operación <small class="text-soft">(opcional)</small></label>
            <input type="text" class="form-control input-lg" name="num_operacion" id="num_operacion" form="formulario" maxlength="40" placeholder="Del voucher o de la app">
          </div>
          <button type="button" class="btn btn-link cobro-link" id="btnPagoMixto" title="Parte en efectivo y parte con Yape, tarjeta…"><i class="fa fa-random"></i> Pagar con varios medios</button>
        </div>

        <div id="grupoVencimiento" style="display:none">
          <label for="fecha_vencimiento">Fecha de vencimiento del crédito</label>
          <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control input-lg" form="formulario">
          <small class="text-soft">Se creará una cuenta por cobrar a nombre del cliente por el saldo.</small>
          <button type="button" class="btn btn-link cobro-link" id="btnAdelanto" title="El cliente deja una parte ahora"><i class="fa fa-plus-circle"></i> Registrar un adelanto</button>
        </div>

        <!-- Pago mixto (contado) o adelanto (credito): una linea por medio -->
        <div id="grupoMixto" class="cobro-mixto" style="display:none">
          <div class="cobro-mixto-cab"><strong id="mixTitulo">Formas de pago</strong><button type="button" class="btn btn-link btn-xs" id="btnMixtoSalir" title="Volver a un solo medio de pago"><i class="fa fa-times"></i> Quitar</button></div>
          <div id="pagosLista"></div>
          <button type="button" class="btn btn-default btn-sm" id="btnAgregarPago"><i class="fa fa-plus"></i> Agregar otro medio</button>
          <div class="cobro-mixto-resumen">
            <div><span>Pagado</span><strong id="mixPagado">—</strong></div>
            <div id="mixFaltaBox"><span id="mixFaltaTxt">Falta</span><strong id="mixFalta">—</strong></div>
            <div><span>Vuelto</span><strong id="mixVuelto">—</strong></div>
          </div>
        </div>

        <div class="form-group cobro-obs">
          <label for="observacion">Observación</label>
          <input type="text" name="observacion" id="observacion" class="form-control" maxlength="200" placeholder="Opcional" form="formulario">
        </div>

        <div class="checkbox cobro-imprimir">
          <label><input type="checkbox" id="chkImprimirTicket"> <i class="fa fa-print"></i> Imprimir ticket al cobrar</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default btn-lg" data-dismiss="modal">Volver</button>
        <button type="button" class="btn btn-success btn-lg" id="btnConfirmarCobro" onclick="confirmarCobro()"><i class="fa fa-check"></i> Confirmar cobro <small>(Enter)</small></button>
      </div>
    </div>
  </div>
</div>

<!-- Abrir caja sin salir del punto de venta -->
<div class="modal fade" id="modalAbrirCajaPos" tabindex="-1" role="dialog" aria-labelledby="tituloAbrirCajaPos">
  <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
    <form id="formAbrirCajaPos" autocomplete="off">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button><h4 class="modal-title" id="tituloAbrirCajaPos"><i class="fa fa-unlock"></i> Abrir caja</h4></div>
      <div class="modal-body">
        <p class="text-soft" style="margin-top:0">Para vender necesitas tu caja abierta: así cada venta al contado entra a tu cierre.</p>
        <div class="form-group"><label for="pos_monto_apertura">Efectivo inicial en el cajón <span class="req">*</span></label><input type="number" step="0.01" min="0" class="form-control input-lg" name="monto_apertura" id="pos_monto_apertura" placeholder="0.00" required inputmode="decimal"></div>
        <div class="form-group"><label for="pos_obs_apertura">Observación</label><input type="text" class="form-control" name="observacion" id="pos_obs_apertura" maxlength="200" placeholder="Opcional"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success" id="btnAbrirCajaPos"><i class="fa fa-unlock"></i> Abrir caja</button></div>
    </form>
  </div></div>
</div>

<!-- Anular con autorizacion de un encargado (usuarios sin permiso de anular) -->
<div class="modal fade" id="modalAutorizarAnulacion" tabindex="-1" role="dialog" aria-labelledby="tituloAutorizarAnulacion">
  <div class="modal-dialog" role="document" style="max-width:440px"><div class="modal-content">
    <form id="formAutorizarAnulacion" autocomplete="off">
      <input type="hidden" id="aut_idventa">
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button><h4 class="modal-title" id="tituloAutorizarAnulacion"><i class="fa fa-ban"></i> Anular venta <span id="autDocumento"></span></h4></div>
      <div class="modal-body">
        <p class="text-soft" style="margin-top:0">El stock vuelve al inventario y, si tu caja sigue abierta, se descuenta el cobro. Un encargado debe autorizarlo con su usuario y clave.</p>
        <div class="form-group"><label for="aut_motivo">Motivo <span class="req">*</span></label><input type="text" class="form-control" id="aut_motivo" maxlength="150" placeholder="Ej.: cliente devolvió el producto" required></div>
        <div class="row">
          <div class="form-group col-sm-6"><label for="aut_login">Usuario del encargado <span class="req">*</span></label><input type="text" class="form-control" id="aut_login" maxlength="60" autocomplete="off" autocapitalize="off" spellcheck="false" required></div>
          <div class="form-group col-sm-6"><label for="aut_clave">Clave <span class="req">*</span></label><input type="password" class="form-control" id="aut_clave" maxlength="64" autocomplete="new-password" required></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger" id="btnAutorizarAnulacion"><i class="fa fa-ban"></i> Autorizar y anular</button></div>
    </form>
  </div></div>
</div>

<!-- Modal cliente rápido -->
<div class="modal fade" id="modalClienteVenta" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formClienteRapido" autocomplete="off">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title"><i class="fa fa-user-plus"></i> Nuevo cliente</h4>
        </div>
        <div class="modal-body">
          <div class="form-group"><label>Nombre <span class="req">*</span></label><input type="text" class="form-control" name="nombre" id="cli_nombre" maxlength="100" required></div>
          <div class="row">
            <div class="form-group col-xs-5"><label>Tipo doc.</label><select class="form-control" name="tipo_documento" id="cli_tipo_documento"><option value="DNI">DNI</option><option value="RUC">RUC</option><option value="CEDULA">Cédula</option></select></div>
            <div class="form-group col-xs-7"><label>N° documento</label><input type="text" class="form-control" name="num_documento" id="cli_num_documento" maxlength="20"></div>
          </div>
          <div class="form-group"><label>Dirección</label><input type="text" class="form-control" name="direccion" id="cli_direccion" maxlength="70"></div>
          <div class="row">
            <div class="form-group col-xs-6"><label>Teléfono</label><input type="text" class="form-control" name="telefono" id="cli_telefono" maxlength="20"></div>
            <div class="form-group col-xs-6"><label>Email</label><input type="email" class="form-control" name="email" id="cli_email" maxlength="50"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
          <button type="submit" class="btn btn-primary" id="btnGuardarClienteRapido"><i class="fa fa-save"></i> Guardar cliente</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal detalle de venta -->
<div class="modal fade" id="modalDetalleVenta" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-file-text-o"></i> Detalle de venta <span id="detTitulo"></span></h4>
      </div>
      <div class="modal-body">
        <div class="row" id="detCabecera"></div>
        <div class="table-responsive">
          <table class="table table-bordered table-condensed" id="detTabla"></table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="detDevolver" class="btn btn-warning" title="Devolver productos de esta venta (nota de crédito)"><i class="fa fa-undo"></i> Devolver</button>
        <button type="button" id="detTicket" class="btn btn-default"><i class="fa fa-print"></i> Ticket</button>
        <a id="detImprimir" href="#" target="_blank" class="btn btn-info"><i class="fa fa-file-pdf-o"></i> PDF A4</a>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Devolucion: nota de credito por items -->
<div class="modal fade" id="modalDevolucion" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="formDevolucion" autocomplete="off">
        <input type="hidden" name="idventa" id="dev_idventa">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title"><i class="fa fa-undo"></i> Devolución <span id="devTitulo"></span></h4>
        </div>
        <div class="modal-body">
          <div class="text-soft" id="devDatos" style="margin-bottom:8px"></div>
          <div id="devNotas" class="callout-soft" style="display:none;margin-bottom:10px"></div>
          <div class="table-responsive">
            <table class="table table-bordered table-condensed dev-tabla" id="devTabla">
              <thead><tr><th>Producto</th><th class="text-right">Vendido</th><th class="text-right">Ya devuelto</th><th class="text-right" style="width:170px">A devolver</th><th class="text-center" title="Desmarca si vuelve dañado: no regresa a la venta">¿Vuelve a stock?</th><th class="text-right">Importe</th></tr></thead>
              <tbody></tbody>
              <tfoot><tr><th colspan="5" class="text-right">Total a devolver</th><th class="text-right"><span class="money" id="devTotal">—</span></th></tr></tfoot>
            </table>
          </div>
          <div class="row">
            <div class="form-group col-sm-6">
              <label for="dev_motivo_tipo">Motivo <span class="req">*</span></label>
              <select id="dev_motivo_tipo" class="form-control">
                <option value="Cliente se arrepintió">Cliente se arrepintió</option>
                <option value="Producto defectuoso">Producto defectuoso</option>
                <option value="Cambio por otro producto">Cambio por otro producto</option>
                <option value="Error en la venta">Error en la venta</option>
                <option value="Otro">Otro</option>
              </select>
            </div>
            <div class="form-group col-sm-6">
              <label for="dev_motivo_det">Detalle</label>
              <input type="text" id="dev_motivo_det" class="form-control" maxlength="150" placeholder="Opcional (obligatorio si es Otro)">
            </div>
            <div class="form-group col-sm-6">
              <label for="dev_reintegro">¿Cómo se devuelve el dinero? <span class="req">*</span></label>
              <select name="reintegro" id="dev_reintegro" class="form-control">
                <option value="EFECTIVO">Efectivo (sale de la caja)</option>
                <option value="YAPE">Yape</option>
                <option value="PLIN">Plin</option>
                <option value="TARJETA">Extorno a tarjeta</option>
                <option value="TRANSFERENCIA">Transferencia</option>
                <option value="DEPOSITO">Depósito</option>
                <option value="SALDO_A_FAVOR">Saldo a favor del cliente (para otra compra)</option>
              </select>
              <p class="help-block" id="devDeuda" style="display:none"></p>
            </div>
            <div class="col-sm-6" id="devAutoriza" style="display:none">
              <label>Autoriza un encargado</label>
              <div class="row">
                <div class="col-xs-6"><input type="text" name="autoriza_login" class="form-control" placeholder="Usuario" autocomplete="off"></div>
                <div class="col-xs-6"><input type="password" name="autoriza_clave" class="form-control" placeholder="Clave" autocomplete="new-password"></div>
              </div>
              <p class="help-block">Tu usuario no tiene el permiso "Anular documentos".</p>
            </div>
          </div>
          <div class="checkbox"><label><input type="checkbox" id="devImprimir" checked> <i class="fa fa-print"></i> Imprimir el comprobante de la devolución</label></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning" id="btnDevolver"><i class="fa fa-undo"></i> Registrar devolución</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
} else {
  require 'noacceso.php';
}
require 'footer.php';
?>
<script src="../public/js/app-pos.js?v=<?php echo e(APP_VERSION); ?>"></script>
<script src="scripts/venta.js?v=<?php echo e(APP_VERSION); ?>"></script>
<script src="scripts/devolucion.js?v=<?php echo e(APP_VERSION); ?>"></script>
