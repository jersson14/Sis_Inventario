/* Devoluciones: nota de credito por items desde el listado o el detalle de una venta */
var devData = null;

function devMoney(v){ return window.appMoney(v, 2); }

function abrirDevolucion(idventa){
	$.post("../ajax/venta.php?op=devolvible", { idventa: idventa }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { appNotify("error", (r && r.message) || "No se pudo cargar la venta."); return; }
		if (r.estado !== "Aceptado") { appNotify("warning", "La venta está anulada: no hay nada que devolver."); return; }
		devData = r;
		$("#dev_idventa").val(r.idventa);
		$("#devTitulo").text(r.tipo_comprobante + " " + r.serie_comprobante + "-" + r.num_comprobante);
		$("#devDatos").html("<strong>" + appEscapeHtml(r.cliente) + "</strong> · " + appEscapeHtml(r.fecha) + " · total " + devMoney(r.total_venta) + " · " + (r.tipo_pago === "CREDITO" ? "al crédito" : "al contado"));
		if (r.notas && r.notas.length) {
			$("#devNotas").html('<i class="fa fa-info-circle"></i> Devoluciones anteriores: ' + r.notas.map(function(n){ return appEscapeHtml(n.numero) + " (" + devMoney(n.total) + ")"; }).join(", ")).show();
		} else {
			$("#devNotas").hide();
		}
		var html = "";
		r.lineas.forEach(function(l, i){
			var hay = l.disponible > 0;
			html += '<tr data-i="' + i + '"' + (hay ? "" : ' class="text-soft"') + ">" +
				"<td><strong>" + appEscapeHtml(l.nombre) + '</strong><br><small class="text-soft">' + window.appCantidad(l.vendido) + " " + appEscapeHtml(l.unidad) + " × " + devMoney(l.precio) + (l.descuento > 0 ? " · dscto " + devMoney(l.descuento) : "") + "</small></td>" +
				'<td class="text-right">' + window.appCantidad(l.vendido) + "</td>" +
				'<td class="text-right">' + (l.devuelto > 0 ? window.appCantidad(l.devuelto) : "—") + "</td>" +
				'<td class="text-right">' + (hay
					? '<div class="input-group input-group-sm"><input type="number" class="form-control text-right dev-cant" min="0" max="' + l.disponible + '" step="' + (l.fraccion ? "0.001" : "1") + '" placeholder="0" title="Cuánto devuelve (máximo ' + window.appCantidad(l.disponible) + ')"><span class="input-group-btn"><button type="button" class="btn btn-default dev-todo" title="Devolver todo lo que queda (' + window.appCantidad(l.disponible) + ')">Todo</button></span></div>'
					: '<span class="label label-default">Ya devuelto</span>') + "</td>" +
				'<td class="text-center">' + (hay ? '<input type="checkbox" class="dev-reingresa" checked title="Desmarca si vuelve dañado">' : "") + "</td>" +
				'<td class="text-right dev-importe">—</td></tr>';
		});
		$("#devTabla tbody").html(html);
		$("#devDeuda").toggle(r.deuda > 0).html('<i class="fa fa-info-circle"></i> Esta venta tiene una deuda pendiente de <strong>' + devMoney(r.deuda) + "</strong>: la devolución la reduce primero y solo lo que pase de ese monto se devuelve al cliente.");
		$("#devAutoriza").toggle(!r.puede_devolver);
		$("#devAutoriza input").val("");
		$("#dev_motivo_det").val("");
		$("#dev_motivo_tipo").val("Cliente se arrepintió");
		$("#dev_reintegro").val("EFECTIVO");
		recalcDevolucion();
		$("#modalDevolucion").modal("show");
	});
}

// Importe estimado por linea (el servidor reparte el descuento exacto)
function recalcDevolucion(){
	if (!devData) { return; }
	var total = 0;
	$("#devTabla tbody tr").each(function(){
		var l = devData.lineas[parseInt($(this).data("i"), 10)];
		var $in = $(this).find(".dev-cant");
		if (!$in.length) { return; }
		var cant = window.appNormalizarCantidad($in.val(), l.fraccion);
		if (cant > l.disponible) { cant = l.disponible; $in.val(cant); }
		var imp = cant > 0 ? Math.round((cant * l.precio - l.descuento * cant / l.vendido) * 100) / 100 : 0;
		$(this).find(".dev-importe").text(cant > 0 ? devMoney(imp) : "—");
		$(this).toggleClass("warning", cant > 0);
		total += imp;
	});
	$("#devTotal").text(devMoney(total));
	return total;
}

function registrarDevolucion(e){
	e.preventDefault();
	if (!devData) { return; }
	var fd = new FormData();
	fd.append("idventa", devData.idventa);
	var hay = false;
	$("#devTabla tbody tr").each(function(){
		var l = devData.lineas[parseInt($(this).data("i"), 10)];
		var cant = window.appNormalizarCantidad($(this).find(".dev-cant").val(), l.fraccion);
		if (!(cant > 0)) { return; }
		hay = true;
		fd.append("iddetalle_venta[]", l.iddetalle_venta);
		fd.append("cantidad_dev[]", cant);
		fd.append("reingresa[]", $(this).find(".dev-reingresa").is(":checked") ? "1" : "0");
	});
	if (!hay) { appNotify("warning", "Indica cuánto se devuelve de al menos un producto."); return; }
	var tipo = $("#dev_motivo_tipo").val();
	var det = $.trim($("#dev_motivo_det").val());
	if (tipo === "Otro" && det.length < 3) { appNotify("warning", "Escribe el motivo de la devolución."); $("#dev_motivo_det").focus(); return; }
	fd.append("motivo", tipo + (det ? ": " + det : ""));
	fd.append("reintegro", $("#dev_reintegro").val());
	if (!devData.puede_devolver) {
		fd.append("autoriza_login", $("#devAutoriza input[name=autoriza_login]").val());
		fd.append("autoriza_clave", $("#devAutoriza input[name=autoriza_clave]").val());
	}
	var imprimir = $("#devImprimir").is(":checked");
	appSetLoading("#btnDevolver", true);
	$.ajax({
		url: "../ajax/venta.php?op=devolver", type: "POST", data: fd, contentType: false, processData: false,
		success: function(resp){
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "", r.ok ? 8000 : 7000);
			if (!r.ok) { return; }
			$("#modalDevolucion").modal("hide");
			if (imprimir) { window.open("../reportes/exTicketNC.php?id=" + parseInt(r.idnota, 10), "_blank"); }
			if (typeof tabla !== "undefined" && tabla && tabla.ajax) { tabla.ajax.reload(null, false); }
			if (typeof cargarCajaPos === "function") { cargarCajaPos(); }
			if (typeof cargarResumenDia === "function") { cargarResumenDia(); }
		},
		complete: function(){ appSetLoading("#btnDevolver", false); }
	});
}

$(function(){
	$("#devTabla").on("input change", ".dev-cant, .dev-reingresa", recalcDevolucion);
	$("#devTabla").on("click", ".dev-todo", function(){
		var $tr = $(this).closest("tr");
		var l = devData.lineas[parseInt($tr.data("i"), 10)];
		$tr.find(".dev-cant").val(l.disponible);
		recalcDevolucion();
	});
	$("#formDevolucion").on("submit", registrarDevolucion);
	$("#detDevolver").on("click", function(){
		var id = $("#detTicket").data("id");
		$("#modalDetalleVenta").modal("hide");
		setTimeout(function(){ abrirDevolucion(id); }, 350);
	});
});
