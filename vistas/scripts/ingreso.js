/* Compras / ingresos */
var tabla;
var tablaArticulos;
var empresaDefaultsIngreso = { serie_boleta: "B001", serie_factura: "F001", serie_ticket: "T001", impuesto_default: 18 };
var proveedoresCargados = false;
var cont = 0;
var detalles = 0;
var enFormulario = false;

function money(v){ return window.appMoney ? window.appMoney(v, 2) : ((window.appCurrencySymbol || "S/") + " " + Number(v || 0).toFixed(2)); }

function normalizarCantidadEntera(valor, minimo){
	var num = parseFloat(valor);
	if (!isFinite(num)) { return minimo; }
	num = Math.round(num);
	return num < minimo ? minimo : num;
}

function fechaHoraActualInput(){
	var now = new Date();
	return now.getFullYear() + "-" + ("0" + (now.getMonth() + 1)).slice(-2) + "-" + ("0" + now.getDate()).slice(-2) + "T" + ("0" + now.getHours()).slice(-2) + ":" + ("0" + now.getMinutes()).slice(-2);
}

function init(){
	mostrarform(false);
	listar();
	$("#formulario").on("submit", function(e){ guardaryeditar(e); });
	$("#btnFiltrarIngreso").on("click", recargarListadoIngreso);
	$("#btnLimpiarFiltroIngreso").on("click", function(){
		$("#filtro_ingreso_inicio, #filtro_ingreso_fin, #filtro_ingreso_estado, #filtro_ingreso_pago").val("");
		recargarListadoIngreso();
	});
	cargarProveedores();
	cargarDefaultsEmpresaIngreso();

	$("#myModal").on("shown.bs.modal", function(){
		if (!tablaArticulos) { listarArticulos(); } else { tablaArticulos.columns.adjust(); tablaArticulos.ajax.reload(null, false); }
		$("#comprasItemsSeleccionadosModal").text(document.getElementsByName("idarticulo[]").length);
		setTimeout(function(){ $("#tblarticulos_filter input").focus(); }, 120);
	});
	$("#formProveedorRapido").on("submit", guardarProveedorRapido);
	$("#modalProveedorIngreso").on("shown.bs.modal", function(){ $("#prv_nombre").focus(); });
	$("#modalProveedorIngreso").on("hidden.bs.modal", limpiarFormProveedorRapido);
	$("#tipo_comprobante").on("change", aplicarSerieImpuestoIngreso);

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
		if (e.key === "F2") { e.preventDefault(); $("#myModal").modal("show"); }
		if (e.key === "F4") { e.preventDefault(); if (!$("#btnGuardar").prop("disabled")) { $("#formulario").trigger("submit"); } }
		if (e.key === "Escape" && !$(".modal.in").length) { cancelarform(); }
	});

	if (window.appQueryParam && window.appQueryParam("nuevo") === "1") { mostrarform(true); }
}

function cargarProveedores(idPreferido){
	var idActual = (typeof idPreferido !== "undefined" && idPreferido !== null && idPreferido !== "") ? idPreferido : ($("#idproveedor").val() || "");
	$.post("../ajax/ingreso.php?op=selectProveedor", function(r){
		$("#idproveedor").html(r);
		var existeActual = false;
		if (idActual !== "") { $("#idproveedor option").each(function(){ if (String($(this).val()) === String(idActual)) { existeActual = true; return false; } }); }
		$("#idproveedor").val(existeActual ? String(idActual) : ($("#idproveedor option:first").val() || "")).selectpicker("refresh");
		proveedoresCargados = true;
	});
}

function limpiarFormProveedorRapido(){
	$("#prv_nombre, #prv_num_documento, #prv_direccion, #prv_telefono, #prv_email").val("");
	$("#prv_tipo_documento").val("RUC");
	appSetLoading("#btnGuardarProveedorRapido", false);
}

function guardarProveedorRapido(e){
	e.preventDefault();
	if (!$.trim($("#prv_nombre").val())) { appNotify("warning", "El nombre del proveedor es obligatorio."); return; }
	appSetLoading("#btnGuardarProveedorRapido", true);
	$.ajax({
		url: "../ajax/ingreso.php?op=crearProveedorRapido",
		type: "POST",
		data: $("#formProveedorRapido").serialize(),
		success: function(resp){
			var r = appParseJson(resp, { ok: false, message: "No se pudo registrar el proveedor." });
			if (!r.ok) { appNotify("error", r.message); appSetLoading("#btnGuardarProveedorRapido", false); return; }
			appNotify("success", r.message || "Proveedor registrado.");
			$("#modalProveedorIngreso").modal("hide");
			cargarProveedores(r.idproveedor || "");
		},
		error: function(){ appSetLoading("#btnGuardarProveedorRapido", false); }
	});
}

