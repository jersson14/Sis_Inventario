/* Caja diaria */
var dtMovCaja;
var dtHistCaja;
var cajaAbierta = false;

function money(v){ return window.appMoney ? window.appMoney(v, 2) : ((window.appCurrencySymbol || "S/") + " " + Number(v || 0).toFixed(2)); }

function fechaBonita(s){
	if (!s) { return "-"; }
	var d = new Date(String(s).replace(" ", "T"));
	if (isNaN(d.getTime())) { return s; }
	return ("0" + d.getDate()).slice(-2) + "/" + ("0" + (d.getMonth() + 1)).slice(-2) + "/" + d.getFullYear() + " " + ("0" + d.getHours()).slice(-2) + ":" + ("0" + d.getMinutes()).slice(-2);
}

function cargarEstadoCaja(){
	$.get("../ajax/caja.php?op=estado", function(resp){
		var r = appParseJson(resp, { abierta: false });
		cajaAbierta = !!r.abierta;
		if (r.abierta) {
			$("#cajaEstadoCerrada").hide();
			$("#cajaEstadoAbierta").show();
			$("#btnAbrirCaja").hide();
			$("#btnMovimiento, #btnCerrarCaja").show();
			$("#kpiApertura").text(money(r.monto_apertura));
			$("#kpiAperturaFecha").text("#" + r.idcaja + " · desde " + fechaBonita(r.fecha_apertura));
			$("#kpiIngresos").text(money(r.ingresos));
			$("#kpiMovs").text((r.num_movimientos || 0) + " movimiento(s)");
			$("#kpiEgresos").text(money(r.egresos));
			$("#kpiSistema").text(money(r.sistema));
			$("#cierreSistema").text(money(r.sistema));
			$("#monto_cierre_real").attr("placeholder", Number(r.sistema || 0).toFixed(2));
			var medios = r.medios || [];
			if (!medios.length) {
				$("#cajaMedios").html('<div class="text-soft">Sin movimientos aún.</div>');
			} else {
				var html = '<table class="table table-condensed mb-0"><thead><tr><th>Medio</th><th class="text-right">Ingresos</th><th class="text-right">Egresos</th><th class="text-right">Neto</th></tr></thead><tbody>';
				medios.forEach(function(m){
					html += '<tr><td><strong>' + appEscapeHtml(m.medio_pago) + '</strong></td><td class="text-right">' + money(m.ingresos) + '</td><td class="text-right">' + money(m.egresos) + '</td><td class="text-right money">' + money(m.neto) + '</td></tr>';
				});
				html += '</tbody></table>';
				$("#cajaMedios").html(html);
			}
		} else {
			$("#cajaEstadoCerrada").show();
			$("#cajaEstadoAbierta").hide();
			$("#btnAbrirCaja").show();
			$("#btnMovimiento, #btnCerrarCaja").hide();
		}
	});
}

function listarMovimientosCaja(){
	dtMovCaja = $("#tblmovcaja").DataTable({
		aProcessing: true, aServerSide: true, bDestroy: true, iDisplayLength: 10,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Movimientos de Caja', false),
		order: [[0, "desc"]],
		ajax: { url: "../ajax/caja.php?op=listarMovimientos", type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } }
	});
}

function listarHistorialCaja(){
	dtHistCaja = $("#tblhistcaja").DataTable({
		aProcessing: true, aServerSide: true, bDestroy: true, iDisplayLength: 10,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Historial de Cajas', false),
		order: [[0, "desc"]],
		columnDefs: [{ orderable: false, targets: [11] }],
		ajax: {
			url: "../ajax/caja.php?op=historial", type: "get", dataType: "json",
			data: function(d){ d.todos = $("#chkTodasCajas").is(":checked") ? 1 : 0; },
			error: function(e){ console.log(e.responseText); }
		}
	});
}

function refrescarTodoCaja(){
	cargarEstadoCaja();
	if (dtMovCaja) dtMovCaja.ajax.reload(null, false);
	if (dtHistCaja) dtHistCaja.ajax.reload(null, false);
}

