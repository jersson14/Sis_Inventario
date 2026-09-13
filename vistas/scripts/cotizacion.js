/* Cotizaciones */
var tabla, tablaArticulos;
var cont = 0, detalles = 0, enFormulario = false, clientesCargados = false;

function money(v){ return window.appMoney ? window.appMoney(v, 2) : Number(v || 0).toFixed(2); }
function ahoraInput(){ var n = new Date(); return n.getFullYear() + "-" + ("0" + (n.getMonth() + 1)).slice(-2) + "-" + ("0" + n.getDate()).slice(-2) + "T" + ("0" + n.getHours()).slice(-2) + ":" + ("0" + n.getMinutes()).slice(-2); }

function init(){
	mostrarform(false);
	listar();
	cargarResumen();
	cargarClientes();
	$("#formulario").on("submit", guardar);
	$("#btnFiltrar").on("click", function(){ tabla.ajax.reload(); });
	$("#btnLimpiar").on("click", function(){ $("#f_inicio, #f_fin, #f_estado").val(""); tabla.ajax.reload(); });
	$("#myModal").on("shown.bs.modal", function(){
		if (!tablaArticulos) { listarArticulos(); } else { tablaArticulos.columns.adjust(); tablaArticulos.ajax.reload(null, false); }
		setTimeout(function(){ $("#tblarticulos_filter input").focus(); }, 120);
	});
	$("#btnBuscarCodigo").on("click", function(){ buscarCodigo($("#codigo_rapido").val()); });
	$("#codigo_rapido").on("keypress", function(e){ if (e.which === 13) { e.preventDefault(); buscarCodigo($(this).val()); } });
	$(document).on("keydown", function(e){
		if (!enFormulario) { return; }
		if (e.key === "F2") { e.preventDefault(); $("#myModal").modal("show"); }
		if (e.key === "F4") { e.preventDefault(); if (!$("#btnGuardar").prop("disabled")) { $("#formulario").trigger("submit"); } }
		if (e.key === "Escape" && !$(".modal.in").length) { cancelarform(); }
	});
	if (window.appQueryParam && window.appQueryParam("nuevo") === "1") { mostrarform(true); }
}

function cargarResumen(){
	$.get("../ajax/cotizacion.php?op=resumen", function(resp){
		var r = appParseJson(resp, null); if (!r) { return; }
		$("#resPend").text(r.pendientes || 0);
		$("#resPendTxt").text("Pendientes · " + money(r.pendientes_monto));
		$("#resConv").text(r.convertidas_30 || 0);
		var tasa = Number(r.total_30) > 0 ? Math.round(100 * Number(r.convertidas_30) / Number(r.total_30)) : 0;
		$("#resTasa").text(tasa + " %");
	});
}

function cargarClientes(idPreferido){
	$.post("../ajax/cotizacion.php?op=selectCliente", function(r){
		$("#idcliente").html(r);
		if (idPreferido) { $("#idcliente").val(String(idPreferido)); }
		$("#idcliente").selectpicker("refresh");
		clientesCargados = true;
	});
}

function listar(){
	tabla = $("#tbllistado").dataTable({
		aProcessing: true, aServerSide: true, dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Cotizaciones', true),
		ajax: { url: "../ajax/cotizacion.php?op=listar", type: "get", dataType: "json",
			data: function(d){ d.fecha_inicio = $("#f_inicio").val(); d.fecha_fin = $("#f_fin").val(); d.estado = $("#f_estado").val(); },
			error: function(e){ console.log(e.responseText); } },
		bDestroy: true, iDisplayLength: 10, order: [[1, "desc"]], columnDefs: [{ orderable: false, targets: [0] }]
	}).DataTable();
}