function cargarDefaultsEmpresaIngreso(){
	$.get("../ajax/empresa.php?op=defaults", function(resp){
		var r = appParseJson(resp, null);
		if (r) {
			empresaDefaultsIngreso.serie_boleta = r.serie_boleta || "B001";
			empresaDefaultsIngreso.serie_factura = r.serie_factura || "F001";
			empresaDefaultsIngreso.serie_ticket = r.serie_ticket || "T001";
			empresaDefaultsIngreso.impuesto_default = parseFloat(r.impuesto_default || 18);
		}
		aplicarSerieImpuestoIngreso();
	});
}

function limpiar(){
	$("#idingreso, #serie_comprobante, #num_comprobante, #observacion, #fecha_vencimiento").val("");
	if (proveedoresCargados) { $("#idproveedor").val($("#idproveedor option:first").val() || "").selectpicker("refresh"); }
	$("#impuesto").val("0");
	$("#total_compra").val("");
	$("#detalles tbody").empty();
	cont = 0; detalles = 0;
	$("#fecha_hora").val(fechaHoraActualInput());
	$("#tipo_comprobante").val("Factura");
	$(".pago-toggle .btn").removeClass("active").filter("[data-pago='CONTADO']").addClass("active");
	$("#tipo_pago").val("CONTADO");
	$("#medio_pago").val("EFECTIVO");
	$("#grupoVencimiento").hide();
	aplicarSerieImpuestoIngreso();
	calcularTotales();
}

function mostrarform(flag){
	limpiar();
	enFormulario = !!flag;
	if (flag) {
		$("#listadoregistros, #accionesListado").hide();
		$("#formularioregistros").show();
	} else {
		$("#listadoregistros, #accionesListado").show();
		$("#formularioregistros").hide();
	}
}

function cancelarform(){
	if (detalles > 0) {
		appConfirm("Hay artículos en la compra actual. ¿Descartar?", function(){ limpiar(); mostrarform(false); }, { titulo: "Descartar compra", ok: "Sí, descartar", tipo: "danger" });
		return;
	}
	limpiar(); mostrarform(false);
}

function listar(){
	tabla = $('#tbllistado').dataTable({
		"aProcessing": true, "aServerSide": true, dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Reporte de Compras', true),
		"ajax": {
			url: '../ajax/ingreso.php?op=listar', type: "get",
			data: function(d){
				d.fecha_inicio = ($("#filtro_ingreso_inicio").val() || "").trim();
				d.fecha_fin = ($("#filtro_ingreso_fin").val() || "").trim();
				d.estado = $("#filtro_ingreso_estado").val() || "";
				d.tipo_pago = $("#filtro_ingreso_pago").val() || "";
			},
			dataType: "json", error: function(e){ console.log(e.responseText); }
		},
		"bDestroy": true, "iDisplayLength": 10, "order": [[1, "desc"]],
		"columnDefs": [{ "orderable": false, "targets": [0] }]
	}).DataTable();
}

function recargarListadoIngreso(){
	var fi = ($("#filtro_ingreso_inicio").val() || "").trim();
	var ff = ($("#filtro_ingreso_fin").val() || "").trim();
	if (fi && ff && fi > ff) { appNotify("warning", "La fecha 'Desde' no puede ser mayor que 'Hasta'."); return; }
	if (tabla) { tabla.ajax.reload(); }
}

function listarArticulos(){
	tablaArticulos = $('#tblarticulos').dataTable({
		"aProcessing": true, "aServerSide": true, "autoWidth": false, dom: 'frtip',
		"ajax": { url: '../ajax/ingreso.php?op=listarArticulos', type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } },
		"bDestroy": true, "iDisplayLength": 8, "order": [[1, "asc"]],
		"columnDefs": [{ "orderable": false, "targets": [0, 7] }],
		"language": { "sSearchPlaceholder": "Buscar por nombre, código o categoría…" }
	}).DataTable();
}

