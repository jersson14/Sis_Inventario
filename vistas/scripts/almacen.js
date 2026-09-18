/* Almacenes, transferencias y stock por almacen */
var URL_ALM = "../ajax/almacen.php?op=";
var tablaTr = null;
var tablaSt = null;
var trLineas = [];      // {idarticulo, nombre, unidad, fraccion, variantes, lotes, idvariante, idlote, cantidad, stock}
var trVer = null;       // transferencia abierta en "Ver / recibir"

function money(v){ return window.appMoney(v, 2); }
function cant(v){ return window.appCantidad(v); }
function esc(t){ return appEscapeHtml(t == null ? "" : String(t)); }
function fecha(iso){ return iso ? String(iso).substr(0, 10).split("-").reverse().join("/") : ""; }

// ---------------------------------------------------------------------
// Almacenes
// ---------------------------------------------------------------------

function cargarAlmacenes(){
	$.get(URL_ALM + "listarAlmacenes", function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { return; }
		var html = r.almacenes.map(function(a){
			var activo = String(a.condicion) === "1";
			var acciones = "";
			if (r.puede_gestionar) {
				acciones = '<div class="almacen-acciones">' +
					'<button type="button" class="btn btn-default btn-xs btn-editar-alm" data-id="' + a.idalmacen + '" title="Editar nombre, dirección y responsable"><i class="fa fa-pencil"></i></button> ' +
					(activo && String(a.principal) !== "1" ? '<button type="button" class="btn btn-default btn-xs btn-principal-alm" data-id="' + a.idalmacen + '" title="Marcar como principal (recibe los ajustes de stock hechos a mano)"><i class="fa fa-star-o"></i></button> ' : "") +
					(String(a.principal) !== "1" ? '<button type="button" class="btn btn-default btn-xs btn-estado-alm" data-id="' + a.idalmacen + '" data-activar="' + (activo ? 0 : 1) + '" title="' + (activo ? "Desactivar" : "Activar") + '"><i class="fa ' + (activo ? "fa-toggle-on" : "fa-toggle-off") + '"></i></button>' : "") +
					"</div>";
			}
			return '<div class="almacen-card' + (activo ? "" : " inactivo") + '" data-json="' + esc(JSON.stringify(a)) + '">' +
				'<div class="almacen-card-cab"><i class="fa fa-building-o"></i> <strong>' + esc(a.nombre) + "</strong>" +
				(String(a.principal) === "1" ? ' <span class="label label-primary">principal</span>' : "") + (activo ? "" : ' <span class="label label-default">inactivo</span>') + acciones + "</div>" +
				(a.direccion ? '<div class="text-soft"><i class="fa fa-map-marker"></i> ' + esc(a.direccion) + "</div>" : "") +
				(a.responsable ? '<div class="text-soft"><i class="fa fa-user"></i> ' + esc(a.responsable) + "</div>" : "") +
				'<div class="almacen-card-num"><div><strong>' + a.articulos + "</strong><span>productos con stock</span></div><div><strong>" + money(a.valor) + "</strong><span>valor a costo</span></div><div><strong>" + a.usuarios + "</strong><span>usuarios</span></div></div>" +
				"</div>";
		}).join("");
		if (r.transito.pendientes > 0) {
			html += '<div class="almacen-card transito"><div class="almacen-card-cab"><i class="fa fa-truck"></i> <strong>En tránsito</strong></div><div class="almacen-card-num"><div><strong>' + r.transito.pendientes + '</strong><span>transferencias en camino</span></div><div><strong>' + money(r.transito.valor) + "</strong><span>valor a costo</span></div></div></div>";
			$("#badgeTransito").text(r.transito.pendientes).show();
		} else {
			$("#badgeTransito").hide();
		}
		if (r.almacenes.length < 2 && r.puede_gestionar) {
			html += '<div class="almacen-card ayuda"><i class="fa fa-lightbulb-o"></i> Tienes un solo almacén. Crea otro (depósito, segundo local…) con <strong>+ Almacén</strong>: desde ese momento cada uno lleva su propio stock y aparece el selector de almacén arriba.</div>';
		}
		$("#almacenesCards").html(html);
	});
}

function abrirAlmacen(a){
	$("#al_id").val(a ? a.idalmacen : "");
	$("#al_nombre").val(a ? a.nombre : "");
	$("#al_direccion").val(a ? a.direccion : "");
	$("#al_responsable").val(a ? a.responsable : "");
	$("#alTitulo").html('<i class="fa fa-building-o"></i> ' + (a ? "Editar almacén" : "Nuevo almacén"));
	$("#modalAlmacen").modal("show");
}

