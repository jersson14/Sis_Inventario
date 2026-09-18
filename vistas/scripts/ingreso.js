/* Compras / ingresos */
var tabla;
var tablaArticulos;
var empresaDefaultsIngreso = { serie_boleta: "B001", serie_factura: "F001", serie_ticket: "T001", impuesto_default: 18 };
var proveedoresCargados = false;
var cont = 0;
var detalles = 0;
var enFormulario = false;
var borradorCompra = appBorrador("compra");
var restaurandoBorrador = false;
var busquedaTimer = null;
var busquedaId = 0;

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
	$("#tipo_comprobante").on("change", function(){ aplicarSerieImpuestoIngreso(); calcularTotales(); });
	$("#impuesto").on("input", calcularTotales);

	$(".pago-toggle .btn").on("click", function(){ fijarTipoPago($(this).data("pago")); });
	$("#medio_pago").on("change", actualizarCamposMedio);

	iniciarBuscador();
	iniciarNavegacionDetalle();

	// Autoguardado: cualquier cambio del formulario deja el borrador al dia
	var guardarDiferido = appDebounce(guardarBorrador, 400);
	$("#formulario").on("input change", "input, select", guardarDiferido);

	$(document).on("keydown", function(e){
		if (!enFormulario) { return; }
		if (e.key === "F2") { e.preventDefault(); $("#myModal").modal("show"); }
		if (e.key === "F3") { e.preventDefault(); $("#buscarArticulo").focus().select(); }
		if (e.key === "F4") { e.preventDefault(); if (!$("#btnGuardar").prop("disabled")) { $("#formulario").trigger("submit"); } }
		if (e.key === "Escape" && !$(".modal.in").length && !$("#buscadorResultados").is(":visible")) { cancelarform(); }
	});

	mostrarAvisoBorradorListado();
	if (window.appQueryParam && window.appQueryParam("nuevo") === "1") { mostrarform(true); }
}

function fijarTipoPago(tipo){
	$(".pago-toggle .btn").removeClass("active").filter("[data-pago='" + tipo + "']").addClass("active");
	$("#tipo_pago").val(tipo);
	if (tipo === "CREDITO") {
		$("#grupoVencimiento").slideDown(120);
		$("#grupoMedioPago").slideUp(120);
		if (!$("#fecha_vencimiento").val()) { $("#fecha_vencimiento").val(appFechaSumarDias(null, 30)); }
	} else {
		$("#grupoVencimiento").slideUp(120);
		$("#grupoMedioPago").slideDown(120);
	}
	guardarBorrador();
}

// Efectivo sale de la caja; deposito, transferencia o billetera piden la cuenta y la operacion
function actualizarCamposMedio(){
	var medio = $("#medio_pago").val() || "EFECTIVO";
	$("#grupoCuentaPago").toggle(medio !== "EFECTIVO");
	$("#ayudaCaja").text(medio === "EFECTIVO"
		? "Con caja abierta, el pago se registra como egreso en efectivo de tu caja."
		: "Anota la cuenta y el N° de operación para ubicar el pago después.");
}

function cargarProveedores(idPreferido){
	var idActual = (typeof idPreferido !== "undefined" && idPreferido !== null && idPreferido !== "") ? idPreferido : ($("#idproveedor").val() || "");
	return $.post("../ajax/ingreso.php?op=selectProveedor", function(r){
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
			cargarProveedores(r.idproveedor || "").then(guardarBorrador);
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
		// Si ya se restauro un borrador no se pisa la serie ni el impuesto que traia
		if (!restaurandoBorrador && detalles === 0) { aplicarSerieImpuestoIngreso(); }
	});
}

function limpiar(){
	$("#idingreso, #serie_comprobante, #num_comprobante, #observacion, #fecha_vencimiento, #cuenta_pago, #num_operacion, #buscarArticulo").val("");
	if (proveedoresCargados) { $("#idproveedor").val($("#idproveedor option:first").val() || "").selectpicker("refresh"); }
	$("#impuesto").val("0");
	$("#total_compra").val("");
	$("#detalles tbody").empty();
	cont = 0; detalles = 0;
	$("#fecha_hora").val(fechaHoraActualInput());
	$("#tipo_comprobante").val("Factura");
	$(".pago-toggle .btn").removeClass("active").filter("[data-pago='CONTADO']").addClass("active");
	$("#tipo_pago").val("CONTADO");
	$("#grupoVencimiento").hide();
	$("#grupoMedioPago").show();
	appFijarMedioPago($("#grupoMedioPago .medio-grid"), "EFECTIVO");
	$("#avisoBorradorForm").hide();
	cerrarResultados();
	aplicarSerieImpuestoIngreso();
	calcularTotales();
}