function guardaryeditar(e){
	e.preventDefault();
	if (!($("#idproveedor").val() || "").toString().trim()) { appNotify("warning", "Selecciona un proveedor antes de guardar."); return; }
	if (detalles <= 0) { appNotify("warning", "Agrega al menos un artículo a la compra."); return; }
	if ($("#tipo_pago").val() === "CREDITO" && !$("#fecha_vencimiento").val()) { appNotify("warning", "Indica la fecha de vencimiento del crédito."); return; }
	var precios = document.getElementsByName("precio_compra[]");
	for (var i = 0; i < precios.length; i++) { if (parseFloat(precios[i].value || 0) < 0) { appNotify("warning", "Hay un precio negativo."); return; } }
	appSetLoading("#btnGuardar", true);
	$.ajax({
		url: "../ajax/ingreso.php?op=guardaryeditar", type: "POST",
		data: new FormData($("#formulario")[0]), contentType: false, processData: false,
		success: function(datos){
			appSetLoading("#btnGuardar", false);
			var r = appParseJson(datos, null);
			if (!r || typeof r.ok === "undefined") { appNotifyFromResponse(datos); return; }
			if (!r.ok) { appNotify("error", r.message || "No se pudo registrar la compra."); return; }
			appNotify("success", r.message || "Compra registrada correctamente");
			var extra = "";
			if (r.cuenta_pagar) { extra += '<p class="text-soft"><i class="fa fa-calendar"></i> Se creó una cuenta por pagar.</p>'; }
			if (r.caja_registrada) { extra += '<p class="text-soft"><i class="fa fa-money"></i> Egreso registrado en tu caja abierta.</p>'; }
			bootbox.dialog({
				title: '<i class="fa fa-check-circle" style="color:#16a34a"></i> Compra registrada',
				message: '<div style="text-align:center"><div style="font-size:22px;font-weight:800;margin:4px 0 8px">' + appEscapeHtml((r.tipo_comprobante || "") + " " + (r.serie_comprobante || "") + "-" + (r.num_comprobante || "")) + '</div><div style="font-size:28px;font-weight:800;color:#0f766e">' + money(r.total) + '</div>' + extra + '</div>',
				closeButton: true,
				buttons: {
					pdf: { label: '<i class="fa fa-file-pdf-o"></i> Imprimir', className: "btn-info", callback: function(){ window.open("../reportes/exIngreso.php?id=" + r.idingreso, "_blank"); } },
					ok: { label: '<i class="fa fa-check"></i> Listo', className: "btn-primary" }
				}
			});
			mostrarform(false);
			tabla.ajax.reload(null, false);
		},
		error: function(){ appSetLoading("#btnGuardar", false); }
	});
}

function mostrar(idingreso){
	$.post("../ajax/ingreso.php?op=mostrar", { idingreso: idingreso }, function(data){
		var d = appParseJson(data, null);
		if (!d) { appNotify("error", "No se pudo cargar la compra."); return; }
		$("#detTitulo").text(d.tipo_comprobante + " " + d.serie_comprobante + "-" + d.num_comprobante);
		$("#detImprimir").attr("href", "../reportes/exIngreso.php?id=" + idingreso);
		var estado = d.estado === "Aceptado" ? '<span class="label bg-green">Aceptado</span>' : '<span class="label bg-red">Anulado</span>';
		var pago = (d.tipo_pago || "CONTADO") + " · " + (d.medio_pago || "EFECTIVO") + (d.tipo_pago === "CREDITO" && d.fecha_vencimiento ? " · vence " + d.fecha_vencimiento : "");
		$("#detCabecera").html(
			'<div class="col-sm-6"><p><strong>Proveedor:</strong> ' + appEscapeHtml(d.proveedor) + '</p><p><strong>Registrado por:</strong> ' + appEscapeHtml(d.usuario) + '</p><p><strong>Fecha:</strong> ' + appEscapeHtml(d.fecha) + '</p></div>' +
			'<div class="col-sm-6"><p><strong>Estado:</strong> ' + estado + '</p><p><strong>Pago:</strong> ' + appEscapeHtml(pago) + '</p><p><strong>Impuesto:</strong> ' + appEscapeHtml(d.impuesto) + ' % &nbsp; <strong>Total:</strong> <span class="money">' + money(d.total_compra) + '</span></p>' + (d.observacion ? '<p><strong>Obs.:</strong> ' + appEscapeHtml(d.observacion) + '</p>' : '') + '</div>'
		);
		$.post("../ajax/ingreso.php?op=listarDetalle&id=" + idingreso, function(r){ $("#detTabla").html(r); });
		$("#modalDetalleIngreso").modal("show");
	});
}

function anular(idingreso){
	appConfirm("Se anulará la compra y el stock ingresado se descontará del inventario. ¿Continuar?", function(){
		$.post("../ajax/ingreso.php?op=anular", { idingreso: idingreso }, function(e){ appNotifyFromResponse(e); tabla.ajax.reload(null, false); });
	}, { titulo: "Anular compra", ok: "Sí, anular", tipo: "danger" });
}