// ---------------------------------------------------------------------
// Transferencias
// ---------------------------------------------------------------------

function listarTransferencias(){
	tablaTr = $("#tblTransferencias").DataTable({
		aProcessing: true, aServerSide: true, bDestroy: true, iDisplayLength: 15,
		dom: "Bfrtip",
		buttons: window.appDataTableButtons("Transferencias", true),
		order: [[1, "desc"]],
		columnDefs: [{ targets: 1, render: window.appOrdenPorDato }],
		ajax: {
			url: URL_ALM + "listarTransferencias", type: "get", dataType: "json",
			data: function(d){ d.fecha_inicio = $("#tr_inicio").val(); d.fecha_fin = $("#tr_fin").val(); d.estado = $("#tr_estado").val(); },
			error: function(e){ console.log(e.responseText); }
		}
	});
}

function abrirTransferencia(){
	trLineas = [];
	pintarLineasTr();
	var actual = window.appAlmacen ? String(window.appAlmacen.id) : "";
	if (actual && $("#tr_origen option[value='" + actual + "']").length) { $("#tr_origen").val(actual); }
	var otro = $("#tr_destino option").filter(function(){ return this.value !== $("#tr_origen").val(); }).first().val();
	$("#tr_destino").val(otro || "");
	$("#tr_obs, #tr_scan").val("");
	$("#tr_ya").prop("checked", false);
	$("#trSugerencias").empty();
	$("#modalTransferencia").modal("show");
}

function claveLinea(l){ return l.idarticulo + "|" + (l.idvariante || 0) + "|" + (l.idlote || 0); }

function stockLinea(l){
	if (l.variantes.length) {
		var v = l.variantes.filter(function(x){ return x.idvariante === parseInt(l.idvariante, 10); })[0];
		return v ? v.stock : 0;
	}
	if (l.idlote) {
		var lt = l.lotes.filter(function(x){ return x.idlote === parseInt(l.idlote, 10); })[0];
		return lt ? lt.stock : 0;
	}
	return l.stock;
}

function pintarLineasTr(){
	if (!trLineas.length) {
		$("#trLineas tbody").html('<tr class="vacio"><td colspan="5" class="text-center text-soft">Escanea o busca los productos que vas a enviar.</td></tr>');
		return;
	}
	$("#trLineas tbody").html(trLineas.map(function(l, i){
		var detalle = "";
		if (l.variantes.length) {
			detalle = '<select class="form-control input-sm tr-var" data-i="' + i + '"><option value="0">— Talla / color —</option>' +
				l.variantes.map(function(v){ return '<option value="' + v.idvariante + '"' + (v.idvariante === parseInt(l.idvariante, 10) ? " selected" : "") + ">" + esc(v.etiqueta) + " (" + cant(v.stock) + ")</option>"; }).join("") + "</select>";
		} else if (l.lotes.length) {
			detalle = '<select class="form-control input-sm tr-lote" data-i="' + i + '"><option value="0">Automático (primero lo que vence antes)</option>' +
				l.lotes.map(function(x){ return '<option value="' + x.idlote + '"' + (x.idlote === parseInt(l.idlote, 10) ? " selected" : "") + ">" + esc((x.codigo_lote || "Lote #" + x.idlote) + (x.fecha_vencimiento ? " · vence " + fecha(x.fecha_vencimiento) : "")) + " (" + cant(x.stock) + ")</option>"; }).join("") + "</select>";
		} else {
			detalle = '<span class="text-soft">—</span>';
		}
		var hay = stockLinea(l);
		var falta = (parseFloat(l.cantidad) || 0) > hay + 0.0005;
		return "<tr" + (falta ? ' class="danger"' : "") + "><td><strong>" + esc(l.nombre) + '</strong><br><small class="text-soft">' + esc(l.codigo) + "</small></td><td>" + detalle + "</td>" +
			'<td class="text-right">' + cant(hay) + " " + esc(l.unidad) + "</td>" +
			'<td><input type="number" class="form-control input-sm text-right tr-cant" data-i="' + i + '" min="0" step="' + (l.fraccion ? "0.001" : "1") + '" value="' + l.cantidad + '"></td>' +
			'<td><button type="button" class="btn btn-default btn-xs tr-quitar" data-i="' + i + '" title="Quitar"><i class="fa fa-times"></i></button></td></tr>';
	}).join(""));
}

