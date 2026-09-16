/* Punto de venta */
var tabla;
var tablaArticulos;
var empresaDefaults = { serie_boleta: "B001", serie_factura: "F001", serie_ticket: "T001", impuesto_default: 18, moneda: "PEN", simbolo_moneda: "S/" };
var posScanTimer = null;
var posCodeQueue = [];
var posProcessing = false;
var correlativoRequestId = 0;
var clientesCargados = false;
var numeroComprobanteManual = false;
var cont = 0;
var detalles = 0;
var enFormulario = false;

function money(v){ return window.appMoney ? window.appMoney(v, 2) : ((window.appCurrencySymbol || "S/") + " " + Number(v || 0).toFixed(2)); }

function fechaHoraActualInput(){
	var now = new Date();
	return now.getFullYear() + "-" + ("0" + (now.getMonth() + 1)).slice(-2) + "-" + ("0" + now.getDate()).slice(-2) + "T" + ("0" + now.getHours()).slice(-2) + ":" + ("0" + now.getMinutes()).slice(-2);
}

function init(){
	mostrarform(false);
	listar();
	cargarResumenDia();

	$("#formulario").on("submit", function(e){ guardaryeditar(e); });
	$("#btnFiltrarVenta").on("click", recargarListadoVenta);
	$("#btnLimpiarFiltroVenta").on("click", function(){
		$("#filtro_venta_inicio, #filtro_venta_fin, #filtro_venta_estado, #filtro_venta_pago").val("");
		recargarListadoVenta();
	});

	cargarClientes();
	cargarDefaultsEmpresa();

	$("#myModal").on("shown.bs.modal", function(){
		if (!tablaArticulos) {
			listarArticulos();
		} else {
			tablaArticulos.columns.adjust();
			tablaArticulos.ajax.reload(null, false);
		}
		$("#ventasItemsSeleccionadosModal").text(document.getElementsByName("idarticulo[]").length);
		setTimeout(function(){ $("#tblarticulos_filter input").focus(); }, 120);
	});

	$("#formClienteRapido").on("submit", guardarClienteRapido);
	$("#modalClienteVenta").on("shown.bs.modal", function(){ $("#cli_nombre").focus(); });
	$("#modalClienteVenta").on("hidden.bs.modal", limpiarFormClienteRapido);

	$("#num_comprobante").on("input", function(){ numeroComprobanteManual = true; });
	$("#serie_comprobante").on("change blur", cargarCorrelativoComprobante);
	$("#tipo_comprobante").on("change", aplicarSerieImpuesto);

	$("#btnBuscarCodigo").on("click", function(){ encolarCodigoPOS($("#codigo_rapido").val()); });
	$("#codigo_rapido").on("keypress", function(e){
		if (e.which === 13) { e.preventDefault(); clearTimeout(posScanTimer); encolarCodigoPOS($(this).val()); }
	});
	$("#codigo_rapido").on("input", function(){
		clearTimeout(posScanTimer);
		var codigoLeido = ($(this).val() || "").trim();
		if (!codigoLeido || codigoLeido.length < 3) { return; }
		posScanTimer = setTimeout(function(){ encolarCodigoPOS(codigoLeido); }, 260);
	});

	$(".pago-toggle .btn").on("click", function(){
		$(".pago-toggle .btn").removeClass("active");
		$(this).addClass("active");
		var tipo = $(this).data("pago");
		$("#tipo_pago").val(tipo);
		if (tipo === "CREDITO") {
			$("#grupoVencimiento").slideDown(120);
			if (!$("#fecha_vencimiento").val()) { $("#fecha_vencimiento").val(appFechaSumarDias(null, 30)); }
		} else {
			$("#grupoVencimiento").slideUp(120);
		}
	});

	$(document).on("keydown", function(e){
		if (!enFormulario) { return; }
		if (e.ctrlKey && (e.key === "b" || e.key === "B")) { e.preventDefault(); $("#codigo_rapido").focus().select(); }
		if (e.key === "F2") { e.preventDefault(); $("#myModal").modal("show"); }
		if (e.key === "F4") { e.preventDefault(); if ($("#btnGuardar").is(":visible") && !$("#btnGuardar").prop("disabled")) { $("#formulario").trigger("submit"); } }
		if (e.key === "Escape" && !$(".modal.in").length) { cancelarform(); }
	});

	if (window.appQueryParam && window.appQueryParam("nuevo") === "1") { mostrarform(true); }
	if (window.appQueryParam && window.appQueryParam("cotizacion")) { cargarDesdeCotizacion(parseInt(window.appQueryParam("cotizacion"), 10)); }
}

