/* Cuentas por cobrar y pagar */
var dtCobrar, dtPagar;
var tabsCargadas = { cobrar: false, pagar: false };

function money(v){ return window.appMoney ? window.appMoney(v, 2) : ((window.appCurrencySymbol || "S/") + " " + Number(v || 0).toFixed(2)); }

function cargarResumen(){
	$.get("../ajax/cuentas.php?op=resumen", function(resp){
		var r = appParseJson(resp, null);
		if (!r) { return; }
		$("#resCxcPend").text(r.cxc_pendiente_txt || money(r.cxc_pendiente));
		$("#resCxcVenc").text(r.cxc_vencido_txt || money(r.cxc_vencido));
		$("#resCxcVencTxt").text("Por cobrar vencido · " + (r.cxc_vencidas_cantidad || 0) + " cuenta(s)");
		$("#resCxpPend").text(r.cxp_pendiente_txt || money(r.cxp_pendiente));
		$("#resCxpVenc").text(r.cxp_vencido_txt || money(r.cxp_vencido));
		$("#resCxpVencTxt").text("Por pagar vencido · " + (r.cxp_vencidas_cantidad || 0) + " cuenta(s)");
	});
}

function opcionesTabla(titulo, op, filtroSel){
	return {
		aProcessing: true, aServerSide: true, bDestroy: true, autoWidth: false, iDisplayLength: 10,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons(titulo, true),
		order: [[2, "asc"]],
		columnDefs: [{ orderable: false, targets: [0] }, { visible: false, targets: [10] }],
		ajax: {
			url: "../ajax/cuentas.php?op=" + op, type: "get", dataType: "json",
			data: function(d){ d.estado = $(filtroSel).val() || "TODOS"; },
			error: function(e){ console.log(e.responseText); }
		}
	};
}

function listarCobrar(){
	if (!$("#tblcobrar").length) { return; }
	dtCobrar = $("#tblcobrar").DataTable(opcionesTabla('Cuentas por Cobrar', 'listarCobrar', '#filtro_cobrar_estado'));
	tabsCargadas.cobrar = true;
}

function listarPagar(){
	if (!$("#tblpagar").length) { return; }
	dtPagar = $("#tblpagar").DataTable(opcionesTabla('Cuentas por Pagar', 'listarPagar', '#filtro_pagar_estado'));
	tabsCargadas.pagar = true;
}

function recargarTablas(){
	if (dtCobrar) dtCobrar.ajax.reload(null, false);
	if (dtPagar) dtPagar.ajax.reload(null, false);
	cargarResumen();
	if (window.cargarAlertasBell) { window.cargarAlertasBell(); }
}

// ---- Abonos ----
function abrirAbono(tipo, idcuenta, saldo){
	$("#ab_tipo").val(tipo);
	$("#ab_idcuenta").val(idcuenta);
	$("#ab_saldo").text(money(saldo)).data("saldo", parseFloat(saldo || 0));
	$("#ab_monto").val("").attr("max", parseFloat(saldo || 0).toFixed(2));
	$("#ab_medio").val("EFECTIVO");
	$("#ab_obs").val("");
	$("#ab_titulo").html('<i class="fa fa-money"></i> ' + (tipo === "cobrar" ? "Registrar cobro" : "Registrar pago"));
	$("#modalAbono").modal("show");
}
function abrirPagoCobrar(idcuenta, saldo){ abrirAbono("cobrar", idcuenta, saldo); }
function abrirPagoPagar(idcuenta, saldo){ abrirAbono("pagar", idcuenta, saldo); }

// ---- Historial ----
function verPagos(tipo, idcuenta){
	$.get("../ajax/cuentas.php?op=" + (tipo === "cobrar" ? "pagosCobrar" : "pagosPagar"), { idcuenta: idcuenta }, function(resp){
		var lista = appParseJson(resp, []);
		var html;
		if (!lista || !lista.length) {
			html = '<div class="empty-state"><i class="fa fa-inbox"></i><strong>Sin pagos registrados</strong>Esta cuenta aún no tiene abonos.</div>';
		} else {
			var total = 0;
			html = '<table class="table table-condensed table-bordered"><thead><tr><th>Fecha</th><th>Medio</th><th>Usuario</th><th>Obs.</th><th class="text-right">Monto</th></tr></thead><tbody>';
			lista.forEach(function(p){
				total += parseFloat(p.monto || 0);
				html += '<tr><td>' + appEscapeHtml(p.fecha) + '</td><td>' + appEscapeHtml(p.medio_pago || "") + '</td><td>' + appEscapeHtml(p.usuario || "") + '</td><td>' + appEscapeHtml(p.observacion || "") + '</td><td class="text-right">' + (p.monto_txt || money(p.monto)) + '</td></tr>';
			});
			html += '</tbody><tfoot><tr><th colspan="4" class="text-right">Total abonado</th><th class="text-right">' + money(total) + '</th></tr></tfoot></table>';
		}
		$("#pagosBody").html(html);
		$("#modalPagos").modal("show");
	});
}
function verPagosCobrar(id){ verPagos("cobrar", id); }
function verPagosPagar(id){ verPagos("pagar", id); }