function agregarProductoTr(codigo, idarticulo){
	var datos = { idorigen: $("#tr_origen").val() };
	if (idarticulo) { datos.idarticulo = idarticulo; } else { datos.codigo = codigo; }
	$.post(URL_ALM + "fichaTransferencia", datos, function(resp){
		var r = appParseJson(resp, { ok: false, message: resp });
		$("#trSugerencias").empty();
		if (!r.ok) {
			window.appSonido("error");
			if (r.sugerencias && r.sugerencias.length) {
				$("#trSugerencias").html(r.sugerencias.map(function(s){ return '<a href="#" class="list-group-item tr-sug" data-id="' + s.idarticulo + '">' + esc(s.nombre) + ' <small class="text-soft">' + esc(s.codigo) + "</small></a>"; }).join(""));
			} else {
				appNotify("warning", r.message || "No se encontró el producto.");
			}
			return;
		}
		window.appSonido("ok");
		var f = r.ficha;
		var lec = r.lectura || {};
		var linea = {
			idarticulo: f.idarticulo, nombre: f.nombre, codigo: f.codigo, unidad: f.unidad, fraccion: f.fraccion, stock: f.stock,
			variantes: f.variantes, lotes: f.lotes, idvariante: lec.idvariante || 0, idlote: lec.idlote || 0, cantidad: parseFloat(lec.factor) || 1
		};
		var existente = trLineas.filter(function(l){ return claveLinea(l) === claveLinea(linea); })[0];
		if (existente) { existente.cantidad = Math.round(((parseFloat(existente.cantidad) || 0) + linea.cantidad) * 1000) / 1000; }
		else { trLineas.push(linea); }
		pintarLineasTr();
	});
}

function enviarTransferencia(e){
	e.preventDefault();
	if ($("#tr_origen").val() === $("#tr_destino").val()) { appNotify("warning", "El origen y el destino deben ser distintos."); return; }
	if (!trLineas.length) { appNotify("warning", "Agrega al menos un producto."); return; }
	var fd = new FormData();
	fd.append("idorigen", $("#tr_origen").val());
	fd.append("iddestino", $("#tr_destino").val());
	fd.append("observacion", $("#tr_obs").val());
	if ($("#tr_ya").is(":checked")) { fd.append("recibir_ya", "1"); }
	for (var i = 0; i < trLineas.length; i++) {
		var l = trLineas[i];
		if (l.variantes.length && !(parseInt(l.idvariante, 10) > 0)) { appNotify("warning", "Elige la talla / color de " + l.nombre + "."); return; }
		if (!((parseFloat(l.cantidad) || 0) > 0)) { appNotify("warning", "Indica cuánto enviar de " + l.nombre + "."); return; }
		fd.append("idarticulo[]", l.idarticulo);
		fd.append("idvariante[]", l.idvariante || 0);
		fd.append("idlote[]", l.idlote || 0);
		fd.append("cantidad[]", l.cantidad);
	}
	appSetLoading("#btnEnviarTr", true);
	$.ajax({
		url: URL_ALM + "enviar", type: "POST", data: fd, contentType: false, processData: false,
		success: function(resp){
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "", 7000);
			if (r.ok) { $("#modalTransferencia").modal("hide"); tablaTr.ajax.reload(null, false); cargarAlmacenes(); if (tablaSt) { cargarStock(); } }
		},
		complete: function(){ appSetLoading("#btnEnviarTr", false); }
	});
}