function mostrarform(flag){
	restaurandoBorrador = true;   // limpiar() no debe borrar el borrador guardado
	limpiar();
	restaurandoBorrador = false;
	enFormulario = !!flag;
	appModoCaja(enFormulario);
	if (flag) {
		$("#listadoregistros, #accionesListado, #avisoBorradorListado").hide();
		$("#formularioregistros").show();
		restaurarBorrador();
		setTimeout(function(){ $("#buscarArticulo").focus(); }, 120);
	} else {
		$("#listadoregistros, #accionesListado").show();
		$("#formularioregistros").hide();
		mostrarAvisoBorradorListado();
	}
}

function cancelarform(){
	if (detalles > 0) {
		guardarBorrador();
		appConfirm("La compra queda guardada como borrador en esta PC y podrás continuarla después. ¿Salir al listado?", function(){ mostrarform(false); }, { titulo: "Salir de la compra", ok: "Sí, salir", tipo: "primary" });
		return;
	}
	borradorCompra.borrar();
	mostrarform(false);
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
		// Lo ultimo comprado primero, ordenando por la fecha real
		"bDestroy": true, "iDisplayLength": 25, "order": [[1, "desc"]],
		"columnDefs": [{ "orderable": false, "targets": [0] }, { "targets": [1], "render": window.appOrdenPorDato }]
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
	if (detalles <= 0) { appNotify("warning", "Agrega al menos un artículo a la compra."); $("#buscarArticulo").focus(); return; }
	if ($("#tipo_pago").val() === "CREDITO" && !$("#fecha_vencimiento").val()) { appNotify("warning", "Indica la fecha de vencimiento del crédito."); return; }
	var sinVariante = $("#detalles tbody tr.filas").filter(function(){ return $(this).find(".sel-variante").length && !(parseInt($(this).find("[name='idvariante[]']").val(), 10) > 0); }).first();
	if (sinVariante.length) { appNotify("warning", "Elige la talla y el color de " + (sinVariante.data("ficha") || {}).nombre + "."); sinVariante.find(".sel-variante").focus(); return; }
	var precios = document.getElementsByName("precio_compra[]");
	for (var i = 0; i < precios.length; i++) { if (parseFloat(precios[i].value || 0) < 0) { appNotify("warning", "Hay un precio negativo."); return; } }
	var sinCosto = $("#detalles tbody tr.filas").filter(function(){ return !(parseFloat($(this).find("[name='precio_compra[]']").val()) > 0); }).length;
	if (sinCosto > 0) {
		appConfirm(sinCosto + " artículo(s) tienen precio de compra 0. ¿Registrar así?", enviarCompra, { titulo: "Precios en cero", ok: "Sí, registrar" });
		return;
	}
	enviarCompra();
}

