/* Punto de venta */
var tabla;
var empresaDefaults = { serie_boleta: "B001", serie_factura: "F001", serie_ticket: "T001", impuesto_default: 18, moneda: "PEN", simbolo_moneda: "S/" };
var posCodeQueue = [];
var posProcessing = false;
var correlativoRequestId = 0;
var clientesCargados = false;
var numeroComprobanteManual = false;
var cont = 0;
var detalles = 0;
var enFormulario = false;
var catalogo = [];
var categoriaActiva = 0;
var ticketCfg = { auto_imprimir: true, ancho: 80 };
var ultimaFila = null;
var cobrando = false;
var MAX_TILES = 80;
var cajaPos = null;          // estado de la caja del usuario (null = aun no se sabe)
var cobrarTrasAbrirCaja = false;

// El administrador puede vender sin caja; el resto necesita la suya abierta
function exigeCaja(){ return !(window.appUser && window.appUser.permisos && window.appUser.permisos.acceso); }
// Sin "Cambiar precios y descuentos" se vende al precio de lista (el servidor lo valida)
function puedeCambiarPrecios(){ return !!(window.appUser && window.appUser.permisos && window.appUser.permisos.precios); }

function money(v){ return window.appMoney ? window.appMoney(v, 2) : ((window.appCurrencySymbol || "S/") + " " + Number(v || 0).toFixed(2)); }

function fechaHoraActualInput(){
	var now = new Date();
	return now.getFullYear() + "-" + ("0" + (now.getMonth() + 1)).slice(-2) + "-" + ("0" + now.getDate()).slice(-2) + "T" + ("0" + now.getHours()).slice(-2) + ":" + ("0" + now.getMinutes()).slice(-2);
}

function init(){
	// Se leen antes de mostrarform(false), que limpia la barra de direcciones
	var abrirNuevo = window.appQueryParam && window.appQueryParam("nuevo") === "1";
	var idCotizacion = window.appQueryParam ? parseInt(window.appQueryParam("cotizacion"), 10) : 0;
	mostrarform(false);
	listar();
	cargarResumenDia();

	// El formulario nunca se envia solo: el cobro pasa por la ventana de cobro
	$("#formulario").on("submit", function(e){ e.preventDefault(); abrirCobro(); });
	$("#btnFiltrarVenta").on("click", recargarListadoVenta);
	$("#btnLimpiarFiltroVenta").on("click", function(){
		$("#filtro_venta_inicio, #filtro_venta_fin, #filtro_venta_estado, #filtro_venta_pago").val("");
		recargarListadoVenta();
	});

	cargarClientes();
	cargarDefaultsEmpresa();

	$("#formClienteRapido").on("submit", guardarClienteRapido);
	$("#modalClienteVenta").on("shown.bs.modal", function(){ $("#cli_nombre").focus(); });
	$("#modalClienteVenta").on("hidden.bs.modal", limpiarFormClienteRapido);

	$("#num_comprobante").on("input", function(){ numeroComprobanteManual = true; });
	$("#serie_comprobante").on("change blur", cargarCorrelativoComprobante);
	$(".pos-comprobante [data-comprobante]").on("click", function(){ fijarComprobante($(this).data("comprobante")); });
	$("#impuesto").on("input", calcularTotales);

	// Buscador: al escribir filtra la cuadricula; Enter agrega (lector de barras)
	var filtrarDiferido = appDebounce(pintarGrid, 120);
	$("#codigo_rapido").on("input", filtrarDiferido);
	$("#codigo_rapido").on("keydown", function(e){
		if (e.key === "Enter") { e.preventDefault(); agregarDesdeBuscador(); }
		else if ((e.key === "+" || e.key === "-") && !$(this).val()) { e.preventDefault(); pasoUltimaFila(e.key === "+" ? 1 : -1); }
	});
	$("#btnBuscarCodigo").on("click", agregarDesdeBuscador);
	$("#posGrid").on("click", ".pos-tile", function(){
		if ($(this).hasClass("agotado")) { appNotify("warning", "Este artículo no tiene stock disponible."); return; }
		agregarArticulo(parseInt($(this).data("id"), 10), 0);
	});
	$("#posCategorias").on("click", "[data-categoria]", function(){
		categoriaActiva = parseInt($(this).data("categoria"), 10) || 0;
		$("#posCategorias [data-categoria]").removeClass("active").attr("aria-selected", "false");
		$(this).addClass("active").attr("aria-selected", "true");
		pintarGrid();
	});

	// Ventana de cobro
	$("#modalCobro .pago-toggle .btn").on("click", function(){ fijarTipoPago($(this).data("pago")); });
	$("#medio_pago").on("change", actualizarCamposMedio);
	$("#monto_recibido").on("input", calcularVuelto);
	$("#cobroRapidos").on("click", "[data-monto]", function(){ $("#monto_recibido").val($(this).data("monto")); calcularVuelto(); $("#btnConfirmarCobro").focus(); });
	$("#modalCobro").on("shown.bs.modal", function(){
		if ($("#tipo_pago").val() === "CONTADO" && $("#medio_pago").val() === "EFECTIVO") { $("#monto_recibido").focus().select(); }
		else { $("#btnConfirmarCobro").focus(); }
	});
	$("#modalCobro").on("hidden.bs.modal", function(){ if (enFormulario) { $("#codigo_rapido").focus(); } });
	$("#modalCobro").on("keydown", "input", function(e){ if (e.key === "Enter") { e.preventDefault(); confirmarCobro(); } });
	$("#formAbrirCajaPos").on("submit", abrirCajaDesdePos);
	$("#formAutorizarAnulacion").on("submit", autorizarAnulacion);
	$("#posUltimaVenta").on("click", ".btn-anular-ultima", function(){ anular($(this).data("id"), $(this).data("doc")); });
	$("#modalAutorizarAnulacion").on("shown.bs.modal", function(){ $("#aut_motivo").focus(); });
	$("#modalAbrirCajaPos").on("shown.bs.modal", function(){ $("#pos_monto_apertura").focus(); });
	$("#chkImprimirTicket").on("change", function(){ appPreferenciaLocal("imprimir_ticket", this.checked ? "1" : "0"); });
	$("#detTicket").on("click", function(){ appImprimirTicket($(this).data("id")); });

	// Enter dentro del carrito no envia nada: vuelve al buscador
	$("#formulario").on("keydown", "input", function(e){
		if (e.key === "Enter" && this.id !== "codigo_rapido") { e.preventDefault(); modificarSubtotales(); $("#codigo_rapido").focus(); }
	});

	$(document).on("keydown", function(e){
		if (!enFormulario) { return; }
		var cobroAbierto = $("#modalCobro").hasClass("in");
		if (e.key === "F2" || e.key === "F3" || (e.ctrlKey && (e.key === "b" || e.key === "B"))) { e.preventDefault(); if (!cobroAbierto) { $("#codigo_rapido").focus().select(); } }
		if (e.key === "F4") { e.preventDefault(); if (cobroAbierto) { confirmarCobro(); } else { abrirCobro(); } }
		if (e.key === "Escape" && !$(".modal.in").length) { cancelarform(); }
		// + / - fuera de un campo: cambia la cantidad del ultimo producto agregado
		if ((e.key === "+" || e.key === "-") && !$(e.target).is("input, select, textarea") && !$(".modal.in").length) { e.preventDefault(); pasoUltimaFila(e.key === "+" ? 1 : -1); }
	});

	// El ticket termina de imprimirse en su iframe: el foco vuelve al buscador
	window.addEventListener("message", function(e){
		if (e.origin === window.location.origin && e.data === "ticket-impreso" && enFormulario) { $("#codigo_rapido").focus(); }
	});

	if (idCotizacion > 0) { cargarDesdeCotizacion(idCotizacion); }
	else if (abrirNuevo) { mostrarform(true); }
}