function listarArticulos(){
	tablaArticulos = $("#tblarticulos").dataTable({
		aProcessing: true, aServerSide: true, autoWidth: false, dom: 'frtip',
		ajax: { url: "../ajax/cotizacion.php?op=listarArticulos", type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } },
		bDestroy: true, iDisplayLength: 8, order: [[1, "asc"]], columnDefs: [{ orderable: false, targets: [0, 7] }]
	}).DataTable();
}

function limpiar(){
	$("#idcotizacion, #observacion, #condiciones").val("");
	$("#impuesto").val("0");
	$("#fecha_hora").val(ahoraInput());
	$("#fecha_validez").val(appFechaSumarDias(null, 15));
	$("#detalles tbody").empty();
	cont = 0; detalles = 0;
	$("#formTitulo").text("Nueva cotización");
	$("#chipNumero").text("—");
	if (clientesCargados) { $("#idcliente").val($("#idcliente option:first").val() || "").selectpicker("refresh"); }
	calcularTotales();
}

function mostrarform(flag){
	limpiar();
	enFormulario = !!flag;
	if (flag) {
		$("#listadoregistros, #accionesListado, #cotResumen").hide();
		$("#formularioregistros").show();
		$.get("../ajax/cotizacion.php?op=siguienteNumero", function(resp){ var r = appParseJson(resp, null); if (r && r.ok && !$("#idcotizacion").val()) { $("#chipNumero").text(r.numero); } });
		setTimeout(function(){ $("#codigo_rapido").focus(); }, 80);
	} else {
		$("#listadoregistros, #accionesListado, #cotResumen").show();
		$("#formularioregistros").hide();
	}
}

function cancelarform(){
	if (detalles > 0) { appConfirm("¿Descartar los cambios de la cotización?", function(){ limpiar(); mostrarform(false); }, { titulo: "Descartar", ok: "Sí, descartar", tipo: "danger" }); return; }
	limpiar(); mostrarform(false);
}

function agregarDetalle(idarticulo, nombre, precio, unidad, stock, cantidadInicial, descuentoInicial){
	if (!idarticulo) { return; }
	var arts = document.getElementsByName("idarticulo[]");
	var cants = document.getElementsByName("cantidad[]");
	for (var i = 0; i < arts.length; i++) {
		if (parseInt(arts[i].value, 10) === parseInt(idarticulo, 10)) {
			cants[i].value = parseInt(cants[i].value || 0, 10) + 1;
			modificarSubtotales(); $("#myModal").modal("hide");
			appNotify("info", nombre + ": cantidad " + cants[i].value); return;
		}
	}
	var p = parseFloat(precio) || 0;
	var fila = '<tr class="filas" id="fila' + cont + '">' +
		'<td><button type="button" class="btn btn-danger btn-xs btn-icon" onclick="eliminarDetalle(' + cont + ')" title="Quitar"><i class="fa fa-trash"></i></button></td>' +
		'<td><input type="hidden" name="idarticulo[]" value="' + parseInt(idarticulo, 10) + '"><strong>' + appEscapeHtml(nombre) + '</strong><br><small class="text-soft">Stock: ' + (stock || 0) + '</small></td>' +
		'<td>' + appEscapeHtml(unidad || "und") + '</td>' +
		'<td><input type="number" step="1" min="1" name="cantidad[]" value="' + (parseInt(cantidadInicial, 10) || 1) + '" oninput="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="precio[]" value="' + p.toFixed(2) + '" oninput="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="descuento[]" value="' + (parseFloat(descuentoInicial) || 0).toFixed(2) + '" oninput="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td class="text-right"><span class="money" name="subtotal" data-value="0">0.00</span></td></tr>';
	cont++; detalles++;
	$("#detalles tbody").append(fila);
	modificarSubtotales();
	$("#myModal").modal("hide");
	setTimeout(function(){ $("#codigo_rapido").focus(); }, 50);
}