function verTransferencia(id){
	$.get(URL_ALM + "verTransferencia", { id: id }, function(resp){
		var t = appParseJson(resp, null);
		if (!t || !t.ok) { appNotify("error", (t && t.message) || "No se pudo cargar."); return; }
		trVer = t;
		var pendiente = t.estado === "ENVIADA" && t.puede_gestionar;
		$("#verTrTitulo").html('<i class="fa fa-exchange"></i> Transferencia #' + t.idtransferencia + ' <span class="label ' + (t.estado === "ENVIADA" ? "label-warning" : (t.estado === "RECIBIDA" ? "label-success" : "label-default")) + '">' + esc(t.estado) + "</span>");
		$("#verTrDatos").html("<strong>" + esc(t.origen) + '</strong> <i class="fa fa-long-arrow-right"></i> <strong>' + esc(t.destino) + "</strong> · enviada " + esc(t.fecha_hora) + " por " + esc(t.usuario) +
			(t.recibio ? " · recibida " + esc(t.fecha_recepcion) + " por " + esc(t.recibio) : "") + (t.observacion ? " · " + esc(t.observacion) : ""));
		$("#verTrTabla tbody").html(t.detalle.map(function(d){
			var rec = pendiente
				? '<input type="number" class="form-control input-sm text-right tr-rec" data-id="' + d.iddetalle + '" min="0" max="' + d.cantidad + '" step="any" value="' + parseFloat(d.cantidad) + '">'
				: (d.cantidad_recibida === null ? "—" : cant(d.cantidad_recibida) + (parseFloat(d.cantidad_recibida) + 0.0005 < parseFloat(d.cantidad) ? ' <span class="label label-danger">faltó ' + cant(d.cantidad - d.cantidad_recibida) + "</span>" : ""));
			return "<tr><td><strong>" + esc(d.articulo) + '</strong> <small class="text-soft">' + esc(d.codigo) + "</small></td><td>" + (d.lote_codigo || d.lote_vencimiento ? esc((d.lote_codigo || "s/c") + (d.lote_vencimiento ? " · " + fecha(d.lote_vencimiento) : "")) : '<span class="text-soft">—</span>') +
				'</td><td class="text-right">' + cant(d.cantidad) + " " + esc(d.unidad) + '</td><td class="text-right">' + rec + '</td><td class="text-right">' + money(d.costo_unitario) + "</td></tr>";
		}).join(""));
		$("#verTrAyuda, #verTrObsGrupo, #btnRecibirTr, #btnAnularTr").toggle(pendiente);
		$("#verTrObs").val("");
		$("#modalVerTr").modal("show");
	});
}

function recibirTransferencia(){
	if (!trVer) { return; }
	var datos = { idtransferencia: trVer.idtransferencia, observacion: $("#verTrObs").val() };
	var faltan = 0;
	$("#verTrTabla .tr-rec").each(function(){
		var d = trVer.detalle.filter(function(x){ return String(x.iddetalle) === String($(this).data("id")); }.bind(this))[0];
		var v = parseFloat(String($(this).val()).replace(",", ".")) || 0;
		datos["recibida[" + $(this).data("id") + "]"] = v;
		if (d && v + 0.0005 < parseFloat(d.cantidad)) { faltan++; }
	});
	var msg = faltan ? "Hay " + faltan + " producto(s) con faltante: la diferencia se dará de baja. ¿Confirmar la recepción?" : "Se confirma que llegó todo. El stock entra a " + esc(trVer.destino) + ".";
	appConfirm(msg, function(){
		appSetLoading("#btnRecibirTr", true);
		$.post(URL_ALM + "recibir", datos, function(resp){
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "", 7000);
			if (r.ok) { $("#modalVerTr").modal("hide"); tablaTr.ajax.reload(null, false); cargarAlmacenes(); }
		}).always(function(){ appSetLoading("#btnRecibirTr", false); });
	}, { titulo: "Recibir transferencia", ok: "Recibir", tipo: "success" });
}

function anularTransferencia(){
	if (!trVer) { return; }
	appConfirm("La mercadería vuelve a " + esc(trVer.origen) + ". ¿Anular la transferencia #" + trVer.idtransferencia + "?", function(){
		$.post(URL_ALM + "anularTransferencia", { idtransferencia: trVer.idtransferencia }, function(resp){
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "");
			if (r.ok) { $("#modalVerTr").modal("hide"); tablaTr.ajax.reload(null, false); cargarAlmacenes(); }
		});
	}, { titulo: "Anular transferencia", ok: "Anular", tipo: "danger" });
}

// ---------------------------------------------------------------------
// Stock por almacen
// ---------------------------------------------------------------------

function cargarStock(){
	$.get(URL_ALM + "stockPorAlmacen", { q: $("#st_q").val(), solo_con_stock: $("#st_con").is(":checked") ? 1 : 0 }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { return; }
		if (tablaSt) { tablaSt.destroy(); $("#tblStockAlm").empty(); }
		var cab = "<tr><th>Artículo</th><th>Código</th>" + r.almacenes.map(function(a){ return '<th class="text-right">' + esc(a.nombre) + "</th>"; }).join("") + '<th class="text-right">En tránsito</th><th class="text-right">Total</th><th class="text-right">Valor</th></tr>';
		var filas = r.filas.map(function(f){
			return "<tr><td>" + esc(f.nombre) + "</td><td>" + esc(f.codigo) + "</td>" +
				r.almacenes.map(function(a){ var s = f.almacenes[a.idalmacen] || 0; return '<td class="text-right' + (s > 0 ? "" : " text-soft") + '">' + cant(s) + "</td>"; }).join("") +
				'<td class="text-right' + (f.transito > 0 ? " text-warning" : " text-soft") + '">' + cant(f.transito) + '</td><td class="text-right"><strong>' + cant(f.total) + " " + esc(f.unidad) + '</strong></td><td class="text-right">' + money(f.total * f.costo) + "</td></tr>";
		}).join("");
		$("#tblStockAlm").html("<thead>" + cab + "</thead><tbody>" + filas + "</tbody>");
		tablaSt = $("#tblStockAlm").DataTable({ dom: "Bfrtip", buttons: window.appDataTableButtons("Stock por almacén", false), iDisplayLength: 25, order: [[0, "asc"]] });
	});
}