function cargarDesdeCotizacion(id){
	$.get("../ajax/cotizacion.php?op=paraVenta", { id: id }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { appNotify("error", (r && r.message) || "No se pudo cargar la cotización."); return; }
		mostrarform(true);
		$("#idcotizacion").val(r.idcotizacion);
		$("#observacion").val(r.observacion || "");
		if (r.impuesto > 0) { $("#tipo_comprobante").val("Factura"); aplicarSerieImpuesto(); $("#impuesto").val(Number(r.impuesto).toFixed(2)); }
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
			appNotify("info", "Cotización " + r.numero + " cargada. Revisa cantidades y registra la venta.", 5000);
			if (sinStock.length) { appNotify("warning", "Sin stock, no se agregaron: " + sinStock.join(", "), 8000); }
		});
	});
}

function cargarResumenDia(){
	$.get("../ajax/venta.php?op=resumenDia", function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { return; }
		$("#chipResumenDia span").text((r.comprobantes || 0) + " ventas · " + (r.monto_formateado || money(r.monto || 0)));
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

function limpiar(){
	$("#idventa, #serie_comprobante, #num_comprobante, #observacion, #fecha_vencimiento").val("");
	if (clientesCargados) {
		$("#idcliente").val($("#idcliente option:first").val() || "").selectpicker("refresh");
	}
	$("#impuesto").val("0");
	numeroComprobanteManual = false;
	$("#total_venta").val("");
	$("#detalles tbody").empty();
	cont = 0;
	detalles = 0;
	$("#fecha_hora").val(fechaHoraActualInput());
	$("#tipo_comprobante").val("Boleta");
	$(".pago-toggle .btn").removeClass("active").filter("[data-pago='CONTADO']").addClass("active");
	$("#tipo_pago").val("CONTADO");
	$("#medio_pago").val("EFECTIVO");
	$("#grupoVencimiento").hide();
	aplicarSerieImpuesto();
	calcularTotales();
}

function mostrarform(flag){
	limpiar();
	enFormulario = !!flag;
	if (flag) {
		$("#listadoregistros, #accionesListado").hide();
		$("#formularioregistros").show();
		setTimeout(function(){ $("#codigo_rapido").focus(); }, 80);
	} else {
		$("#listadoregistros, #accionesListado").show();
		$("#formularioregistros").hide();
	}
}

function cancelarform(){
	if (detalles > 0) {
		appConfirm("Hay artículos en la venta actual. ¿Descartar la venta?", function(){ limpiar(); mostrarform(false); }, { titulo: "Descartar venta", ok: "Sí, descartar", tipo: "danger" });
		return;
	}
	limpiar();
	mostrarform(false);
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
		"iDisplayLength": 10,
		// Lo ultimo vendido primero, ordenando por la fecha real
		"order": [[1, "desc"]],
		"columnDefs": [{ "orderable": false, "targets": [0] }, { "targets": [1], "render": window.appOrdenPorDato }]
	}).DataTable();
}

function listarArticulos(){
	tablaArticulos = $('#tblarticulos').dataTable({
		"aProcessing": true,
		"aServerSide": true,
		"autoWidth": false,
		dom: 'frtip',
		"ajax": { url: '../ajax/venta.php?op=listarArticulos', type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } },
		"bDestroy": true,
		"iDisplayLength": 8,
		"order": [[1, "asc"]],
		"columnDefs": [{ "orderable": false, "targets": [0, 7] }],
		"language": { "sSearchPlaceholder": "Buscar por nombre, código o categoría…" }
	}).DataTable();
}

function recargarListadoVenta(){
	var fi = ($("#filtro_venta_inicio").val() || "").trim();
	var ff = ($("#filtro_venta_fin").val() || "").trim();
	if (fi && ff && fi > ff) { appNotify("warning", "La fecha 'Desde' no puede ser mayor que 'Hasta'."); return; }
	if (tabla) { tabla.ajax.reload(); }
}

function guardaryeditar(e){
	e.preventDefault();
	if (!($("#idcliente").val() || "").toString().trim()) { appNotify("warning", "Selecciona un cliente antes de guardar."); return; }
	if (detalles <= 0) { appNotify("warning", "Agrega al menos un artículo a la venta."); $("#codigo_rapido").focus(); return; }
	if (!validarStockDetalleAntesGuardar()) { return; }
	if ($("#tipo_pago").val() === "CREDITO" && !$("#fecha_vencimiento").val()) { appNotify("warning", "Indica la fecha de vencimiento del crédito."); $("#fecha_vencimiento").focus(); return; }

	appSetLoading("#btnGuardar", true);
	$.ajax({
		url: "../ajax/venta.php?op=guardaryeditar",
		type: "POST",
		data: new FormData($("#formulario")[0]),
		contentType: false,
		processData: false,
		success: function(datos){
			appSetLoading("#btnGuardar", false);
			var r = appParseJson(datos, null);
			if (!r || typeof r.ok === "undefined") { appNotifyFromResponse(datos); return; }
			if (!r.ok) { appNotify("error", r.message || "No se pudo registrar la venta."); return; }
			appNotify("success", r.message || "Venta registrada correctamente");
			if (r.alertas && r.alertas.length > 0) {
				appNotify("warning", "Stock bajo: " + r.alertas.map(function(a){ return (a.nombre || "Artículo") + " (" + window.appCantidad(a.stock) + ")"; }).join(", "), 7000);
			}
			mostrarDialogoPostVenta(r);
			mostrarform(false);
			tabla.ajax.reload(null, false);
			cargarResumenDia();
			if (window.cargarAlertasBell) { window.cargarAlertasBell(); }
		},
		error: function(){ appSetLoading("#btnGuardar", false); }
	});
}