function modificarSubtotales(){
	var c = document.getElementsByName("cantidad[]"), p = document.getElementsByName("precio[]"), d = document.getElementsByName("descuento[]"), s = document.getElementsByName("subtotal");
	for (var i = 0; i < c.length; i++) {
		var cant = Math.max(1, parseInt(c[i].value || 1, 10)); c[i].value = cant;
		var bruto = cant * (parseFloat(p[i].value) || 0);
		var des = Math.max(0, parseFloat(d[i].value) || 0);
		if (des > bruto) { des = bruto; d[i].value = bruto.toFixed(2); }
		var sub = bruto - des;
		s[i].textContent = sub.toFixed(2); s[i].setAttribute("data-value", sub.toFixed(2));
	}
	calcularTotales();
}

function calcularTotales(){
	var s = document.getElementsByName("subtotal"), c = document.getElementsByName("cantidad[]"), d = document.getElementsByName("descuento[]");
	var total = 0, uni = 0, desc = 0;
	for (var i = 0; i < s.length; i++) { total += parseFloat(s[i].getAttribute("data-value") || 0); }
	for (var j = 0; j < c.length; j++) { uni += parseInt(c[j].value || 0, 10); desc += parseFloat(d[j].value || 0); }
	$("#total").html(money(total)); $("#posTotal").text(money(total));
	$("#posItems").text(c.length); $("#posUnidades").text(uni); $("#posDescuentos").text(money(desc));
	if (detalles > 0) { $("#btnGuardar").prop("disabled", false); $("#detalleVacio").hide(); } else { $("#btnGuardar").prop("disabled", true); $("#detalleVacio").show(); cont = 0; }
}

function eliminarDetalle(i){ $("#fila" + i).remove(); detalles--; calcularTotales(); }

function buscarCodigo(codigo){
	codigo = (codigo || "").trim();
	if (!codigo) { return; }
	$("#codigo_rapido").val("");
	$.post("../ajax/cotizacion.php?op=buscarArticuloCodigo", { codigo: codigo }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { appNotify("warning", (r && r.message) || "No se encontró el artículo"); return; }
		agregarDetalle(r.idarticulo, r.nombre, r.precio_venta, r.unidad, r.stock);
	});
}

function guardar(e){
	e.preventDefault();
	if (!$("#idcliente").val()) { appNotify("warning", "Selecciona un cliente."); return; }
	if (detalles <= 0) { appNotify("warning", "Agrega al menos un artículo."); return; }
	if (!$("#fecha_validez").val()) { appNotify("warning", "Indica hasta cuándo es válida."); return; }
	appSetLoading("#btnGuardar", true);
	$.post("../ajax/cotizacion.php?op=guardar", $("#formulario").serialize(), function(resp){
		appSetLoading("#btnGuardar", false);
		var r = appParseJson(resp, { ok: false, message: resp });
		if (!r.ok) { appNotify("error", r.message); return; }
		appNotify("success", r.message);
		var id = r.idcotizacion;
		bootbox.dialog({
			title: '<i class="fa fa-check-circle" style="color:#16a34a"></i> Cotización guardada',
			message: '<div style="text-align:center"><div style="font-size:22px;font-weight:800">' + appEscapeHtml(r.numero) + '</div><div style="font-size:28px;font-weight:800;color:#0f766e">' + money(r.total) + '</div></div>',
			closeButton: true,
			buttons: {
				pdf: { label: '<i class="fa fa-file-pdf-o"></i> Imprimir PDF', className: "btn-info", callback: function(){ window.open("../reportes/exCotizacion.php?id=" + id, "_blank"); } },
				ok: { label: '<i class="fa fa-check"></i> Listo', className: "btn-primary" }
			}
		});
		mostrarform(false); tabla.ajax.reload(null, false); cargarResumen();
	}).fail(function(){ appSetLoading("#btnGuardar", false); });
}