function verDetalleCaja(idcaja){
	$.get("../ajax/caja.php?op=detalle", { idcaja: idcaja }, function(resp){
		var d = appParseJson(resp, null);
		if (!d || !d.ok) { appNotify("error", (d && d.message) || "No se pudo cargar la caja."); return; }
		$("#detCajaTitulo").text("#" + d.idcaja + " · " + d.usuario);
		var estado = d.estado === "ABIERTA" ? '<span class="label bg-green">ABIERTA</span>' : '<span class="label bg-aqua">CERRADA</span>';
		var html = '<div class="row">' +
			'<div class="col-sm-6"><p><strong>Usuario:</strong> ' + appEscapeHtml(d.usuario) + '</p><p><strong>Apertura:</strong> ' + fechaBonita(d.fecha_apertura) + '</p><p><strong>Cierre:</strong> ' + fechaBonita(d.fecha_cierre) + '</p><p><strong>Estado:</strong> ' + estado + '</p></div>' +
			'<div class="col-sm-6"><table class="table table-condensed"><tr><td>Monto inicial</td><td class="text-right">' + money(d.monto_apertura) + '</td></tr><tr><td>Ingresos</td><td class="text-right">' + money(d.total_ingresos) + '</td></tr><tr><td>Egresos</td><td class="text-right">' + money(d.total_egresos) + '</td></tr><tr><th>Saldo sistema</th><th class="text-right">' + money(d.sistema) + '</th></tr>' +
			(d.monto_cierre_real !== null ? '<tr><td>Real contado</td><td class="text-right">' + money(d.monto_cierre_real) + '</td></tr><tr><th>Diferencia</th><th class="text-right" style="color:' + (Number(d.diferencia) < 0 ? '#dc2626' : '#16a34a') + '">' + money(d.diferencia) + '</th></tr>' : '') +
			'</table></div></div>';
		if (d.medios && d.medios.length) {
			html += '<h5 class="fw-700">Por medio de pago</h5><table class="table table-condensed table-bordered"><thead><tr><th>Medio</th><th class="text-right">Ingresos</th><th class="text-right">Egresos</th><th class="text-right">Neto</th></tr></thead><tbody>';
			d.medios.forEach(function(m){ html += '<tr><td>' + appEscapeHtml(m.medio_pago) + '</td><td class="text-right">' + money(m.ingresos) + '</td><td class="text-right">' + money(m.egresos) + '</td><td class="text-right">' + money(m.neto) + '</td></tr>'; });
			html += '</tbody></table>';
		}
		html += '<h5 class="fw-700">Movimientos (' + (d.movimientos ? d.movimientos.length : 0) + ')</h5><div class="table-responsive"><table class="table table-condensed table-bordered"><thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Medio</th><th>Ref.</th><th class="text-right">Monto</th></tr></thead><tbody>';
		(d.movimientos || []).forEach(function(m){
			html += '<tr><td>' + fechaBonita(m.fecha_hora) + '</td><td>' + (m.tipo === "INGRESO" ? '<span class="label bg-green">INGRESO</span>' : '<span class="label bg-red">EGRESO</span>') + '</td><td>' + appEscapeHtml(m.concepto) + '</td><td>' + appEscapeHtml(m.medio_pago || "") + '</td><td>' + appEscapeHtml(m.referencia || "") + '</td><td class="text-right">' + money(m.monto) + '</td></tr>';
		});
		html += '</tbody></table></div>';
		if (d.observacion) { html += '<p class="text-soft"><strong>Obs.:</strong> ' + appEscapeHtml(d.observacion) + '</p>'; }
		$("#detCajaBody").html(html);
		$("#modalDetalleCaja").modal("show");
	});
}

function imprimirDetalleCaja(){
	var contenido = $("#detCajaBody").html();
	var titulo = "Arqueo de caja " + $("#detCajaTitulo").text();
	var w = window.open("", "_blank", "width=800,height=900");
	w.document.write('<html><head><title>' + titulo + '</title><link rel="stylesheet" href="../public/css/bootstrap.min.css"><style>body{font-family:Segoe UI,Arial,sans-serif;padding:24px;font-size:13px}.label{padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}.bg-green{background:#dcfce7;color:#14532d;border:1px solid #86efac}.bg-red{background:#fee2e2;color:#7f1d1d;border:1px solid #fca5a5}.bg-aqua{background:#e0f2fe;color:#0c4a6e;border:1px solid #7dd3fc}h2{margin-top:0}</style></head><body><h2>' + titulo + '</h2>' + contenido + '<script>window.onload=function(){window.print();}<\/script></body></html>');
	w.document.close();
}

function init(){
	cargarEstadoCaja();
	listarMovimientosCaja();
	listarHistorialCaja();

	$("#chkTodasCajas").on("change", function(){ if (dtHistCaja) dtHistCaja.ajax.reload(); });
	$("#btnImprimirCaja").on("click", imprimirDetalleCaja);
	$("#modalAbrirCaja").on("shown.bs.modal", function(){ $("#monto_apertura").focus(); });
	$("#modalMovimiento").on("shown.bs.modal", function(){ $("#mov_concepto").focus(); });
	$("#modalCerrarCaja").on("shown.bs.modal", function(){ $("#monto_cierre_real").focus(); });

	$("#formAbrirCaja").on("submit", function(e){
		e.preventDefault();
		appSetLoading("#btnConfirmarAbrir", true);
		$.post("../ajax/caja.php?op=abrir", $(this).serialize(), function(resp){
			appSetLoading("#btnConfirmarAbrir", false);
			var ok = (resp || "").toLowerCase().indexOf("correctamente") !== -1;
			appNotify(ok ? "success" : "warning", resp);
			if (ok) { $("#modalAbrirCaja").modal("hide"); $("#formAbrirCaja")[0].reset(); refrescarTodoCaja(); }
		}).fail(function(){ appSetLoading("#btnConfirmarAbrir", false); });
	});

	$("#formMovimientoCaja").on("submit", function(e){
		e.preventDefault();
		appSetLoading("#btnConfirmarMov", true);
		$.post("../ajax/caja.php?op=movimiento", $(this).serialize(), function(resp){
			appSetLoading("#btnConfirmarMov", false);
			var ok = (resp || "").toLowerCase().indexOf("registrado") !== -1;
			appNotify(ok ? "success" : "warning", resp);
			if (ok) { $("#modalMovimiento").modal("hide"); $("#formMovimientoCaja")[0].reset(); refrescarTodoCaja(); }
		}).fail(function(){ appSetLoading("#btnConfirmarMov", false); });
	});

	$("#formCerrarCaja").on("submit", function(e){
		e.preventDefault();
		var $form = $(this);
		appConfirm("Al cerrar la caja no podrás registrar más movimientos en ella. ¿Confirmas el cierre?", function(){
			appSetLoading("#btnConfirmarCerrar", true);
			$.post("../ajax/caja.php?op=cerrar", $form.serialize(), function(resp){
				appSetLoading("#btnConfirmarCerrar", false);
				var ok = (resp || "").toLowerCase().indexOf("correctamente") !== -1;
				appNotify(ok ? "success" : "warning", resp);
				if (ok) { $("#modalCerrarCaja").modal("hide"); $form[0].reset(); refrescarTodoCaja(); }
			}).fail(function(){ appSetLoading("#btnConfirmarCerrar", false); });
		}, { titulo: "Cerrar caja", ok: "Sí, cerrar", tipo: "danger" });
	});
}

init();