function cargarDesdeCotizacion(id){
	$.get("../ajax/cotizacion.php?op=paraVenta", { id: id }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { appNotify("error", (r && r.message) || "No se pudo cargar la cotización."); return; }
		mostrarform(true);
		$("#idcotizacion").val(r.idcotizacion);
		$("#observacion").val(r.observacion || "");
		if (r.impuesto > 0) { fijarComprobante("Factura"); }
		var fijarCliente = function(){ $("#idcliente").val(String(r.idcliente)).selectpicker("refresh"); };
		if (clientesCargados) { fijarCliente(); } else { setTimeout(fijarCliente, 700); }
		var sinStock = [];
		// Se agregan en orden, uno tras otro, respetando la presentacion y el precio cotizados
		var cadena = $.Deferred().resolve();
		(r.items || []).forEach(function(it){
			cadena = cadena.then(function(){
				if (it.stock <= 0) { sinStock.push(it.nombre); return; }
				return agregarArticulo(it.idarticulo, it.idpresentacion || 0, { cantidad: it.cantidad, precio: Number(it.precio), descuento: it.descuento, idvariante: it.idvariante || 0, silencioso: true });
			});
		});
		cadena.then(function(){
			modificarSubtotales();
			appNotify("info", "Cotización " + r.numero + " cargada. Revisa cantidades y cobra.", 5000);
			if (sinStock.length) { appNotify("warning", "Sin stock, no se agregaron: " + sinStock.join(", "), 8000); }
		});
	});
}

function cargarResumenDia(){
	$.get("../ajax/venta.php?op=resumenDia", function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { return; }
		$("#chipResumenDia span, #chipResumenDiaPos span").text((r.comprobantes || 0) + " ventas · " + (r.monto_formateado || money(r.monto || 0)));
	});
}

function cargarClientes(idPreferido){
	var idActual = (typeof idPreferido !== "undefined" && idPreferido !== null && idPreferido !== "") ? idPreferido : ($("#idcliente").val() || "");
	$.post("../ajax/venta.php?op=selectCliente", function(r){
		$("#idcliente").html(r);
		var existeActual = false;
		if (idActual !== "") {
			$("#idcliente option").each(function(){ if (String($(this).val()) === String(idActual)) { existeActual = true; return false; } });
		}
		$("#idcliente").val(existeActual ? String(idActual) : ($("#idcliente option:first").val() || "")).selectpicker("refresh");
		clientesCargados = true;
	});
}

function limpiarFormClienteRapido(){
	$("#cli_nombre, #cli_num_documento, #cli_direccion, #cli_telefono, #cli_email").val("");
	$("#cli_tipo_documento").val("DNI");
	appSetLoading("#btnGuardarClienteRapido", false);
}

function guardarClienteRapido(e){
	e.preventDefault();
	if (!$.trim($("#cli_nombre").val())) { appNotify("warning", "El nombre del cliente es obligatorio."); return; }
	appSetLoading("#btnGuardarClienteRapido", true);
	$.ajax({
		url: "../ajax/venta.php?op=crearClienteRapido",
		type: "POST",
		data: $("#formClienteRapido").serialize(),
		success: function(resp){
			var r = appParseJson(resp, { ok: false, message: "No se pudo registrar el cliente." });
			if (!r.ok) { appNotify("error", r.message || "No se pudo registrar el cliente."); appSetLoading("#btnGuardarClienteRapido", false); return; }
			appNotify("success", r.message || "Cliente registrado.");
			$("#modalClienteVenta").modal("hide");
			cargarClientes(r.idcliente || "");
		},
		error: function(){ appSetLoading("#btnGuardarClienteRapido", false); }
	});
}

function cargarDefaultsEmpresa(){
	$.get("../ajax/empresa.php?op=defaults", function(resp){
		var r = appParseJson(resp, null);
		if (r) {
			empresaDefaults.serie_boleta = r.serie_boleta || empresaDefaults.serie_boleta;
			empresaDefaults.serie_factura = r.serie_factura || empresaDefaults.serie_factura;
			empresaDefaults.serie_ticket = r.serie_ticket || empresaDefaults.serie_ticket;
			empresaDefaults.impuesto_default = parseFloat(r.impuesto_default || empresaDefaults.impuesto_default);
		}
		aplicarSerieImpuesto();
	});
}

// Deja lista una venta nueva sin salir del punto de venta
function limpiar(){
	$("#idventa, #idcotizacion, #serie_comprobante, #num_comprobante, #observacion, #fecha_vencimiento, #monto_recibido, #num_operacion, #codigo_rapido").val("");
	if (clientesCargados) {
		$("#idcliente").val($("#idcliente option:first").val() || "").selectpicker("refresh");
	}
	$("#impuesto").val("0");
	numeroComprobanteManual = false;
	$("#total_venta").val("");
	$("#detalles tbody").empty();
	cont = 0;
	detalles = 0;
	ultimaFila = null;
	$("#fecha_hora").val(fechaHoraActualInput());
	$(".pos-comprobante [data-comprobante]").removeClass("active").filter("[data-comprobante='Boleta']").addClass("active");
	$("#tipo_comprobante").val("Boleta");
	fijarTipoPago("CONTADO");
	appFijarMedioPago($("#modalCobro .medio-grid"), "EFECTIVO");
	aplicarSerieImpuesto();
	calcularTotales();
}

function mostrarform(flag){
	limpiar();
	enFormulario = !!flag;
	appModoCaja(enFormulario);
	if (flag) {
		$("#listadoregistros, #accionesListado").hide();
		$("#formularioregistros").show();
		cargarCatalogo();
		cargarCajaPos();
		setTimeout(function(){ $("#codigo_rapido").focus(); }, 80);
	} else {
		$("#listadoregistros, #accionesListado").show();
		$("#formularioregistros").hide();
		// Sin ?cotizacion=ID en la barra: recargar no debe volver a cargarla
		if (window.history && window.history.replaceState && window.location.search) { window.history.replaceState(null, "", window.location.pathname); }
	}
}

function cancelarform(){
	if (detalles > 0) {
		appConfirm("Hay productos en el carrito. ¿Salir del punto de venta y descartar la venta?", function(){ mostrarform(false); }, { titulo: "Salir del punto de venta", ok: "Sí, salir", tipo: "danger" });
		return;
	}
	mostrarform(false);
}

function vaciarCarrito(){
	if (detalles <= 0) { return; }
	appConfirm("Se quitarán todos los productos del carrito. ¿Continuar?", function(){
		$("#detalles tbody").empty();
		detalles = 0; cont = 0; ultimaFila = null;
		calcularTotales();
		$("#codigo_rapido").focus();
	}, { titulo: "Vaciar carrito", ok: "Sí, vaciar", tipo: "danger" });
}

function listar(){
	tabla = $('#tbllistado').dataTable({
		"aProcessing": true,
		"aServerSide": true,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Reporte de Ventas', true),
		"ajax": {
			url: '../ajax/venta.php?op=listar',
			type: "get",
			data: function(d){
				d.fecha_inicio = ($("#filtro_venta_inicio").val() || "").trim();
				d.fecha_fin = ($("#filtro_venta_fin").val() || "").trim();
				d.estado = $("#filtro_venta_estado").val() || "";
				d.tipo_pago = $("#filtro_venta_pago").val() || "";
			},
			dataType: "json",
			error: function(e){ console.log(e.responseText); }
		},
		"bDestroy": true,
		"iDisplayLength": 25,
		// Lo ultimo vendido primero, ordenando por la fecha real
		"order": [[1, "desc"]],
		"columnDefs": [{ "orderable": false, "targets": [0] }, { "targets": [1], "render": window.appOrdenPorDato }]
	}).DataTable();
}