// ---------------------------------------------------------------------

$(function(){
	cargarAlmacenes();
	listarTransferencias();
	$("#btnFiltrarTr").on("click", function(){ tablaTr.ajax.reload(); });
	$("#lnkStock").on("shown.bs.tab", function(){ if (!tablaSt) { cargarStock(); } });
	$("#btnFiltrarSt").on("click", cargarStock);

	$("#btnNuevoAlmacen").on("click", function(){ abrirAlmacen(null); });
	$("#almacenesCards").on("click", ".btn-editar-alm", function(){ abrirAlmacen(JSON.parse($(this).closest(".almacen-card").attr("data-json"))); });
	$("#almacenesCards").on("click", ".btn-estado-alm", function(){
		var datos = { idalmacen: $(this).data("id"), activar: $(this).data("activar") };
		$.post(URL_ALM + "estadoAlmacen", datos, function(resp){
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "", 6000);
			if (r.ok) { setTimeout(function(){ window.location.reload(); }, 700); }
		});
	});
	$("#almacenesCards").on("click", ".btn-principal-alm", function(){
		var id = $(this).data("id");
		appConfirm("El almacén principal recibe las correcciones de stock hechas a mano (ficha del artículo, importación). ¿Marcarlo como principal?", function(){
			$.post(URL_ALM + "hacerPrincipal", { idalmacen: id }, function(resp){
				var r = appParseJson(resp, { ok: false, message: resp });
				appNotify(r.ok ? "success" : "error", r.message || "");
				if (r.ok) { cargarAlmacenes(); }
			});
		});
	});
	$("#formAlmacen").on("submit", function(e){
		e.preventDefault();
		appSetLoading("#btnGuardarAlmacen", true);
		$.post(URL_ALM + "guardarAlmacen", $(this).serialize(), function(resp){
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "");
			if (r.ok) { $("#modalAlmacen").modal("hide"); setTimeout(function(){ window.location.reload(); }, 600); }
		}).always(function(){ appSetLoading("#btnGuardarAlmacen", false); });
	});

	$("#btnNuevaTransferencia").on("click", abrirTransferencia);
	$("#tr_origen").on("change", function(){
		if (trLineas.length) { appNotify("info", "Cambiaste el origen: vuelve a agregar los productos (el stock es otro)."); }
		trLineas = [];
		pintarLineasTr();
	});
	window.appLectorCodigo("#tr_scan", function(codigo){ $("#tr_scan").val(""); agregarProductoTr(codigo, 0); });
	$("#trSugerencias").on("click", ".tr-sug", function(e){ e.preventDefault(); agregarProductoTr("", $(this).data("id")); $("#tr_scan").focus(); });
	$("#trLineas").on("change", ".tr-var", function(){ trLineas[$(this).data("i")].idvariante = parseInt(this.value, 10) || 0; pintarLineasTr(); });
	$("#trLineas").on("change", ".tr-lote", function(){ trLineas[$(this).data("i")].idlote = parseInt(this.value, 10) || 0; pintarLineasTr(); });
	$("#trLineas").on("change", ".tr-cant", function(){
		var l = trLineas[$(this).data("i")];
		l.cantidad = window.appNormalizarCantidad($(this).val(), l.fraccion);
		pintarLineasTr();
	});
	$("#trLineas").on("click", ".tr-quitar", function(){ trLineas.splice($(this).data("i"), 1); pintarLineasTr(); });
	$("#formTransferencia").on("submit", enviarTransferencia);
	$("#modalTransferencia").on("shown.bs.modal", function(){ $("#tr_scan").focus(); });
	$("#btnRecibirTr").on("click", recibirTransferencia);
	$("#btnAnularTr").on("click", anularTransferencia);
});