function mostrar(id){
	$.get("../ajax/cotizacion.php?op=mostrar", { id: id }, function(resp){
		var c = appParseJson(resp, null);
		if (!c || !c.ok) { appNotify("error", "No se pudo cargar la cotización."); return; }
		$("#detNumero").text(c.numero);
		$("#detPdf").attr("href", "../reportes/exCotizacion.php?id=" + id);
		$("#detVender").attr("href", "venta.php?cotizacion=" + id).toggle(c.estado === "PENDIENTE" || c.estado === "ACEPTADA");
		$("#detCabecera").html('<div class="col-sm-6"><p><strong>Cliente:</strong> ' + appEscapeHtml(c.cliente) + '</p><p><strong>Vendedor:</strong> ' + appEscapeHtml(c.usuario) + '</p><p><strong>Fecha:</strong> ' + appEscapeHtml(c.fecha) + '</p></div><div class="col-sm-6"><p><strong>Estado:</strong> ' + appEscapeHtml(c.estado) + '</p><p><strong>Válida hasta:</strong> ' + appEscapeHtml(c.fecha_validez) + '</p><p><strong>Total:</strong> <span class="money">' + money(c.total) + '</span> (impuesto ' + appEscapeHtml(c.impuesto) + ' %)</p>' + (c.observacion ? '<p><strong>Obs.:</strong> ' + appEscapeHtml(c.observacion) + '</p>' : '') + (c.condiciones ? '<p><strong>Condiciones:</strong> ' + appEscapeHtml(c.condiciones) + '</p>' : '') + '</div>');
		var html = '<thead><tr><th>Artículo</th><th>Unidad</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Dscto.</th><th class="text-right">Subtotal</th></tr></thead><tbody>';
		(c.items || []).forEach(function(it){ html += '<tr><td>' + appEscapeHtml(it.nombre) + '</td><td>' + appEscapeHtml(it.unidad) + '</td><td class="text-right">' + it.cantidad + '</td><td class="text-right">' + Number(it.precio).toFixed(2) + '</td><td class="text-right">' + Number(it.descuento).toFixed(2) + '</td><td class="text-right">' + Number(it.subtotal).toFixed(2) + '</td></tr>'; });
		html += '</tbody>';
		$("#detTabla").html(html);
		$("#modalDetalle").modal("show");
	});
}

function editar(id){
	$.get("../ajax/cotizacion.php?op=mostrar", { id: id }, function(resp){
		var c = appParseJson(resp, null);
		if (!c || !c.ok) { appNotify("error", "No se pudo cargar la cotización."); return; }
		if (c.estado !== "PENDIENTE") { appNotify("warning", "Solo se editan cotizaciones pendientes."); return; }
		mostrarform(true);
		$("#idcotizacion").val(c.idcotizacion);
		$("#formTitulo").text("Editar cotización");
		$("#chipNumero").text(c.numero);
		var f = (c.fecha || "").replace(" ", "T").substring(0, 16);
		$("#fecha_hora").val(f); $("#fecha_validez").val(c.fecha_validez);
		$("#impuesto").val(c.impuesto); $("#observacion").val($("<textarea/>").html(c.observacion || "").text()); $("#condiciones").val($("<textarea/>").html(c.condiciones || "").text());
		var fijarCliente = function(){ $("#idcliente").val(String(c.idcliente)).selectpicker("refresh"); };
		if (clientesCargados) { fijarCliente(); } else { setTimeout(fijarCliente, 600); }
		(c.items || []).forEach(function(it){ agregarDetalle(it.idarticulo, it.nombre, it.precio, it.unidad, it.stock, it.cantidad, it.descuento); });
	});
}

function cambiarEstado(id, estado){
	appConfirm("¿Marcar la cotización como " + estado.toLowerCase() + "?", function(){
		$.post("../ajax/cotizacion.php?op=estado", { id: id, estado: estado }, function(resp){
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message);
			tabla.ajax.reload(null, false); cargarResumen();
		});
	}, { titulo: "Cambiar estado", ok: "Sí", tipo: estado === "RECHAZADA" ? "danger" : "success" });
}

init();