function mostrarDialogoPostVenta(r){
	var id = r.idventa;
	var doc = (r.tipo_comprobante || "") + " " + (r.serie_comprobante || "") + "-" + (r.num_comprobante || "");
	var urlPdf = "../reportes/exFactura.php?id=" + id;
	var urlTicket = "../reportes/exTicket.php?id=" + id;
	var extra = "";
	if (r.cuenta_cobrar) { extra += '<p class="text-soft"><i class="fa fa-calendar"></i> Se creó una cuenta por cobrar por ' + money(r.total) + '.</p>'; }
	if (r.caja_registrada) { extra += '<p class="text-soft"><i class="fa fa-money"></i> Ingreso registrado en tu caja abierta.</p>'; }
	bootbox.dialog({
		title: '<i class="fa fa-check-circle" style="color:#16a34a"></i> Venta registrada',
		message: '<div style="text-align:center"><div style="font-size:13px;color:#64748b">Comprobante</div><div style="font-size:22px;font-weight:800;margin:4px 0 8px">' + appEscapeHtml(doc) + '</div><div style="font-size:28px;font-weight:800;color:#0f766e">' + money(r.total) + '</div>' + extra + '</div>',
		closeButton: true,
		buttons: {
			ticket: { label: '<i class="fa fa-print"></i> Ticket', className: "btn-default", callback: function(){ window.open(urlTicket, "_blank"); } },
			pdf: { label: '<i class="fa fa-file-pdf-o"></i> PDF A4', className: "btn-info", callback: function(){ window.open(urlPdf, "_blank"); } },
			nueva: { label: '<i class="fa fa-plus"></i> Nueva venta', className: "btn-primary", callback: function(){ mostrarform(true); } }
		}
	});
}

function mostrar(idventa){
	$.post("../ajax/venta.php?op=mostrar", { idventa: idventa }, function(data){
		var d = appParseJson(data, null);
		if (!d) { appNotify("error", "No se pudo cargar la venta."); return; }
		var esTicket = d.tipo_comprobante === "Ticket";
		$("#detTitulo").text(d.tipo_comprobante + " " + d.serie_comprobante + "-" + d.num_comprobante);
		$("#detImprimir").attr("href", (esTicket ? "../reportes/exTicket.php?id=" : "../reportes/exFactura.php?id=") + idventa);
		var estado = d.estado === "Aceptado" ? '<span class="label bg-green">Aceptado</span>' : '<span class="label bg-red">Anulado</span>';
		var pago = (d.tipo_pago || "CONTADO") + " · " + (d.medio_pago || "EFECTIVO") + (d.tipo_pago === "CREDITO" && d.fecha_vencimiento ? " · vence " + d.fecha_vencimiento : "");
		$("#detCabecera").html(
			'<div class="col-sm-6"><p><strong>Cliente:</strong> ' + appEscapeHtml(d.cliente) + '</p><p><strong>Vendedor:</strong> ' + appEscapeHtml(d.usuario) + '</p><p><strong>Fecha:</strong> ' + appEscapeHtml(d.fecha) + '</p></div>' +
			'<div class="col-sm-6"><p><strong>Estado:</strong> ' + estado + '</p><p><strong>Pago:</strong> ' + appEscapeHtml(pago) + '</p><p><strong>Impuesto:</strong> ' + appEscapeHtml(d.impuesto) + ' % &nbsp; <strong>Total:</strong> <span class="money">' + money(d.total_venta) + '</span></p>' + (d.observacion ? '<p><strong>Obs.:</strong> ' + appEscapeHtml(d.observacion) + '</p>' : '') + '</div>'
		);
		$.post("../ajax/venta.php?op=listarDetalle&id=" + idventa, function(r){ $("#detTabla").html(r); });
		$("#modalDetalleVenta").modal("show");
	});
}