// ---- Anular ----
function anularCuenta(tipo, idcuenta){
	appConfirm("La cuenta quedará anulada (solo es posible si no tiene pagos). ¿Continuar?", function(){
		$.post("../ajax/cuentas.php?op=" + (tipo === "cobrar" ? "anularCobrar" : "anularPagar"), { idcuenta: idcuenta }, function(resp){
			appNotifyFromResponse(resp);
			recargarTablas();
		});
	}, { titulo: "Anular cuenta", ok: "Sí, anular", tipo: "danger" });
}
function anularCobrar(id){ anularCuenta("cobrar", id); }
function anularPagar(id){ anularCuenta("pagar", id); }

// ---- Nueva cuenta ----
function prepararNuevaCuenta(tipo){
	$("#nc_tipo").val(tipo);
	$("#nc_titulo").html('<i class="fa fa-plus"></i> ' + (tipo === "cobrar" ? "Registrar cuenta por cobrar" : "Registrar cuenta por pagar"));
	$("#nc_labelPersona").html((tipo === "cobrar" ? "Cliente" : "Proveedor") + ' <span class="req">*</span>');
	$("#nc_emision").val(appFechaHoy());
	$("#nc_venc").val(appFechaSumarDias(null, 30));
	$("#nc_doc, #nc_monto, #nc_obs").val("");
	$.post("../ajax/cuentas.php?op=" + (tipo === "cobrar" ? "selectCliente" : "selectProveedor"), function(r){
		$("#nc_idpersona").html(r).selectpicker("refresh");
	});
}

function init(){
	cargarResumen();
	if ($("#cxctab").hasClass("active")) { listarCobrar(); } else if ($("#cxptab").hasClass("active")) { listarPagar(); }

	$('a[data-toggle="tab"]').on("shown.bs.tab", function(e){
		var target = $(e.target).attr("href");
		if (target === "#cxptab" && !tabsCargadas.pagar) { listarPagar(); }
		if (target === "#cxctab" && !tabsCargadas.cobrar) { listarCobrar(); }
		if (dtCobrar) dtCobrar.columns.adjust();
		if (dtPagar) dtPagar.columns.adjust();
	});
	if (window.location.hash === "#cxp") { $('a[href="#cxptab"]').tab("show"); }

	$("#filtro_cobrar_estado").on("change", function(){ if (dtCobrar) dtCobrar.ajax.reload(); });
	$("#filtro_pagar_estado").on("change", function(){ if (dtPagar) dtPagar.ajax.reload(); });

	$("#modalNuevaCuenta").on("show.bs.modal", function(e){
		var tipo = $(e.relatedTarget).data("tipo") || "cobrar";
		prepararNuevaCuenta(tipo);
	});

	$("#formNuevaCuenta").on("submit", function(e){
		e.preventDefault();
		var tipo = $("#nc_tipo").val();
		var data = {
			fecha_emision: $("#nc_emision").val(), fecha_vencimiento: $("#nc_venc").val(),
			documento_ref: $("#nc_doc").val(), monto_total: $("#nc_monto").val(), observacion: $("#nc_obs").val()
		};
		if (tipo === "cobrar") { data.idcliente = $("#nc_idpersona").val(); } else { data.idproveedor = $("#nc_idpersona").val(); }
		if (!data.idcliente && !data.idproveedor) { appNotify("warning", "Selecciona " + (tipo === "cobrar" ? "un cliente" : "un proveedor") + "."); return; }
		if (data.fecha_vencimiento < data.fecha_emision) { appNotify("warning", "El vencimiento no puede ser anterior a la emisión."); return; }
		appSetLoading("#btnGuardarCuenta", true);
		$.post("../ajax/cuentas.php?op=" + (tipo === "cobrar" ? "guardarCobrar" : "guardarPagar"), data, function(resp){
			appSetLoading("#btnGuardarCuenta", false);
			var ok = (resp || "").toLowerCase().indexOf("registrada") !== -1;
			appNotify(ok ? "success" : "error", resp);
			if (ok) { $("#modalNuevaCuenta").modal("hide"); recargarTablas(); }
		}).fail(function(){ appSetLoading("#btnGuardarCuenta", false); });
	});

	$("#btnAbonoTotal").on("click", function(){ $("#ab_monto").val(($("#ab_saldo").data("saldo") || 0).toFixed(2)); });
	$("#modalAbono").on("shown.bs.modal", function(){ $("#ab_monto").focus(); });

	$("#formAbono").on("submit", function(e){
		e.preventDefault();
		var tipo = $("#ab_tipo").val();
		var monto = parseFloat($("#ab_monto").val() || 0);
		var saldo = parseFloat($("#ab_saldo").data("saldo") || 0);
		if (monto <= 0) { appNotify("warning", "Ingresa un monto mayor a cero."); return; }
		if (monto > saldo + 0.005) { appNotify("warning", "El monto supera el saldo pendiente."); return; }
		appSetLoading("#btnConfirmarAbono", true);
		$.post("../ajax/cuentas.php?op=" + (tipo === "cobrar" ? "abonarCobrar" : "abonarPagar"), {
			idcuenta: $("#ab_idcuenta").val(), monto: monto.toFixed(2), medio_pago: $("#ab_medio").val(), observacion: $("#ab_obs").val()
		}, function(resp){
			appSetLoading("#btnConfirmarAbono", false);
			var ok = (resp || "").toLowerCase().indexOf("correctamente") !== -1;
			appNotify(ok ? "success" : "error", resp);
			if (ok) { $("#modalAbono").modal("hide"); recargarTablas(); }
		}).fail(function(){ appSetLoading("#btnConfirmarAbono", false); });
	});
}

init();