function recargarListadoVenta(){
	var fi = ($("#filtro_venta_inicio").val() || "").trim();
	var ff = ($("#filtro_venta_fin").val() || "").trim();
	if (fi && ff && fi > ff) { appNotify("warning", "La fecha 'Desde' no puede ser mayor que 'Hasta'."); return; }
	if (tabla) { tabla.ajax.reload(); }
}

// ---------------------------------------------------------------------
// Cuadricula de productos
// ---------------------------------------------------------------------

function cargarCatalogo(){
	return $.get("../ajax/venta.php?op=catalogoPos", function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { $("#posGrid").html('<div class="pos-grid-vacio">No se pudo cargar el catálogo.</div>'); return; }
		catalogo = r.items || [];
		ticketCfg = r.ticket || ticketCfg;
		// La preferencia de esta PC manda sobre la de la empresa (ej. una caja sin ticketera)
		var pref = appPreferenciaLocal("imprimir_ticket");
		$("#chkImprimirTicket").prop("checked", pref === null ? !!ticketCfg.auto_imprimir : pref === "1");
		pintarCategorias();
		pintarGrid();
	});
}

function textoPlano(t){
	return String(t || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");
}

function pintarCategorias(){
	var vistas = {}, lista = [];
	catalogo.forEach(function(a){ if (!vistas[a.idcategoria]) { vistas[a.idcategoria] = true; lista.push({ id: a.idcategoria, nombre: a.categoria }); } });
	lista.sort(function(a, b){ return a.nombre.localeCompare(b.nombre); });
	if (categoriaActiva && !vistas[categoriaActiva]) { categoriaActiva = 0; }
	var html = '<button type="button" role="tab" data-categoria="0" class="' + (categoriaActiva === 0 ? "active" : "") + '">Todos <small>' + catalogo.length + '</small></button>';
	lista.forEach(function(c){
		html += '<button type="button" role="tab" data-categoria="' + c.id + '" class="' + (categoriaActiva === c.id ? "active" : "") + '">' + appEscapeHtml(c.nombre) + '</button>';
	});
	$("#posCategorias").html(html);
}

// Coincidencias del texto con nombre, codigo o categoria (sin tildes ni mayusculas)
function filtrarCatalogo(texto, conCategoria){
	var palabras = textoPlano(texto).split(/\s+/).filter(Boolean);
	return catalogo.filter(function(a){
		if (conCategoria && categoriaActiva && a.idcategoria !== categoriaActiva) { return false; }
		if (!palabras.length) { return true; }
		var base = textoPlano(a.nombre + " " + a.codigo + " " + a.categoria);
		return palabras.every(function(p){ return base.indexOf(p) !== -1; });
	});
}

function pintarGrid(){
	var texto = $.trim($("#codigo_rapido").val());
	var lista = filtrarCatalogo(texto, true);
	var enCarrito = cantidadesEnCarrito();
	var html = lista.slice(0, MAX_TILES).map(function(a){
		var agotado = a.stock <= 0;
		var bajo = !agotado && a.stock <= Math.max(a.stock_minimo, 5);
		var img = a.imagen
			? '<img src="../files/articulos/' + encodeURIComponent(a.imagen) + '" alt="" loading="lazy" onerror="this.parentNode.classList.add(\'sin-img\');this.remove()">'
			: '';
		var extra = a.variantes > 0 ? '<span class="pos-tile-tag">Tallas</span>' : (a.presentaciones > 0 ? '<span class="pos-tile-tag">Por caja</span>' : '');
		var cant = enCarrito[a.idarticulo] ? '<span class="pos-tile-cant">' + window.appCantidad(enCarrito[a.idarticulo]) + '</span>' : '';
		return '<button type="button" class="pos-tile' + (agotado ? " agotado" : "") + '" data-id="' + a.idarticulo + '" title="' + appEscapeHtml(a.nombre + (a.codigo ? " · " + a.codigo : "")) + '">' +
			'<span class="pos-tile-img' + (img ? "" : " sin-img") + '" data-inicial="' + appEscapeHtml(a.nombre.charAt(0).toUpperCase()) + '">' + img + '</span>' + cant + extra +
			'<span class="pos-tile-nombre">' + appEscapeHtml(a.nombre) + '</span>' +
			'<span class="pos-tile-pie"><strong>' + money(a.precio_venta) + '</strong><small class="' + (agotado ? "text-danger" : (bajo ? "text-warning" : "")) + '">' + (agotado ? "Agotado" : window.appCantidad(a.stock) + " " + appEscapeHtml(a.unidad)) + '</small></span>' +
		'</button>';
	}).join("");
	if (!lista.length) {
		html = '<div class="pos-grid-vacio"><i class="fa fa-search"></i> ' + (texto ? 'Nada coincide con "' + appEscapeHtml(texto) + '". Presiona Enter para buscarlo por código.' : 'No hay productos en esta categoría.') + '</div>';
	}
	$("#posGrid").html(html);
	$("#posGridPie").text(lista.length > MAX_TILES ? "Mostrando " + MAX_TILES + " de " + lista.length + " productos: escribe para encontrar el resto." : "");
}

function cantidadesEnCarrito(){
	var m = {};
	$("#detalles tbody tr.filas").each(function(){
		var id = parseInt($(this).attr("data-idarticulo"), 10);
		m[id] = (m[id] || 0) + (parseFloat($(this).find("[name='cantidad[]']").val()) || 0) * filaFactor($(this));
	});
	return m;
}

// Enter en el buscador: un solo producto que coincide entra directo; un codigo
// exacto o sin coincidencias va al servidor (codigos de caja y de talla/color).
function agregarDesdeBuscador(){
	var texto = $.trim($("#codigo_rapido").val());
	if (!texto) { appNotify("warning", "Escanea o escribe el código del producto."); return; }
	var exacto = catalogo.filter(function(a){ return a.codigo && a.codigo.toLowerCase() === texto.toLowerCase(); });
	var coincidencias = filtrarCatalogo(texto, false);
	if (exacto.length || !coincidencias.length) { encolarCodigoPOS(texto); return; }
	if (coincidencias.length === 1) {
		$("#codigo_rapido").val("");
		agregarArticulo(coincidencias[0].idarticulo, 0);
		pintarGrid();
		return;
	}
	appNotify("info", coincidencias.length + " productos coinciden: toca el que quieres.");
}

// ---------------------------------------------------------------------
// Cobro
// ---------------------------------------------------------------------

function fijarComprobante(tipo){
	$(".pos-comprobante [data-comprobante]").removeClass("active").filter("[data-comprobante='" + tipo + "']").addClass("active");
	$("#tipo_comprobante").val(tipo);
	aplicarSerieImpuesto();
	calcularTotales();
}

function fijarTipoPago(tipo){
	$("#modalCobro .pago-toggle .btn").removeClass("active").filter("[data-pago='" + tipo + "']").addClass("active");
	$("#tipo_pago").val(tipo);
	var credito = tipo === "CREDITO";
	$("#grupoVencimiento").toggle(credito);
	$("#grupoMedioPago").toggle(!credito);
	if (credito && !$("#fecha_vencimiento").val()) { $("#fecha_vencimiento").val(appFechaSumarDias(null, 30)); }
	$("#btnConfirmarCobro").html(credito ? '<i class="fa fa-check"></i> Registrar al crédito <small>(Enter)</small>' : '<i class="fa fa-check"></i> Confirmar cobro <small>(Enter)</small>');
}

function actualizarCamposMedio(){
	var efectivo = ($("#medio_pago").val() || "EFECTIVO") === "EFECTIVO";
	$("#grupoEfectivo").toggle(efectivo);
	$("#grupoOperacion").toggle(!efectivo);
	if ($("#modalCobro").hasClass("in")) { (efectivo ? $("#monto_recibido") : $("#num_operacion")).focus(); }
}

function totalVenta(){ return parseFloat($("#total_venta").val()) || 0; }

// Billetes para cobrar rapido: exacto y los redondeos que se suelen recibir
function pintarRapidos(){
	var total = totalVenta();
	var montos = [];
	[Math.ceil(total), Math.ceil(total / 10) * 10, Math.ceil(total / 20) * 20, Math.ceil(total / 50) * 50, Math.ceil(total / 100) * 100, 200].forEach(function(m){
		if (m > total + 0.001 && montos.indexOf(m) === -1) { montos.push(m); }
	});
	montos.sort(function(a, b){ return a - b; });
	var html = '<button type="button" data-monto="' + total.toFixed(2) + '">Exacto</button>';
	montos.slice(0, 4).forEach(function(m){ html += '<button type="button" data-monto="' + m.toFixed(2) + '">' + money(m) + '</button>'; });
	$("#cobroRapidos").html(html);
}

function calcularVuelto(){
	var total = totalVenta();
	var raw = $.trim($("#monto_recibido").val());
	var $v = $("#cobroVuelto").removeClass("falta ok");
	if (raw === "") { $v.find("span").text("Vuelto"); $v.find("strong").text("—"); return; }
	var recibido = parseFloat(raw) || 0;
	if (recibido + 0.001 < total) {
		$v.addClass("falta").find("span").text("Falta");
		$v.find("strong").text(money(total - recibido));
	} else {
		$v.addClass("ok").find("span").text("Vuelto");
		$v.find("strong").text(money(recibido - total));
	}
}

function abrirCobro(){
	if (cobrando) { return; }
	if (!($("#idcliente").val() || "").toString().trim()) { appNotify("warning", "Selecciona un cliente antes de cobrar."); return; }
	if (detalles <= 0) { appNotify("warning", "Agrega al menos un producto."); $("#codigo_rapido").focus(); return; }
	modificarSubtotales();
	if (!validarStockDetalleAntesGuardar()) { return; }
	if (exigeCaja() && cajaPos && !cajaPos.abierta) { pedirAbrirCaja(true); return; }
	$("#cobroTotal").text(money(totalVenta()));
	pintarRapidos();
	calcularVuelto();
	actualizarCamposMedio();
	$("#modalCobro").modal("show");
}

function confirmarCobro(){
	if (cobrando) { return; }
	var total = totalVenta();
	var contado = $("#tipo_pago").val() === "CONTADO";
	var efectivo = $("#medio_pago").val() === "EFECTIVO";
	if (!contado && !$("#fecha_vencimiento").val()) { appNotify("warning", "Indica la fecha de vencimiento del crédito."); $("#fecha_vencimiento").focus(); return; }
	if (contado && efectivo) {
		var raw = $.trim($("#monto_recibido").val());
		if (raw !== "" && (parseFloat(raw) || 0) + 0.001 < total) { appNotify("warning", "El monto recibido es menor que el total."); $("#monto_recibido").focus().select(); return; }
	}
	if (!(contado && efectivo)) { $("#monto_recibido").val(""); }
	if (!contado || efectivo) { $("#num_operacion").val(""); }
	guardaryeditar();
}

function guardaryeditar(){
	cobrando = true;
	appSetLoading("#btnConfirmarCobro", true);
	var recibido = parseFloat($("#monto_recibido").val()) || 0;
	var imprimir = $("#chkImprimirTicket").is(":checked");
	$.ajax({
		url: "../ajax/venta.php?op=guardaryeditar",
		type: "POST",
		// Incluye los campos de la ventana de cobro (atributo form="formulario")
		data: new FormData($("#formulario")[0]),
		contentType: false,
		processData: false,
		success: function(datos){
			var r = appParseJson(datos, null);
			if (!r || typeof r.ok === "undefined") { appNotifyFromResponse(datos); return; }
			if (!r.ok) { if (r.caja_cerrada) { $("#modalCobro").modal("hide"); cargarCajaPos(); pedirAbrirCaja(true); return; } appNotify("error", r.message || "No se pudo registrar la venta."); return; }
			var vuelto = recibido > 0 ? Math.max(0, recibido - (r.total || 0)) : 0;
			$("#modalCobro").modal("hide");
			if (imprimir) { appImprimirTicket(r.idventa); }
			appNotify("success", "Venta " + (r.serie_comprobante || "") + "-" + (r.num_comprobante || "") + " registrada" + (vuelto > 0 ? " · Vuelto " + money(vuelto) : ""), 5000);
			if (r.alertas && r.alertas.length > 0) {
				appNotify("warning", "Stock bajo: " + r.alertas.map(function(a){ return (a.nombre || "Artículo") + " (" + window.appCantidad(a.stock) + ")"; }).join(", "), 7000);
			}
			pintarUltimaVenta(r, vuelto);
			// Venta nueva en el mismo punto de venta, con el stock al dia
			limpiar();
			cargarCatalogo();
			tabla.ajax.reload(null, false);
			cargarCajaPos();
			cargarResumenDia();
			if (window.cargarAlertasBell) { window.cargarAlertasBell(); }
			if (window.history && window.history.replaceState && window.location.search) { window.history.replaceState(null, "", window.location.pathname + "?nuevo=1"); }
		},
		complete: function(){ cobrando = false; appSetLoading("#btnConfirmarCobro", false); }
	});
}

// ---------------------------------------------------------------------
// Caja del vendedor: se ve en la barra superior y se abre sin salir del POS
// ---------------------------------------------------------------------

function cargarCajaPos(){
	return $.get("../ajax/caja.php?op=estado", function(resp){
		cajaPos = appParseJson(resp, { abierta: false });
		var $c = $("#posCaja");
		if (cajaPos.abierta) {
			$c.attr("class", "pos-caja abierta").html(
				'<i class="fa fa-unlock"></i><span>Caja #' + parseInt(cajaPos.idcaja, 10) + (typeof cajaPos.sistema !== "undefined" ? ' · efectivo <strong>' + money(cajaPos.sistema) + '</strong>' : ' abierta') + '</span>' +
				'<a href="caja.php" class="btn btn-default btn-xs" title="Ver movimientos y cerrar la caja con arqueo"><i class="fa fa-lock"></i> Cerrar caja</a>'
			);
		} else {
			$c.attr("class", "pos-caja cerrada").html(
				'<i class="fa fa-lock"></i><span>Caja cerrada</span>' +
				'<button type="button" class="btn btn-success btn-xs" onclick="pedirAbrirCaja(false)"><i class="fa fa-unlock"></i> Abrir caja</button>'
			);
		}
	}).fail(function(){ $("#posCaja").empty(); });
}

function pedirAbrirCaja(paraCobrar){
	cobrarTrasAbrirCaja = !!paraCobrar;
	if (paraCobrar) { appNotify("warning", "Abre tu caja antes de cobrar."); }
	$("#pos_monto_apertura, #pos_obs_apertura").val("");
	$("#modalAbrirCajaPos").modal("show");
}

function abrirCajaDesdePos(e){
	e.preventDefault();
	var monto = $.trim($("#pos_monto_apertura").val());
	if (monto === "" || parseFloat(monto) < 0) { appNotify("warning", "Indica el efectivo inicial (puede ser 0)."); return; }
	appSetLoading("#btnAbrirCajaPos", true);
	$.post("../ajax/caja.php?op=abrir", { monto_apertura: monto, observacion: $("#pos_obs_apertura").val() }, function(txt){
		appNotifyFromResponse(txt);
		if (String(txt).toLowerCase().indexOf("correctamente") === -1) { return; }
		$("#modalAbrirCajaPos").modal("hide");
		cargarCajaPos().then(function(){ if (cobrarTrasAbrirCaja && detalles > 0) { cobrarTrasAbrirCaja = false; abrirCobro(); } });
	}).always(function(){ appSetLoading("#btnAbrirCajaPos", false); });
}

function pintarUltimaVenta(r, vuelto){
	var doc = (r.tipo_comprobante || "") + " " + (r.serie_comprobante || "") + "-" + (r.num_comprobante || "");
	var extra = [];
	if (vuelto > 0) { extra.push("Vuelto <strong>" + money(vuelto) + "</strong>"); }
	if (r.cuenta_cobrar) { extra.push("al crédito"); }
	$("#posUltimaVenta").html(
		'<i class="fa fa-check-circle"></i><span><strong>' + appEscapeHtml(doc) + '</strong> · ' + money(r.total) + (extra.length ? ' · ' + extra.join(" · ") : '') + '</span>' +
		'<button type="button" class="btn btn-default btn-xs" onclick="appImprimirTicket(' + parseInt(r.idventa, 10) + ')" title="Volver a imprimir el ticket"><i class="fa fa-print"></i> Ticket</button>' +
		'<a class="btn btn-default btn-xs" target="_blank" href="../reportes/exFactura.php?id=' + parseInt(r.idventa, 10) + '" title="Comprobante en PDF A4"><i class="fa fa-file-pdf-o"></i> A4</a>' +
		'<button type="button" class="btn btn-default btn-xs text-danger btn-anular-ultima" data-id="' + parseInt(r.idventa, 10) + '" data-doc="' + appEscapeHtml(doc) + '" title="Anular esta venta"><i class="fa fa-ban"></i> Anular</button>'
	).show();
}

function mostrar(idventa){
	$.post("../ajax/venta.php?op=mostrar", { idventa: idventa }, function(data){
		var d = appParseJson(data, null);
		if (!d) { appNotify("error", "No se pudo cargar la venta."); return; }
		$("#detTitulo").text(d.tipo_comprobante + " " + d.serie_comprobante + "-" + d.num_comprobante);
		$("#detImprimir").attr("href", "../reportes/exFactura.php?id=" + idventa);
		$("#detTicket").data("id", idventa);
		var estado = d.estado === "Aceptado" ? '<span class="label bg-green">Aceptado</span>' : '<span class="label bg-red">Anulado</span>';
		var medio = appMedioPagoTexto[d.medio_pago] || d.medio_pago || "Efectivo";
		var pago = d.tipo_pago === "CREDITO" ? "Crédito" + (d.fecha_vencimiento ? " · vence " + d.fecha_vencimiento : "") : "Contado · " + medio;
		var datosPago = "";
		if (d.num_operacion) { datosPago += '<p><strong>N° operación:</strong> ' + appEscapeHtml(d.num_operacion) + '</p>'; }
		if (parseFloat(d.monto_recibido) > 0) { datosPago += '<p><strong>Recibido:</strong> ' + money(d.monto_recibido) + ' &nbsp; <strong>Vuelto:</strong> ' + money(Math.max(0, d.monto_recibido - d.total_venta)) + '</p>'; }
		$("#detCabecera").html(
			'<div class="col-sm-6"><p><strong>Cliente:</strong> ' + appEscapeHtml(d.cliente) + '</p><p><strong>Vendedor:</strong> ' + appEscapeHtml(d.usuario) + '</p><p><strong>Fecha:</strong> ' + appEscapeHtml(d.fecha) + '</p></div>' +
			'<div class="col-sm-6"><p><strong>Estado:</strong> ' + estado + '</p><p><strong>Pago:</strong> ' + appEscapeHtml(pago) + '</p>' + datosPago + '<p><strong>Impuesto:</strong> ' + appEscapeHtml(d.impuesto) + ' % &nbsp; <strong>Total:</strong> <span class="money">' + money(d.total_venta) + '</span></p>' + (d.observacion ? '<p><strong>Obs.:</strong> ' + appEscapeHtml(d.observacion) + '</p>' : '') + '</div>'
		);
		$.post("../ajax/venta.php?op=listarDetalle&id=" + idventa, function(r){ $("#detTabla").html(r); });
		$("#modalDetalleVenta").modal("show");
	});
}

// Con permiso "Anular documentos" se confirma y listo; sin el, un encargado
// autoriza con su usuario y clave (el servidor lo verifica y lo audita).
function anular(idventa, documento){
	if (window.appUser && window.appUser.permisos && window.appUser.permisos.anular) {
		appConfirm("Se anulará la venta y el stock volverá al inventario. Esta acción queda registrada en auditoría. ¿Continuar?", function(){
			$.post("../ajax/venta.php?op=anular", { idventa: idventa }, function(e){
				appNotifyFromResponse(e);
				trasAnular(e);
			});
		}, { titulo: "Anular venta", ok: "Sí, anular", tipo: "danger" });
		return;
	}
	$("#aut_idventa").val(idventa);
	$("#autDocumento").text(documento || "");
	$("#aut_motivo, #aut_login, #aut_clave").val("");
	$("#modalAutorizarAnulacion").modal("show");
}

function autorizarAnulacion(e){
	e.preventDefault();
	var datos = { idventa: $("#aut_idventa").val(), motivo: $.trim($("#aut_motivo").val()), autoriza_login: $.trim($("#aut_login").val()), autoriza_clave: $("#aut_clave").val() };
	if (datos.motivo.length < 4) { appNotify("warning", "Escribe el motivo de la anulación."); $("#aut_motivo").focus(); return; }
	if (!datos.autoriza_login || !datos.autoriza_clave) { appNotify("warning", "Falta el usuario o la clave del encargado."); return; }
	appSetLoading("#btnAutorizarAnulacion", true);
	$.post("../ajax/venta.php?op=anular", datos, function(txt){
		appNotifyFromResponse(txt);
		if (String(txt).toLowerCase().indexOf("anulada") === -1) { $("#aut_clave").val("").focus(); return; }
		$("#modalAutorizarAnulacion").modal("hide");
		trasAnular(txt);
	}).always(function(){ appSetLoading("#btnAutorizarAnulacion", false); });
}

function trasAnular(respuesta){
	if (String(respuesta).toLowerCase().indexOf("anulada") === -1) { return; }
	tabla.ajax.reload(null, false);
	cargarResumenDia();
	if (enFormulario) { cargarCajaPos(); cargarCatalogo(); $("#posUltimaVenta").hide(); }
}

// Borrado definitivo (solo administrador). Si la venta seguía vigente el
// servidor devuelve el stock antes de borrarla; si ya estaba anulada no lo
// vuelve a tocar, porque la anulación ya lo repuso.
function eliminar(idventa){
	appConfirm("Se eliminará la venta de forma PERMANENTE, junto con su detalle, su cuenta por cobrar y sus movimientos de caja. Si la venta estaba vigente, el stock volverá al inventario. Esta acción no se puede deshacer. ¿Continuar?", function(){
		$.post("../ajax/venta.php?op=eliminar", { idventa: idventa }, function(e){
			appNotifyFromResponse(e);
			tabla.ajax.reload(null, false);
			cargarResumenDia();
		});
	}, { titulo: "Eliminar venta", ok: "Sí, eliminar", tipo: "danger" });
}

function aplicarSerieImpuesto(){
	var tipo = $("#tipo_comprobante").val();
	if (tipo === 'Factura') {
		$("#serie_comprobante").val(empresaDefaults.serie_factura || "F001");
		$("#impuesto").val((empresaDefaults.impuesto_default || 18).toFixed(2));
	} else if (tipo === 'Ticket') {
		$("#serie_comprobante").val(empresaDefaults.serie_ticket || "T001");
		$("#impuesto").val("0");   // nota de venta interna: sin desglose de IGV
	} else {
		$("#serie_comprobante").val(empresaDefaults.serie_boleta || "B001");
		// La boleta tambien lleva IGV (los precios ya lo incluyen; cambia el desglose)
		$("#impuesto").val((empresaDefaults.impuesto_default || 18).toFixed(2));
	}
	numeroComprobanteManual = false;
	cargarCorrelativoComprobante();
}

function cargarCorrelativoComprobante(){
	var tipo = ($("#tipo_comprobante").val() || "Boleta").trim();
	var serie = ($("#serie_comprobante").val() || "").trim();
	if (!serie) { $("#num_comprobante").val(""); return; }
	correlativoRequestId++;
	var reqId = correlativoRequestId;
	$.get("../ajax/venta.php?op=siguienteCorrelativo", { tipo_comprobante: tipo, serie_comprobante: serie }, function(resp){
		if (reqId !== correlativoRequestId) { return; }
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { return; }
		$("#serie_comprobante").val(r.serie_comprobante || serie);
		if (!numeroComprobanteManual || !$.trim($("#num_comprobante").val())) {
			$("#num_comprobante").val(r.numero || "");
			numeroComprobanteManual = false;
		}
	});
}

// ---------------------------------------------------------------------
// Detalle de la venta (carrito)
//
// Cada fila guarda en data-* la ficha del articulo (factor de la
// presentacion elegida, si admite decimales, precio base, escalas de precio
// por mayor). La cantidad se escribe en la presentacion elegida (2 cajas) y
// el stock se controla en unidades base (2 x 12 = 24 und), sumando todas las
// filas del mismo articulo.
// ---------------------------------------------------------------------

// Agrega un articulo pidiendo su ficha al servidor (cuadricula, cotizacion).
function agregarArticulo(idarticulo, idpresentacion, opciones){
	return $.post("../ajax/venta.php?op=infoArticulo", { idarticulo: idarticulo }, function(resp){
		var f = appParseJson(resp, null);
		if (!f || !f.ok) { appNotify("warning", (f && f.message) || "No se pudo agregar el artículo."); return; }
		agregarFicha(f, idpresentacion || 0, opciones);
	});
}

function presentacionDeFicha(f, idpresentacion){
	var id = parseInt(idpresentacion, 10) || 0;
	for (var i = 0; i < (f.presentaciones || []).length; i++) {
		if (f.presentaciones[i].idpresentacion === id) { return f.presentaciones[i]; }
	}
	return null;
}

function agregarFicha(f, idpresentacion, opciones){
	opciones = opciones || {};
	var pres = presentacionDeFicha(f, idpresentacion);
	var idPres = pres ? pres.idpresentacion : 0;
	if (f.stock <= 0) { appNotify("warning", f.stock_vencido > 0 ? "El stock de " + f.nombre + " está vencido: dale de baja en Vencimientos." : "Este artículo no tiene stock disponible."); return; }
	// Talla/color: la del codigo escaneado o la pedida; si no, el cajero la elige en la fila
	var variante = varianteDeFicha(f, opciones.idvariante || f.idvariante);
	var idVar = variante ? variante.idvariante : 0;
	if (variante && variante.stock <= 0) { appNotify("warning", f.nombre + " " + variante.etiqueta + " no tiene stock."); return; }

	// Si ya esta en la venta con la misma presentacion y talla/color, se suma a esa fila
	var $existente = $("#detalles tbody tr.filas").filter(function(){
		return parseInt($(this).attr("data-idarticulo"), 10) === f.idarticulo && (parseInt($(this).find("[name='idpresentacion[]']").val(), 10) || 0) === idPres &&
			(parseInt($(this).find("[name='idvariante[]']").val(), 10) || 0) === idVar;
	}).first();
	if ($existente.length) {
		var $cant = $existente.find("[name='cantidad[]']");
		$cant.val((parseFloat($cant.val()) || 0) + (opciones.cantidad || 1));
		modificarSubtotales();
		ultimaFila = $existente;
		resaltarFila($existente);
		if (!opciones.silencioso) { $("#codigo_rapido").focus(); }
		return;
	}

	var opcionesPres = "";
	if (f.presentaciones && f.presentaciones.length) {
		opcionesPres = '<select class="form-control input-sm sel-presentacion" onchange="cambiarPresentacion(this)">' +
			'<option value="0">' + appEscapeHtml(f.unidad) + '</option>' +
			f.presentaciones.map(function(p){
				return '<option value="' + p.idpresentacion + '"' + (p.idpresentacion === idPres ? " selected" : "") + '>' + appEscapeHtml(p.nombre) + ' (' + window.appCantidad(p.factor) + ' ' + appEscapeHtml(f.unidad) + ')</option>';
			}).join("") + '</select>';
	}

	var selectorVariante = htmlSelectorVariante(f, idVar, true);

	var fila = $('<tr class="filas" id="fila' + cont + '"></tr>');
	fila.attr({
		"data-idarticulo": f.idarticulo,
		"data-stock": f.stock,
		"data-fraccion": f.permite_fraccion ? "1" : "0",
		"data-unidad": f.unidad,
		"data-precio-base": f.precio_venta
	});
	fila.data("ficha", f);
	fila.html(
		'<td>' +
			'<input type="hidden" name="idarticulo[]" value="' + f.idarticulo + '"><input type="hidden" name="idpresentacion[]" value="' + idPres + '"><input type="hidden" name="idvariante[]" value="' + idVar + '">' +
			'<div class="cart-cab"><strong class="cart-nombre">' + appEscapeHtml(f.nombre) + '</strong>' +
				'<button type="button" class="cart-quitar" onclick="eliminarDetalle(' + cont + ')" title="Quitar del carrito"><i class="fa fa-times"></i></button></div>' +
			selectorVariante + opcionesPres +
			'<small class="text-soft d-block">Stock: <span class="stock-fila">' + window.appCantidad(f.stock) + ' ' + appEscapeHtml(f.unidad) + '</span>' +
				(f.escalas && f.escalas.length ? ' · <span class="text-success" title="Tiene precio por mayor"><i class="fa fa-tags"></i> por mayor</span>' : '') +
				textoVencimiento(f) + '</small>' +
			'<div class="cart-controles">' +
				'<div class="cart-cant">' +
					'<button type="button" onclick="pasoCantidad(this,-1)" title="Uno menos" aria-label="Uno menos">−</button>' +
					'<input type="number" min="0" name="cantidad[]" value="' + (opciones.cantidad || 1) + '" oninput="modificarSubtotales()" onblur="modificarSubtotales()" onfocus="this.select()" aria-label="Cantidad">' +
					'<button type="button" onclick="pasoCantidad(this,1)" title="Uno más" aria-label="Uno más">+</button>' +
				'</div>' +
				'<span class="unidad-fila"></span>' +
				'<label class="cart-precio" title="Precio unitario">x<input type="number" step="0.01" min="0" name="precio_venta[]" value="0.00" oninput="marcarPrecioManual(this)" onfocus="this.select()" aria-label="Precio"></label>' +
				'<label class="cart-dscto" title="Descuento en dinero para esta línea">Dscto<input type="number" step="0.01" min="0" name="descuento[]" value="' + Number(opciones.descuento || 0).toFixed(2) + '" oninput="modificarSubtotales()" onfocus="this.select()" aria-label="Descuento"></label>' +
				'<span class="money cart-sub" name="subtotal">0.00</span>' +
			'</div>' +
		'</td>'
	);
	cont++;
	detalles++;
	$('#detalles tbody').append(fila);
	if (typeof opciones.precio === "number") {
		fila.find("[name='precio_venta[]']").val(opciones.precio.toFixed(2)).attr("data-manual", "1");
	}
	if (!puedeCambiarPrecios()) {
		fila.find("[name='precio_venta[]'], [name='descuento[]']").prop("readonly", true).attr("title", "Precio de lista: tu usuario no puede cambiarlo");
		fila.find(".cart-dscto").hide();
	}
	ajustarPasoCantidad(fila);
	etiquetaUnidadFila(fila);
	actualizarStockFila(fila);
	modificarSubtotales();
	ultimaFila = fila;
	resaltarFila(fila);
	var $carrito = $(".pos-carrito");
	$carrito.scrollTop($carrito[0] ? $carrito[0].scrollHeight : 0);
	if (selectorVariante && !idVar) { fila.find(".sel-variante").focus(); }
	if (precioAutomatico(fila) <= 0 && typeof opciones.precio !== "number") { appNotify("warning", puedeCambiarPrecios() ? "El artículo no tiene precio de venta: ingrésalo en la fila." : "El artículo no tiene precio de venta: pide a un encargado que lo registre.", 5000); }
	if (!opciones.silencioso && !(selectorVariante && !idVar)) { setTimeout(function(){ $("#codigo_rapido").focus(); }, 50); }
}

// Botones - / + de la fila; en 0 la fila se quita
function pasoCantidad(boton, delta){
	var $tr = $(boton).closest("tr.filas");
	var $c = $tr.find("[name='cantidad[]']");
	var nueva = Math.round(((parseFloat($c.val()) || 0) + delta) * 1000) / 1000;
	if (nueva <= 0) { eliminarDetalle(String($tr.attr("id")).replace("fila", "")); return; }
	$c.val(nueva);
	modificarSubtotales();
	ultimaFila = $tr;
}

function pasoUltimaFila(delta){
	if (!ultimaFila || !ultimaFila.closest("body").length) { ultimaFila = $("#detalles tbody tr.filas").last(); }
	if (ultimaFila.length) { pasoCantidad(ultimaFila.find(".cart-cant button").first(), delta); }
}

// Aviso de vencimiento bajo el nombre: proximo lote y stock vencido que no se puede vender
function textoVencimiento(f){
	var html = "";
	if (f.proximo_vencimiento && f.proximo_vencimiento.fecha) {
		var dias = Math.round((new Date(f.proximo_vencimiento.fecha + "T00:00:00") - new Date(new Date().toDateString())) / 86400000);
		var clase = dias <= 7 ? "text-danger" : (dias <= 30 ? "text-warning" : "text-soft");
		html += ' · <span class="' + clase + '" title="Lote que sale primero"><i class="fa fa-calendar-times-o"></i> vence ' + f.proximo_vencimiento.fecha.split("-").reverse().join("/") + '</span>';
	}
	if (f.stock_vencido > 0) {
		html += ' · <span class="text-danger" title="No se puede vender"><i class="fa fa-ban"></i> ' + window.appCantidad(f.stock_vencido) + ' vencido</span>';
	}
	return html;
}

// ---------- Tallas y colores ----------

function varianteDeFicha(f, idvariante){
	var id = parseInt(idvariante, 10) || 0;
	for (var i = 0; i < (f.variantes || []).length; i++) {
		if (f.variantes[i].idvariante === id) { return f.variantes[i]; }
	}
	return null;
}

// Selector de talla/color de la fila (vacio si el articulo no tiene variantes)
function htmlSelectorVariante(f, idVar, mostrarStock){
	if (!f.variantes || !f.variantes.length) { return ""; }
	return '<select class="form-control input-sm sel-variante" onchange="cambiarVariante(this)" title="Talla y color">' +
		'<option value="0">— Elige talla / color —</option>' +
		f.variantes.map(function(v){
			var extra = mostrarStock ? (v.stock > 0 ? " · " + window.appCantidad(v.stock) : " · agotado") : "";
			return '<option value="' + v.idvariante + '"' + (v.idvariante === idVar ? " selected" : "") + (mostrarStock && v.stock <= 0 && v.idvariante !== idVar ? " disabled" : "") + '>' + appEscapeHtml(v.etiqueta) + extra + '</option>';
		}).join("") + '</select>';
}

function filaVariante($tr){
	return varianteDeFicha($tr.data("ficha") || {}, $tr.find("[name='idvariante[]']").val());
}

// El stock que limita la fila: el de la talla/color elegida, o el del articulo
function stockFila($tr){
	var v = filaVariante($tr);
	return v ? v.stock : (parseFloat($tr.attr("data-stock")) || 0);
}

function claveStockFila($tr){
	var v = filaVariante($tr);
	return v ? "v" + v.idvariante : "a" + $tr.attr("data-idarticulo");
}

function actualizarStockFila($tr){
	var v = filaVariante($tr);
	$tr.find(".stock-fila").text(window.appCantidad(stockFila($tr)) + " " + (($tr.data("ficha") || {}).unidad || "und") + (v ? " de " + v.etiqueta : ""));
}

function cambiarVariante(select){
	var $tr = $(select).closest("tr");
	var id = parseInt($(select).val(), 10) || 0;
	var f = $tr.data("ficha") || {};
	// Si esa talla/color ya esta en otra fila, se suma alli
	var $otra = $("#detalles tbody tr.filas").not($tr).filter(function(){
		return parseInt($(this).attr("data-idarticulo"), 10) === f.idarticulo && id > 0 &&
			(parseInt($(this).find("[name='idvariante[]']").val(), 10) || 0) === id &&
			$(this).find("[name='idpresentacion[]']").val() === $tr.find("[name='idpresentacion[]']").val();
	}).first();
	if ($otra.length) {
		var $c = $otra.find("[name='cantidad[]']");
		$c.val((parseFloat($c.val()) || 0) + (parseFloat($tr.find("[name='cantidad[]']").val()) || 1));
		$tr.remove(); detalles--;
		modificarSubtotales();
		resaltarFila($otra);
		ultimaFila = $otra;
		return;
	}
	$tr.find("[name='idvariante[]']").val(id);
	$tr.find("[name='precio_venta[]']").removeAttr("data-manual");
	actualizarStockFila($tr);
	modificarSubtotales();
	$("#codigo_rapido").focus();
}

function filaFactor($tr){
	var pres = presentacionDeFicha($tr.data("ficha") || {}, $tr.find("[name='idpresentacion[]']").val());
	return pres ? pres.factor : 1;
}

// Solo la unidad base admite decimales; cajas y paquetes van enteros
function filaPermiteFraccion($tr){
	return $tr.attr("data-fraccion") === "1" && (parseInt($tr.find("[name='idpresentacion[]']").val(), 10) || 0) === 0;
}

function ajustarPasoCantidad($tr){
	$tr.find("[name='cantidad[]']").attr("step", filaPermiteFraccion($tr) ? "0.001" : "1");
}

// Precio que corresponde a la fila: el de la presentacion, o el precio por
// mayor segun la cantidad, o el precio base.
function precioAutomatico($tr){
	var f = $tr.data("ficha") || {};
	var pres = presentacionDeFicha(f, $tr.find("[name='idpresentacion[]']").val());
	if (pres) { return pres.precio_venta > 0 ? pres.precio_venta : (f.precio_venta || 0) * pres.factor; }
	var cantidad = parseFloat($tr.find("[name='cantidad[]']").val()) || 0;
	var v = filaVariante($tr);
	var precio = v ? v.precio_venta : (f.precio_venta || 0);
	(f.escalas || []).forEach(function(es){ if (cantidad + 0.0005 >= es.cantidad_minima) { precio = es.precio; } });
	return precio;
}

function marcarPrecioManual(input){
	$(input).attr("data-manual", "1");
	modificarSubtotales();
}

function cambiarPresentacion(select){
	var $tr = $(select).closest("tr");
	$tr.find("[name='idpresentacion[]']").val($(select).val());
	$tr.find("[name='precio_venta[]']").removeAttr("data-manual");
	ajustarPasoCantidad($tr);
	etiquetaUnidadFila($tr);
	modificarSubtotales();
}

// Texto corto de la unidad de la fila: la abreviatura base o el nombre de la presentacion
function etiquetaUnidadFila($tr){
	var f = $tr.data("ficha") || {};
	var pres = presentacionDeFicha(f, $tr.find("[name='idpresentacion[]']").val());
	$tr.find(".unidad-fila").text(pres ? pres.nombre : (f.unidad || "und"));
}


function resaltarFila($tr){
	$tr.addClass("fila-nueva");
	setTimeout(function(){ $tr.removeClass("fila-nueva"); }, 900);
}

function modificarSubtotales(){
	var usadoPorArticulo = {};
	var huboAjusteStock = false;
	$("#detalles tbody tr.filas").each(function(){
		var $tr = $(this);
		var $cant = $tr.find("[name='cantidad[]']");
		var $precio = $tr.find("[name='precio_venta[]']");
		var $desc = $tr.find("[name='descuento[]']");
		var fraccion = filaPermiteFraccion($tr);
		var factor = filaFactor($tr);
		var idArt = claveStockFila($tr);
		var stock = stockFila($tr);

		var cantidad = window.appNormalizarCantidad($cant.val(), fraccion, fraccion ? 0.001 : 1);
		// Stock restante para esta fila, descontando lo que ya usan las filas anteriores del mismo articulo (o talla/color)
		var usado = usadoPorArticulo[idArt] || 0;
		var maxFila = (stock - usado) / factor;
		maxFila = fraccion ? Math.floor(maxFila * 1000 + 0.0005) / 1000 : Math.floor(maxFila + 0.0005);
		if (cantidad > maxFila) { cantidad = Math.max(maxFila, 0); huboAjusteStock = true; }
		// Mientras se escribe ("1." camino a "1.5") no se toca el campo; se corrige al salir de el
		var escribiendo = document.activeElement === $cant[0];
		if (!escribiendo || cantidad < (parseFloat($cant.val()) || 0)) {
			if (parseFloat($cant.val()) !== cantidad) { $cant.val(cantidad); }
		}
		usadoPorArticulo[idArt] = usado + cantidad * factor;

		if ($precio.attr("data-manual") !== "1") { $precio.val(Number(precioAutomatico($tr)).toFixed(2)); }
		var precio = parseFloat($precio.val() || 0);
		var descuento = parseFloat($desc.val() || 0);
		if (descuento < 0) { descuento = 0; $desc.val("0"); }
		var bruto = Math.round(cantidad * precio * 100) / 100;
		if (descuento > bruto) { descuento = bruto; $desc.val(bruto.toFixed(2)); }
		var s = bruto - descuento;
		var $sub = $tr.find("[name='subtotal']");
		$sub.text(s.toFixed(2)).attr("data-value", s.toFixed(2));
	});
	if (huboAjusteStock) { appNotify("warning", "Se ajustó la cantidad al stock disponible."); }
	calcularTotales();
}

function validarStockDetalleAntesGuardar(){
	var usado = {}, ok = true;
	$("#detalles tbody tr.filas").each(function(){
		var $tr = $(this);
		var cantidad = parseFloat($tr.find("[name='cantidad[]']").val()) || 0;
		var idArt = claveStockFila($tr);
		if ($tr.find(".sel-variante").length && !filaVariante($tr)) { appNotify("warning", "Elige la talla y el color de " + ($tr.data("ficha") || {}).nombre + "."); $tr.find(".sel-variante").focus(); ok = false; return false; }
		if (cantidad <= 0) { appNotify("warning", "Hay un artículo con cantidad inválida."); ok = false; return false; }
		if (parseFloat($tr.find("[name='precio_venta[]']").val() || 0) < 0) { appNotify("warning", "Hay un precio negativo."); ok = false; return false; }
		usado[idArt] = (usado[idArt] || 0) + cantidad * filaFactor($tr);
		if (usado[idArt] > stockFila($tr) + 0.0005) {
			appNotify("warning", "Hay un artículo con cantidad mayor al stock disponible."); ok = false; return false;
		}
	});
	return ok;
}

function calcularTotales(){
	var total = 0, unidades = 0, descuentos = 0;
	$("#detalles tbody tr.filas").each(function(){
		var $tr = $(this);
		total += parseFloat($tr.find("[name='subtotal']").attr("data-value") || 0);
		unidades += (parseFloat($tr.find("[name='cantidad[]']").val()) || 0) * filaFactor($tr);
		descuentos += parseFloat($tr.find("[name='descuento[]']").val() || 0);
	});
	total = Math.round(total * 100) / 100;
	var imp = parseFloat($("#impuesto").val()) || 0;
	$("#posTotal").text(money(total));
	$("#btnCobrarTotal").text(total > 0 ? money(total) : "");
	$("#posUnidades").text(window.appCantidad(unidades));
	$("#posDescuentos").text(money(descuentos));
	$("#filaDescuentos").toggle(descuentos > 0);
	$("#posIgv").text(money(total - total / (1 + imp / 100)));
	$("#filaIgv").toggle(imp > 0 && total > 0);
	$("#total_venta").val(total.toFixed(2));
	actualizarContadorItems();
	evaluar();
	actualizarCantidadesGrid();
}

// Globo con la cantidad en el carrito sobre cada producto de la cuadricula
function actualizarCantidadesGrid(){
	var enCarrito = cantidadesEnCarrito();
	$("#posGrid .pos-tile").each(function(){
		var id = parseInt($(this).data("id"), 10);
		var $b = $(this).find(".pos-tile-cant");
		if (enCarrito[id]) {
			if (!$b.length) { $b = $('<span class="pos-tile-cant"></span>').insertAfter($(this).find(".pos-tile-img")); }
			$b.text(window.appCantidad(enCarrito[id]));
		} else {
			$b.remove();
		}
	});
}

function evaluar(){
	if (detalles > 0) {
		$("#btnGuardar").prop("disabled", false);
		$("#detalleVacio").hide();
	} else {
		$("#btnGuardar").prop("disabled", true);
		$("#detalleVacio").show();
		cont = 0;
	}
}

function eliminarDetalle(indice){
	$("#fila" + indice).remove();
	detalles = detalles - 1;
	ultimaFila = null;
	calcularTotales();
	$("#codigo_rapido").focus();
}

function actualizarContadorItems(){
	var count = document.getElementsByName("idarticulo[]").length;
	$("#ventasItemsSeleccionados").text(count);
}

function encolarCodigoPOS(codigo){
	var limpio = (codigo || "").trim();
	if (!limpio) { appNotify("warning", "Ingresa o escanea un código de producto."); return; }
	posCodeQueue.push(limpio);
	$("#codigo_rapido").val("");
	pintarGrid();
	procesarColaPOS();
}

function procesarColaPOS(){
	if (posProcessing || posCodeQueue.length === 0) { return; }
	posProcessing = true;
	var codigo = posCodeQueue.shift();
	buscarCodigoRapido(codigo, function(){ posProcessing = false; procesarColaPOS(); });
}

function buscarCodigoRapido(codigo, callback){
	$.post("../ajax/venta.php?op=buscarArticuloCodigo", { codigo: codigo }, function(resp){
		var r = appParseJson(resp, null);
		if (!r) { appNotify("error", "No se pudo buscar el artículo."); }
		else if (!r.ok) { appNotify("warning", r.message || "No se encontró el artículo"); }
		else { agregarFicha(r, r.idpresentacion || 0, { idvariante: r.idvariante || 0 }); }
		$("#codigo_rapido").focus();
		if (typeof callback === "function") { callback(); }
	}).fail(function(){ if (typeof callback === "function") { callback(); } });
}

init();
