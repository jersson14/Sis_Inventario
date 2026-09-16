/* Compras / ingresos */
var tabla;
var tablaArticulos;
var empresaDefaultsIngreso = { serie_boleta: "B001", serie_factura: "F001", serie_ticket: "T001", impuesto_default: 18 };
var proveedoresCargados = false;
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

// Borrado definitivo (solo administrador). Si la compra seguía vigente el
// servidor descuenta el stock ingresado antes de borrarla; si ya estaba
// anulada no lo vuelve a tocar, porque la anulación ya lo descontó.
function eliminar(idingreso){
	appConfirm("Se eliminará la compra de forma PERMANENTE, junto con su detalle, su cuenta por pagar y sus movimientos de caja. Si la compra estaba vigente, el stock ingresado se descontará del inventario. Esta acción no se puede deshacer. ¿Continuar?", function(){
		$.post("../ajax/ingreso.php?op=eliminar", { idingreso: idingreso }, function(e){
			appNotifyFromResponse(e);
			tabla.ajax.reload(null, false);
		});
	}, { titulo: "Eliminar compra", ok: "Sí, eliminar", tipo: "danger" });
}

function aplicarSerieImpuestoIngreso(){
	var tipo = $("#tipo_comprobante").val();
	if (tipo === 'Factura') { $("#serie_comprobante").val(empresaDefaultsIngreso.serie_factura); $("#impuesto").val((empresaDefaultsIngreso.impuesto_default || 18).toFixed(2)); }
	else if (tipo === 'Ticket') { $("#serie_comprobante").val(empresaDefaultsIngreso.serie_ticket); $("#impuesto").val("0"); }
	else { $("#serie_comprobante").val(empresaDefaultsIngreso.serie_boleta); $("#impuesto").val("0"); }
}

// ---------------------------------------------------------------------
// Detalle de la compra. Igual que en ventas: la cantidad se escribe en la
// presentacion elegida (5 cajas) y el trigger suma cantidad x factor al stock.
// ---------------------------------------------------------------------

function agregarArticulo(idarticulo, idpresentacion){
	$.post("../ajax/ingreso.php?op=infoArticulo", { idarticulo: idarticulo }, function(resp){
		var f = appParseJson(resp, null);
		if (!f || !f.ok) { appNotify("warning", (f && f.message) || "No se pudo agregar el artículo."); return; }
		agregarFicha(f, idpresentacion || 0);
	});
}

function presentacionDeFicha(f, idpresentacion){
	var id = parseInt(idpresentacion, 10) || 0;
	for (var i = 0; i < (f.presentaciones || []).length; i++) {
		if (f.presentaciones[i].idpresentacion === id) { return f.presentaciones[i]; }
	}
	return null;
}

function agregarFicha(f, idpresentacion){
	var pres = presentacionDeFicha(f, idpresentacion);
	var idPres = pres ? pres.idpresentacion : 0;
	var $existente = $("#detalles tbody tr.filas").filter(function(){
		return parseInt($(this).attr("data-idarticulo"), 10) === f.idarticulo && (parseInt($(this).find("[name='idpresentacion[]']").val(), 10) || 0) === idPres;
	}).first();
	if ($existente.length) {
		var $cant = $existente.find("[name='cantidad[]']");
		$cant.val((parseFloat($cant.val()) || 0) + 1);
		modificarSubtotales();
		$('#myModal').modal('hide');
		appNotify("info", f.nombre + ": cantidad " + window.appCantidad($cant.val()));
		return;
	}
	var selector = "";
	if (f.presentaciones && f.presentaciones.length) {
		selector = '<select class="form-control input-sm sel-presentacion" onchange="cambiarPresentacion(this)">' +
			'<option value="0">' + appEscapeHtml(f.unidad) + '</option>' +
			f.presentaciones.map(function(p){
				return '<option value="' + p.idpresentacion + '"' + (p.idpresentacion === idPres ? " selected" : "") + '>' + appEscapeHtml(p.nombre) + ' (' + window.appCantidad(p.factor) + ' ' + appEscapeHtml(f.unidad) + ')</option>';
			}).join("") + '</select>';
	}
	var fila = $('<tr class="filas" id="fila' + cont + '"></tr>');
	fila.attr({ "data-idarticulo": f.idarticulo, "data-fraccion": f.permite_fraccion ? "1" : "0" });
	fila.data("ficha", f);
	fila.html(
		'<td><button type="button" class="btn btn-danger btn-xs btn-icon" onclick="eliminarDetalle(' + cont + ')" title="Quitar"><i class="fa fa-trash"></i></button></td>' +
		'<td><input type="hidden" name="idarticulo[]" value="' + f.idarticulo + '"><input type="hidden" name="idpresentacion[]" value="' + idPres + '"><strong>' + appEscapeHtml(f.nombre) + '</strong>' + selector +
			'<small class="text-soft d-block">Stock: ' + window.appCantidad(f.stock) + ' ' + appEscapeHtml(f.unidad) + '</small>' +
			camposLote() + '</td>' +
		'<td><span class="unidad-fila"></span></td>' +
		'<td><input type="number" min="0" name="cantidad[]" value="1" oninput="modificarSubtotales()" onblur="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="precio_compra[]" value="0.00" oninput="$(this).attr(\'data-manual\',\'1\');modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="precio_venta[]" value="0.00" oninput="$(this).attr(\'data-manual\',\'1\')" onfocus="this.select()" title="Nuevo precio de venta (0 = mantener)"></td>' +
		'<td class="text-right"><span class="money" name="subtotal" data-value="0">0.00</span></td>'
	);
	cont++; detalles++;
	$('#detalles tbody').append(fila);
	aplicarPreciosPresentacion(fila);
	etiquetaUnidadFila(fila);
	modificarSubtotales();
	$('#myModal').modal('hide');
	setTimeout(function(){ fila.find("input[name='cantidad[]']").focus().select(); }, 300);
}

