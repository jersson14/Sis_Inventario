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

function normalizarEnteroNoNegativo(valor){
	var num = parseFloat(valor);
	if (!isFinite(num)) { return 0; }
	num = Math.round(num);
	return num < 0 ? 0 : num;
}

function normalizarCantidadEntera(valor, minimo){
	var num = normalizarEnteroNoNegativo(valor);
	return num < minimo ? minimo : num;
}

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
		"order": [[1, "desc"]],
		"columnDefs": [{ "orderable": false, "targets": [0] }]
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
				appNotify("warning", "Stock bajo: " + r.alertas.map(function(a){ return (a.nombre || "Artículo") + " (" + normalizarEnteroNoNegativo(a.stock) + ")"; }).join(", "), 7000);
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

function agregarDetalle(idarticulo, articulo, precio_venta, unidad, stockDisponible){
	var unidadTexto = unidad || "und";
	var stockDisponibleNum = normalizarEnteroNoNegativo(stockDisponible || 0);
	var articulos = document.getElementsByName("idarticulo[]");
	var cantidades = document.getElementsByName("cantidad[]");
	var stocksDisponibles = document.getElementsByName("stock_disponible[]");
	if (stockDisponibleNum <= 0) { appNotify("warning", "Este artículo no tiene stock disponible."); return; }
	if (!idarticulo) { appNotify("warning", "No se pudo agregar el artículo."); return; }

	for (var i = 0; i < articulos.length; i++) {
		if (parseInt(articulos[i].value, 10) === parseInt(idarticulo, 10)) {
			var stockFila = normalizarEnteroNoNegativo((stocksDisponibles[i] && stocksDisponibles[i].value) ? stocksDisponibles[i].value : stockDisponibleNum);
			var nueva = normalizarCantidadEntera(parseFloat(cantidades[i].value || 0) + 1, 1);
			if (nueva > stockFila) { appNotify("warning", "No puedes vender más de " + stockFila + " " + unidadTexto + " de este artículo."); return; }
			cantidades[i].value = nueva;
			modificarSubtotales();
			$('#myModal').modal('hide');
			appNotify("info", articulo + ": cantidad " + nueva);
			resaltarFila($(cantidades[i]).closest("tr"));
			return;
		}
	}
	var precio = parseFloat(precio_venta) || 0;
	var fila = '<tr class="filas" id="fila' + cont + '">' +
		'<td><button type="button" class="btn btn-danger btn-xs btn-icon" onclick="eliminarDetalle(' + cont + ')" title="Quitar"><i class="fa fa-trash"></i></button></td>' +
		'<td><input type="hidden" name="idarticulo[]" value="' + parseInt(idarticulo, 10) + '"><input type="hidden" name="stock_disponible[]" value="' + stockDisponibleNum + '"><strong>' + appEscapeHtml(articulo) + '</strong><br><small class="text-soft">Stock: ' + stockDisponibleNum + '</small></td>' +
		'<td>' + appEscapeHtml(unidadTexto) + '</td>' +
		'<td><input type="number" step="1" min="1" max="' + stockDisponibleNum + '" name="cantidad[]" value="1" oninput="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="precio_venta[]" value="' + precio.toFixed(2) + '" oninput="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="descuento[]" value="0" oninput="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td class="text-right"><span class="money" name="subtotal">' + precio.toFixed(2) + '</span></td>' +
		'<td></td>' +
		'</tr>';
	cont++;
	detalles++;
	$('#detalles tbody').append(fila);
	modificarSubtotales();
	$('#myModal').modal('hide');
	resaltarFila($("#fila" + (cont - 1)));
	if (precio <= 0) { appNotify("warning", "El artículo no tiene precio de venta: ingrésalo en la fila.", 5000); }
	setTimeout(function(){ $("#codigo_rapido").focus(); }, 50);
}

function resaltarFila($tr){
	$tr.css("background", "#ecfeff");
	setTimeout(function(){ $tr.css("background", ""); }, 700);
}

function modificarSubtotales(){
	var cant = document.getElementsByName("cantidad[]");
	var prev = document.getElementsByName("precio_venta[]");
	var desc = document.getElementsByName("descuento[]");
	var sub = document.getElementsByName("subtotal");
	var stockDisp = document.getElementsByName("stock_disponible[]");
	var huboAjusteStock = false;
	for (var i = 0; i < cant.length; i++) {
		var maxStock = normalizarEnteroNoNegativo((stockDisp[i] && stockDisp[i].value) ? stockDisp[i].value : 0);
		var cantidadActual = normalizarCantidadEntera(cant[i].value, 1);
		if (maxStock > 0 && cantidadActual > maxStock) { cantidadActual = maxStock; huboAjusteStock = true; }
		cant[i].value = cantidadActual;
		var precio = parseFloat(prev[i].value || 0);
		var descuento = parseFloat(desc[i].value || 0);
		if (descuento < 0) { descuento = 0; desc[i].value = "0"; }
		var bruto = cantidadActual * precio;
		if (descuento > bruto) { descuento = bruto; desc[i].value = bruto.toFixed(2); }
		var s = bruto - descuento;
		sub[i].textContent = s.toFixed(2);
		sub[i].setAttribute("data-value", s.toFixed(2));
	}
	if (huboAjusteStock) { appNotify("warning", "Se ajustó la cantidad al stock disponible."); }
	calcularTotales();
}

function validarStockDetalleAntesGuardar(){
	var cant = document.getElementsByName("cantidad[]");
	var stockDisp = document.getElementsByName("stock_disponible[]");
	var precios = document.getElementsByName("precio_venta[]");
	for (var i = 0; i < cant.length; i++) {
		var cantidad = normalizarCantidadEntera(cant[i].value, 1);
		var stock = normalizarEnteroNoNegativo((stockDisp[i] && stockDisp[i].value) ? stockDisp[i].value : 0);
		cant[i].value = cantidad;
		if (cantidad <= 0) { appNotify("warning", "Hay un artículo con cantidad inválida."); return false; }
		if (cantidad > stock) { appNotify("warning", "Hay un artículo con cantidad mayor al stock disponible."); return false; }
		if (parseFloat(precios[i].value || 0) < 0) { appNotify("warning", "Hay un precio negativo."); return false; }
	}
	return true;
}

function calcularTotales(){
	var sub = document.getElementsByName("subtotal");
	var cant = document.getElementsByName("cantidad[]");
	var desc = document.getElementsByName("descuento[]");
	var total = 0, unidades = 0, descuentos = 0;
	for (var i = 0; i < sub.length; i++) { total += parseFloat(sub[i].getAttribute("data-value") || sub[i].textContent || 0); }
	for (var j = 0; j < cant.length; j++) { unidades += parseInt(cant[j].value || 0, 10); descuentos += parseFloat(desc[j].value || 0); }
	$("#total").html(money(total));
	$("#posTotal").text(money(total));
	$("#posUnidades").text(unidades);
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
		else { agregarDetalle(r.idarticulo, r.nombre, r.precio_venta || 0, r.unidad || "und", r.stock || 0); }
		$("#codigo_rapido").focus();
		if (typeof callback === "function") { callback(); }
	}).fail(function(){ if (typeof callback === "function") { callback(); } });
}

init();