function anular(idventa){
	appConfirm("Se anulará la venta y el stock volverá al inventario. Esta acción queda registrada en auditoría. ¿Continuar?", function(){
		$.post("../ajax/venta.php?op=anular", { idventa: idventa }, function(e){
			appNotifyFromResponse(e);
			tabla.ajax.reload(null, false);
			cargarResumenDia();
		});
	}, { titulo: "Anular venta", ok: "Sí, anular", tipo: "danger" });
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
		$("#impuesto").val("0");
	} else {
		$("#serie_comprobante").val(empresaDefaults.serie_boleta || "B001");
		$("#impuesto").val("0");
	}
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
// Detalle de la venta
//
// Cada fila guarda en data-* la ficha del articulo (factor de la
// presentacion elegida, si admite decimales, precio base, escalas de precio
// por mayor). La cantidad se escribe en la presentacion elegida (2 cajas) y
// el stock se controla en unidades base (2 x 12 = 24 und), sumando todas las
// filas del mismo articulo.
// ---------------------------------------------------------------------

// Agrega un articulo pidiendo su ficha al servidor (catalogo, cotizacion).
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
		$('#myModal').modal('hide');
		appNotify("info", f.nombre + ": cantidad " + window.appCantidad($cant.val()));
		resaltarFila($existente);
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
		'<td><button type="button" class="btn btn-danger btn-xs btn-icon" onclick="eliminarDetalle(' + cont + ')" title="Quitar"><i class="fa fa-trash"></i></button></td>' +
		'<td><input type="hidden" name="idarticulo[]" value="' + f.idarticulo + '"><input type="hidden" name="idpresentacion[]" value="' + idPres + '"><input type="hidden" name="idvariante[]" value="' + idVar + '">' +
			'<strong>' + appEscapeHtml(f.nombre) + '</strong>' + selectorVariante + opcionesPres + '<small class="text-soft d-block">Stock: <span class="stock-fila">' + window.appCantidad(f.stock) + ' ' + appEscapeHtml(f.unidad) + '</span>' +
			(f.escalas && f.escalas.length ? ' · <span class="text-success" title="Tiene precio por mayor"><i class="fa fa-tags"></i> por mayor</span>' : '') +
			textoVencimiento(f) + '</small></td>' +
		'<td><span class="unidad-fila"></span></td>' +
		'<td><input type="number" min="0" name="cantidad[]" value="' + (opciones.cantidad || 1) + '" oninput="modificarSubtotales()" onblur="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="precio_venta[]" value="0.00" oninput="marcarPrecioManual(this)" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="descuento[]" value="' + Number(opciones.descuento || 0).toFixed(2) + '" oninput="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td class="text-right"><span class="money" name="subtotal">0.00</span></td>'
	);
	cont++;
	detalles++;
	$('#detalles tbody').append(fila);
	if (typeof opciones.precio === "number") {
		fila.find("[name='precio_venta[]']").val(opciones.precio.toFixed(2)).attr("data-manual", "1");
	}
	ajustarPasoCantidad(fila);
	etiquetaUnidadFila(fila);
	actualizarStockFila(fila);
	modificarSubtotales();
	$('#myModal').modal('hide');
	resaltarFila(fila);
	if (selectorVariante && !idVar) { fila.find(".sel-variante").focus(); }
	if (precioAutomatico(fila) <= 0 && typeof opciones.precio !== "number") { appNotify("warning", "El artículo no tiene precio de venta: ingrésalo en la fila.", 5000); }
	if (!opciones.silencioso) { setTimeout(function(){ $("#codigo_rapido").focus(); }, 50); }
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
		return;
	}
	$tr.find("[name='idvariante[]']").val(id);
	$tr.find("[name='precio_venta[]']").removeAttr("data-manual");
	actualizarStockFila($tr);
	modificarSubtotales();
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
	$tr.css("background", "#ecfeff");
	setTimeout(function(){ $tr.css("background", ""); }, 700);
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
	$("#total").html(money(total));
	$("#posTotal").text(money(total));
	$("#posUnidades").text(window.appCantidad(unidades));
	$("#posDescuentos").text(money(descuentos));
	$("#total_venta").val(total.toFixed(2));
	actualizarContadorItems();
	evaluar();
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
	calcularTotales();
}

function actualizarContadorItems(){
	var count = document.getElementsByName("idarticulo[]").length;
	$("#ventasItemsSeleccionados, #ventasItemsSeleccionadosModal").text(count);
}

function encolarCodigoPOS(codigo){
	var limpio = (codigo || "").trim();
	if (!limpio) { appNotify("warning", "Ingresa o escanea un código de producto."); return; }
	clearTimeout(posScanTimer);
	posCodeQueue.push(limpio);
	$("#codigo_rapido").val("");
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