function aplicarSerieImpuestoIngreso(){
	var tipo = $("#tipo_comprobante").val();
	if (tipo === 'Factura') { $("#serie_comprobante").val(empresaDefaultsIngreso.serie_factura); $("#impuesto").val((empresaDefaultsIngreso.impuesto_default || 18).toFixed(2)); }
	else if (tipo === 'Ticket') { $("#serie_comprobante").val(empresaDefaultsIngreso.serie_ticket); $("#impuesto").val("0"); }
	else { $("#serie_comprobante").val(empresaDefaultsIngreso.serie_boleta); $("#impuesto").val("0"); }
}

function agregarDetalle(idarticulo, articulo, unidad, precio_compra_ref, precio_venta_ref){
	var precio_compra = (precio_compra_ref && parseFloat(precio_compra_ref) > 0) ? parseFloat(precio_compra_ref) : 0;
	var precio_venta = (precio_venta_ref && parseFloat(precio_venta_ref) > 0) ? parseFloat(precio_venta_ref) : 0;
	var unidadTexto = unidad || "und";
	var articulos = document.getElementsByName("idarticulo[]");
	var cantidades = document.getElementsByName("cantidad[]");
	if (!idarticulo) { appNotify("warning", "No se pudo agregar el artículo."); return; }
	for (var i = 0; i < articulos.length; i++) {
		if (parseInt(articulos[i].value, 10) === parseInt(idarticulo, 10)) {
			cantidades[i].value = normalizarCantidadEntera(parseFloat(cantidades[i].value || 0) + 1, 1);
			modificarSubtotales();
			$('#myModal').modal('hide');
			appNotify("info", articulo + ": cantidad " + cantidades[i].value);
			return;
		}
	}
	var fila = '<tr class="filas" id="fila' + cont + '">' +
		'<td><button type="button" class="btn btn-danger btn-xs btn-icon" onclick="eliminarDetalle(' + cont + ')" title="Quitar"><i class="fa fa-trash"></i></button></td>' +
		'<td><input type="hidden" name="idarticulo[]" value="' + parseInt(idarticulo, 10) + '"><strong>' + appEscapeHtml(articulo) + '</strong></td>' +
		'<td>' + appEscapeHtml(unidadTexto) + '</td>' +
		'<td><input type="number" step="1" min="1" name="cantidad[]" value="1" oninput="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="precio_compra[]" value="' + precio_compra.toFixed(2) + '" oninput="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="precio_venta[]" value="' + precio_venta.toFixed(2) + '" onfocus="this.select()" title="Nuevo precio de venta (0 = mantener)"></td>' +
		'<td class="text-right"><span class="money" name="subtotal" data-value="' + precio_compra.toFixed(2) + '">' + precio_compra.toFixed(2) + '</span></td>' +
		'<td></td></tr>';
	cont++; detalles++;
	$('#detalles tbody').append(fila);
	modificarSubtotales();
	$('#myModal').modal('hide');
	setTimeout(function(){ $("#fila" + (cont - 1) + " input[name='cantidad[]']").focus().select(); }, 300);
}

function modificarSubtotales(){
	var cant = document.getElementsByName("cantidad[]");
	var prec = document.getElementsByName("precio_compra[]");
	var sub = document.getElementsByName("subtotal");
	for (var i = 0; i < cant.length; i++) {
		cant[i].value = normalizarCantidadEntera(cant[i].value, 1);
		var s = parseFloat(cant[i].value || 0) * parseFloat(prec[i].value || 0);
		sub[i].textContent = s.toFixed(2);
		sub[i].setAttribute("data-value", s.toFixed(2));
	}
	calcularTotales();
}

function calcularTotales(){
	var sub = document.getElementsByName("subtotal");
	var cant = document.getElementsByName("cantidad[]");
	var total = 0, unidades = 0;
	for (var i = 0; i < sub.length; i++) { total += parseFloat(sub[i].getAttribute("data-value") || 0); }
	for (var j = 0; j < cant.length; j++) { unidades += parseInt(cant[j].value || 0, 10); }
	$("#total").html(money(total));
	$("#posTotal").text(money(total));
	$("#posUnidades").text(unidades);
	$("#total_compra").val(total.toFixed(2));
	var count = document.getElementsByName("idarticulo[]").length;
	$("#comprasItemsSeleccionados, #comprasItemsSeleccionadosModal").text(count);
	if (detalles > 0) { $("#btnGuardar").prop("disabled", false); $("#detalleVacio").hide(); }
	else { $("#btnGuardar").prop("disabled", true); $("#detalleVacio").show(); cont = 0; }
}

function eliminarDetalle(indice){
	$("#fila" + indice).remove();
	detalles = detalles - 1;
	calcularTotales();
}

init();