// Lote y vencimiento por linea (rubros con control de vencimientos)
function camposLote(){
	if (!window.appNegocioTiene || !(window.appNegocioTiene("vencimientos") || window.appNegocioTiene("lotes"))) {
		return '<input type="hidden" name="lote_codigo[]" value=""><input type="hidden" name="lote_vencimiento[]" value="">';
	}
	return '<div class="lote-fila">' +
		'<input type="text" class="form-control input-sm" name="lote_codigo[]" maxlength="40" placeholder="Lote" title="Código de lote (opcional)">' +
		'<input type="date" class="form-control input-sm" name="lote_vencimiento[]" title="Fecha de vencimiento">' +
		'</div>';
}

function filaFactor($tr){
	var pres = presentacionDeFicha($tr.data("ficha") || {}, $tr.find("[name='idpresentacion[]']").val());
	return pres ? pres.factor : 1;
}

function filaPermiteFraccion($tr){
	return $tr.attr("data-fraccion") === "1" && (parseInt($tr.find("[name='idpresentacion[]']").val(), 10) || 0) === 0;
}

// Precios de referencia segun la presentacion elegida, salvo que el usuario ya los haya escrito
function aplicarPreciosPresentacion($tr){
	var f = $tr.data("ficha") || {};
	var pres = presentacionDeFicha(f, $tr.find("[name='idpresentacion[]']").val());
	var pc = pres ? (pres.precio_compra > 0 ? pres.precio_compra : (f.precio_compra || 0) * pres.factor) : (f.precio_compra || 0);
	var pv = pres ? (pres.precio_venta || 0) : (f.precio_venta || 0);
	var $pc = $tr.find("[name='precio_compra[]']"), $pv = $tr.find("[name='precio_venta[]']");
	if ($pc.attr("data-manual") !== "1") { $pc.val(Number(pc).toFixed(2)); }
	if ($pv.attr("data-manual") !== "1") { $pv.val(Number(pv).toFixed(2)); }
	$tr.find("[name='cantidad[]']").attr("step", filaPermiteFraccion($tr) ? "0.001" : "1");
}

function cambiarPresentacion(select){
	var $tr = $(select).closest("tr");
	$tr.find("[name='idpresentacion[]']").val($(select).val());
	$tr.find("[name='precio_compra[]'], [name='precio_venta[]']").removeAttr("data-manual");
	aplicarPreciosPresentacion($tr);
	etiquetaUnidadFila($tr);
	modificarSubtotales();
}

// Texto corto de la unidad de la fila: la abreviatura base o el nombre de la presentacion
function etiquetaUnidadFila($tr){
	var f = $tr.data("ficha") || {};
	var pres = presentacionDeFicha(f, $tr.find("[name='idpresentacion[]']").val());
	$tr.find(".unidad-fila").text(pres ? pres.nombre : (f.unidad || "und"));
}


function modificarSubtotales(){
	$("#detalles tbody tr.filas").each(function(){
		var $tr = $(this);
		var $cant = $tr.find("[name='cantidad[]']");
		var fraccion = filaPermiteFraccion($tr);
		var cantidad = window.appNormalizarCantidad($cant.val(), fraccion, fraccion ? 0.001 : 1);
		// Mientras se escribe un decimal no se reescribe el campo; se corrige al salir
		if (document.activeElement !== $cant[0] && parseFloat($cant.val()) !== cantidad) { $cant.val(cantidad); }
		var s = Math.round(cantidad * parseFloat($tr.find("[name='precio_compra[]']").val() || 0) * 100) / 100;
		$tr.find("[name='subtotal']").text(s.toFixed(2)).attr("data-value", s.toFixed(2));
	});
	calcularTotales();
}

function calcularTotales(){
	var total = 0, unidades = 0;
	$("#detalles tbody tr.filas").each(function(){
		var $tr = $(this);
		total += parseFloat($tr.find("[name='subtotal']").attr("data-value") || 0);
		unidades += (parseFloat($tr.find("[name='cantidad[]']").val()) || 0) * filaFactor($tr);
	});
	$("#total").html(money(total));
	$("#posTotal").text(money(total));
	$("#posUnidades").text(window.appCantidad(unidades));
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