function enviarCompra(){
	appSetLoading("#btnGuardar", true);
	$.ajax({
		url: "../ajax/ingreso.php?op=guardaryeditar", type: "POST",
		data: new FormData($("#formulario")[0]), contentType: false, processData: false,
		success: function(datos){
			appSetLoading("#btnGuardar", false);
			var r = appParseJson(datos, null);
			if (!r || typeof r.ok === "undefined") { appNotifyFromResponse(datos); return; }
			if (!r.ok) { appNotify("error", r.message || "No se pudo registrar la compra."); return; }
			borradorCompra.borrar();
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
					nueva: { label: '<i class="fa fa-plus"></i> Otra compra', className: "btn-default", callback: function(){ mostrarform(true); } },
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
		var medio = appMedioPagoTexto[d.medio_pago] || d.medio_pago || "Efectivo";
		var pago = d.tipo_pago === "CREDITO" ? "Crédito" + (d.fecha_vencimiento ? " · vence " + d.fecha_vencimiento : "") : "Contado · " + medio;
		var datosPago = "";
		if (d.cuenta_pago) { datosPago += '<p><strong>Cuenta:</strong> ' + appEscapeHtml(d.cuenta_pago) + '</p>'; }
		if (d.num_operacion) { datosPago += '<p><strong>N° operación:</strong> ' + appEscapeHtml(d.num_operacion) + '</p>'; }
		$("#detCabecera").html(
			'<div class="col-sm-6"><p><strong>Proveedor:</strong> ' + appEscapeHtml(d.proveedor) + '</p><p><strong>Registrado por:</strong> ' + appEscapeHtml(d.usuario) + '</p><p><strong>Fecha:</strong> ' + appEscapeHtml(d.fecha) + '</p></div>' +
			'<div class="col-sm-6"><p><strong>Estado:</strong> ' + estado + '</p><p><strong>Pago:</strong> ' + appEscapeHtml(pago) + '</p>' + datosPago + '<p><strong>Impuesto:</strong> ' + appEscapeHtml(d.impuesto) + ' % &nbsp; <strong>Total:</strong> <span class="money">' + money(d.total_compra) + '</span></p>' + (d.observacion ? '<p><strong>Obs.:</strong> ' + appEscapeHtml(d.observacion) + '</p>' : '') + '</div>'
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
// Buscador en linea: se escribe o se escanea y se elige con flechas + Enter.
// Un codigo exacto (lector de barras) entra directo sin mostrar la lista.
// ---------------------------------------------------------------------

function iniciarBuscador(){
	var $input = $("#buscarArticulo");
	$input.on("input", function(){
		clearTimeout(busquedaTimer);
		var q = $.trim($input.val());
		if (q.length < 2) { cerrarResultados(); return; }
		busquedaTimer = setTimeout(function(){ buscarArticulos(q, false); }, 220);
	});
	$input.on("keydown", function(e){
		var $items = $("#buscadorResultados li[data-id]");
		var $activo = $items.filter(".activo");
		if (e.key === "ArrowDown" || e.key === "ArrowUp") {
			if (!$items.length) { return; }
			e.preventDefault();
			var i = $items.index($activo);
			i = e.key === "ArrowDown" ? Math.min(i + 1, $items.length - 1) : Math.max(i - 1, 0);
			$items.removeClass("activo").attr("aria-selected", "false").eq(i).addClass("activo").attr("aria-selected", "true")[0].scrollIntoView({ block: "nearest" });
		} else if (e.key === "Enter") {
			e.preventDefault();
			clearTimeout(busquedaTimer);
			if ($activo.length) { elegirResultado($activo.data("id")); return; }
			var q = $.trim($input.val());
			if (q) { buscarArticulos(q, true); }
		} else if (e.key === "Escape") {
			if ($("#buscadorResultados").is(":visible")) { e.preventDefault(); e.stopPropagation(); cerrarResultados(); }
		}
	});
	$(document).on("mousedown", "#buscadorResultados li[data-id]", function(e){
		e.preventDefault();
		elegirResultado($(this).data("id"));
	});
	$input.on("blur", function(){ setTimeout(cerrarResultados, 150); });
}

// directo=true (Enter): con un codigo exacto o un unico resultado se agrega sin preguntar
function buscarArticulos(q, directo){
	var id = ++busquedaId;
	$.get("../ajax/ingreso.php?op=buscarArticulos", { q: q }, function(resp){
		if (id !== busquedaId) { return; }
		var r = appParseJson(resp, null);
		var items = (r && r.items) || [];
		if (directo && items.length && (items[0].exacto || items.length === 1)) {
			elegirResultado(items[0].idarticulo);
			return;
		}
		pintarResultados(items, q);
	});
}

function pintarResultados(items, q){
	var $ul = $("#buscadorResultados").empty();
	if (!items.length) {
		$ul.append('<li class="sin-resultados"><i class="fa fa-search"></i> Nada coincide con "' + appEscapeHtml(q) + '". <a href="articulo.php?nuevo=1" target="_blank">Crear artículo</a></li>').show();
		return;
	}
	items.forEach(function(it, i){
		var stockClase = it.stock <= 0 ? "stock-empty" : (it.stock <= 5 ? "stock-low" : "stock-ok");
		$ul.append(
			'<li role="option" data-id="' + it.idarticulo + '" class="' + (i === 0 ? "activo" : "") + '" aria-selected="' + (i === 0) + '">' +
				'<span class="res-nombre">' + appEscapeHtml(it.nombre) + '<small>' + appEscapeHtml(it.codigo || "sin código") + ' · ' + appEscapeHtml(it.categoria) + '</small></span>' +
				'<span class="stock-pill ' + stockClase + '">' + window.appCantidad(it.stock) + ' ' + appEscapeHtml(it.unidad) + '</span>' +
				'<span class="res-precio">' + money(it.precio_compra) + '<small>últ. costo</small></span>' +
			'</li>'
		);
	});
	$ul.show();
}

function cerrarResultados(){ $("#buscadorResultados").hide().empty(); }

function elegirResultado(idarticulo){
	cerrarResultados();
	$("#buscarArticulo").val("");
	agregarArticulo(idarticulo);
}

// Enter recorre la fila: cantidad -> precio de compra -> precio de venta -> buscador.
// Tambien evita que Enter en cualquier campo envie la compra por accidente.
function iniciarNavegacionDetalle(){
	$("#formulario").on("keydown", "input", function(e){
		if (e.key !== "Enter" || this.id === "buscarArticulo") { return; }
		e.preventDefault();
		var $tr = $(this).closest("tr.filas");
		if (!$tr.length) { return; }
		var orden = ["cantidad[]", "precio_compra[]", "precio_venta[]"];
		var i = orden.indexOf(this.name);
		if (i >= 0 && i < orden.length - 1) { $tr.find("[name='" + orden[i + 1] + "']").focus().select(); }
		else { modificarSubtotales(); $("#buscarArticulo").focus(); }
	});
}

// ---------------------------------------------------------------------
// Detalle de la compra. Igual que en ventas: la cantidad se escribe en la
// presentacion elegida (5 cajas) y el trigger suma cantidad x factor al stock.
// ---------------------------------------------------------------------

function agregarArticulo(idarticulo, idpresentacion, opciones){
	return $.post("../ajax/ingreso.php?op=infoArticulo", { idarticulo: idarticulo }, function(resp){
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

// opciones.valores: datos de un borrador para reponer la fila tal como estaba
function agregarFicha(f, idpresentacion, opciones){
	opciones = opciones || {};
	var pres = presentacionDeFicha(f, idpresentacion);
	var idPres = pres ? pres.idpresentacion : 0;
	// Con tallas/colores se elige en la fila; una fila nueva empieza sin elegir
	var $existente = opciones.valores ? $() : $("#detalles tbody tr.filas").filter(function(){
		return parseInt($(this).attr("data-idarticulo"), 10) === f.idarticulo && (parseInt($(this).find("[name='idpresentacion[]']").val(), 10) || 0) === idPres &&
			!(f.variantes && f.variantes.length);
	}).first();
	if ($existente.length) {
		var $cant = $existente.find("[name='cantidad[]']");
		$cant.val((parseFloat($cant.val()) || 0) + 1);
		modificarSubtotales();
		$('#myModal').modal('hide');
		appNotify("info", f.nombre + ": cantidad " + window.appCantidad($cant.val()));
		resaltarFila($existente);
		setTimeout(function(){ $cant.focus().select(); }, 200);
		guardarBorrador();
		return $existente;
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
		'<td class="num-fila"></td>' +
		'<td><input type="hidden" name="idarticulo[]" value="' + f.idarticulo + '"><input type="hidden" name="idpresentacion[]" value="' + idPres + '"><input type="hidden" name="idvariante[]" value="0"><strong>' + appEscapeHtml(f.nombre) + '</strong>' +
			'<small class="text-soft"> · ' + appEscapeHtml(f.codigo || "") + '</small>' + selectorVariante(f) + selector +
			'<small class="text-soft d-block">Stock actual: ' + window.appCantidad(f.stock) + ' ' + appEscapeHtml(f.unidad) + '</small>' +
			camposLote() + '</td>' +
		'<td><span class="unidad-fila"></span></td>' +
		'<td><input type="number" min="0" name="cantidad[]" value="1" oninput="modificarSubtotales()" onblur="modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="precio_compra[]" value="0.00" oninput="$(this).attr(\'data-manual\',\'1\');modificarSubtotales()" onfocus="this.select()"></td>' +
		'<td><input type="number" step="0.01" min="0" name="precio_venta[]" value="0.00" oninput="$(this).attr(\'data-manual\',\'1\')" onfocus="this.select()" title="Nuevo precio de venta (0 = mantener)"></td>' +
		'<td class="text-right"><span class="money" name="subtotal" data-value="0">0.00</span></td>' +
		'<td><button type="button" class="btn btn-default btn-xs btn-icon btn-quitar" onclick="eliminarDetalle(' + cont + ')" title="Quitar de la compra"><i class="fa fa-times"></i></button></td>'
	);
	cont++; detalles++;
	$('#detalles tbody').append(fila);
	aplicarPreciosPresentacion(fila);
	etiquetaUnidadFila(fila);
	if (opciones.valores) { reponerValoresFila(fila, opciones.valores); }
	modificarSubtotales();
	$('#myModal').modal('hide');
	if (!opciones.valores) {
		resaltarFila(fila);
		var $foco = fila.find(".sel-variante").length ? fila.find(".sel-variante") : fila.find("input[name='cantidad[]']");
		setTimeout(function(){ $foco.focus(); if ($foco.is("input")) { $foco.select(); } }, 200);
		guardarBorrador();
	}
	return fila;
}

function resaltarFila($tr){
	$tr.addClass("fila-nueva");
	setTimeout(function(){ $tr.removeClass("fila-nueva"); }, 900);
}

// Talla/color de la fila: obligatoria si el articulo las tiene
function selectorVariante(f){
	if (!f.variantes || !f.variantes.length) { return ""; }
	return '<select class="form-control input-sm sel-variante" onchange="$(this).closest(\'tr\').find(\'[name=&quot;idvariante[]&quot;]\').val(this.value)" title="Talla y color">' +
		'<option value="0">— Elige talla / color —</option>' +
		f.variantes.map(function(v){ return '<option value="' + v.idvariante + '">' + appEscapeHtml(v.etiqueta) + ' · stock ' + window.appCantidad(v.stock) + '</option>'; }).join("") +
		'</select>';
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
	$("#detalles tbody tr.filas").each(function(i){
		var $tr = $(this);
		$tr.find(".num-fila").text(i + 1);
		total += parseFloat($tr.find("[name='subtotal']").attr("data-value") || 0);
		unidades += (parseFloat($tr.find("[name='cantidad[]']").val()) || 0) * filaFactor($tr);
	});
	$("#posTotal").text(money(total));
	$("#posUnidades").text(window.appCantidad(unidades));
	$("#total_compra").val(total.toFixed(2));
	// Los precios de compra ya incluyen el impuesto: se muestra cuanto es
	var imp = parseFloat($("#impuesto").val()) || 0;
	$("#filaImpuesto").toggle(imp > 0 && total > 0);
	$("#posImpuesto").text(money(total - total / (1 + imp / 100)));
	var count = document.getElementsByName("idarticulo[]").length;
	$("#comprasItemsSeleccionados, #comprasItemsSeleccionadosModal").text(count);
	if (detalles > 0) { $("#btnGuardar").prop("disabled", false); $("#detalleVacio").hide(); }
	else { $("#btnGuardar").prop("disabled", true); $("#detalleVacio").show(); cont = 0; }
}

function eliminarDetalle(indice){
	$("#fila" + indice).remove();
	detalles = detalles - 1;
	calcularTotales();
	guardarBorrador();
	$("#buscarArticulo").focus();
}

// ---------------------------------------------------------------------
// Autoguardado. La compra en curso se copia en esta PC con cada cambio; si
// se cierra la pestana, se va la luz o se sale al listado, al volver se
// repone igual. Se borra al registrar la compra o al descartarla.
// ---------------------------------------------------------------------

function datosBorrador(){
	var filas = [];
	$("#detalles tbody tr.filas").each(function(){
		var $tr = $(this);
		filas.push({
			idarticulo: parseInt($tr.attr("data-idarticulo"), 10),
			nombre: ($tr.data("ficha") || {}).nombre || "",
			idpresentacion: $tr.find("[name='idpresentacion[]']").val(),
			idvariante: $tr.find("[name='idvariante[]']").val(),
			cantidad: $tr.find("[name='cantidad[]']").val(),
			precio_compra: $tr.find("[name='precio_compra[]']").val(),
			precio_venta: $tr.find("[name='precio_venta[]']").val(),
			pc_manual: $tr.find("[name='precio_compra[]']").attr("data-manual") === "1",
			pv_manual: $tr.find("[name='precio_venta[]']").attr("data-manual") === "1",
			lote_codigo: $tr.find("[name='lote_codigo[]']").val() || "",
			lote_vencimiento: $tr.find("[name='lote_vencimiento[]']").val() || ""
		});
	});
	var cab = {};
	["idproveedor", "tipo_comprobante", "serie_comprobante", "num_comprobante", "fecha_hora", "impuesto", "tipo_pago", "medio_pago", "cuenta_pago", "num_operacion", "fecha_vencimiento", "observacion"].forEach(function(c){ cab[c] = $("#" + c).val() || ""; });
	return { cabecera: cab, filas: filas, total: $("#total_compra").val() || "0" };
}

function guardarBorrador(){
	if (!enFormulario || restaurandoBorrador) { return; }
	var d = datosBorrador();
	if (!d.filas.length && !d.cabecera.num_comprobante && !d.cabecera.observacion) { borradorCompra.borrar(); pintarEstadoBorrador(null); return; }
	if (borradorCompra.guardar(d)) { pintarEstadoBorrador(new Date().toISOString()); }
}

function pintarEstadoBorrador(iso){
	$("#estadoBorrador").toggleClass("guardado", !!iso).find("span").text(iso ? "Guardado " + appHoraCorta(iso) : "Autoguardado activo");
}

function mostrarAvisoBorradorListado(){
	var b = borradorCompra.leer();
	var hay = b && b.filas && b.filas.length;
	if (hay) { $("#avisoBorradorResumen").text("(" + b.filas.length + " artículo(s) · " + money(b.total) + " · " + appHoraCorta(b.guardado) + ")"); }
	$("#avisoBorradorListado").toggle(!!hay && !enFormulario);
}

function descartarBorrador(enForm){
	appConfirm("Se borrará la compra sin terminar. ¿Continuar?", function(){
		borradorCompra.borrar();
		if (enForm) { mostrarform(true); } else { $("#avisoBorradorListado").hide(); }
	}, { titulo: "Descartar borrador", ok: "Sí, descartar", tipo: "danger" });
}

function restaurarBorrador(){
	var b = borradorCompra.leer();
	if (!b || !b.filas || !b.filas.length) { pintarEstadoBorrador(null); return; }
	restaurandoBorrador = true;
	var c = b.cabecera || {};
	["tipo_comprobante", "serie_comprobante", "num_comprobante", "fecha_hora", "impuesto", "cuenta_pago", "num_operacion", "fecha_vencimiento", "observacion"].forEach(function(k){ if (typeof c[k] !== "undefined") { $("#" + k).val(c[k]); } });
	var fijarProveedor = function(){ if (c.idproveedor) { $("#idproveedor").val(String(c.idproveedor)).selectpicker("refresh"); } };
	if (proveedoresCargados) { fijarProveedor(); } else { cargarProveedores(c.idproveedor); }
	// Con restaurandoBorrador activo estas llamadas no guardan: un guardado a
	// medio restaurar (sin filas todavia) borraria el borrador
	fijarTipoPago(c.tipo_pago === "CREDITO" ? "CREDITO" : "CONTADO");
	appFijarMedioPago($("#grupoMedioPago .medio-grid"), c.medio_pago || "EFECTIVO");

	// Cada fila se vuelve a pedir al servidor para tener stock y presentaciones al dia
	var faltantes = [];
	var cadena = $.Deferred().resolve();
	b.filas.forEach(function(v){
		cadena = cadena.then(function(){
			return $.post("../ajax/ingreso.php?op=infoArticulo", { idarticulo: v.idarticulo }).then(function(resp){
				var f = appParseJson(resp, null);
				if (!f || !f.ok) { faltantes.push(v.nombre || ("#" + v.idarticulo)); return; }
				agregarFicha(f, v.idpresentacion, { valores: v });
			}, function(){ faltantes.push(v.nombre || ("#" + v.idarticulo)); return $.Deferred().resolve(); });
		});
	});
	cadena.always(function(){
		restaurandoBorrador = false;
		modificarSubtotales();
		$("#avisoBorradorFormTexto").text("Recuperamos la compra que dejaste sin registrar (" + appHoraCorta(b.guardado) + ").");
		$("#avisoBorradorForm").show();
		pintarEstadoBorrador(b.guardado);
		if (faltantes.length) { appNotify("warning", "Ya no están disponibles: " + faltantes.join(", "), 8000); }
		guardarBorrador();
	});
}

function reponerValoresFila($tr, v){
	if (parseInt(v.idvariante, 10) > 0) {
		$tr.find("[name='idvariante[]']").val(v.idvariante);
		$tr.find(".sel-variante").val(String(v.idvariante));
	}
	$tr.find("[name='cantidad[]']").val(v.cantidad);
	var $pc = $tr.find("[name='precio_compra[]']"), $pv = $tr.find("[name='precio_venta[]']");
	if (v.pc_manual) { $pc.val(v.precio_compra).attr("data-manual", "1"); }
	if (v.pv_manual) { $pv.val(v.precio_venta).attr("data-manual", "1"); }
	$tr.find("[name='lote_codigo[]']").val(v.lote_codigo || "");
	$tr.find("[name='lote_vencimiento[]']").val(v.lote_vencimiento || "");
}

init();
