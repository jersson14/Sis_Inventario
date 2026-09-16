/* Vencimientos y lotes */
var tabla;

function money(v){ return window.appMoney ? window.appMoney(v, 2) : Number(v || 0).toFixed(2); }

function listar(){
	tabla = $("#tbllotes").DataTable({
		aProcessing: true, aServerSide: true, bDestroy: true, iDisplayLength: 15,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Vencimientos y lotes', false),
		order: [[3, "asc"]],
		columnDefs: [{ orderable: false, targets: [0] }],
		ajax: {
			url: "../ajax/lote.php?op=listar", type: "get", dataType: "json",
			data: function(d){ d.estado = $("#f_estado").val(); d.dias = $("#f_dias").val(); },
			error: function(e){ console.log(e.responseText); }
		},
		language: { emptyTable: "No hay lotes en este estado." }
	});
}

function cargarResumen(){
	$.get("../ajax/lote.php?op=resumen", { dias: $("#f_dias").val() }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.ok) { return; }
		$("#resVencidos").text(r.vencidos);
		$("#resVencidosTxt").text("Lotes vencidos · " + r.vencidos_valor_fmt);
		$("#resPorVencer").text(r.por_vencer);
		$("#resPorVencerTxt").text("Por vencer en " + r.dias + " días · " + r.por_vencer_valor_fmt);
		$("#resLotes").text(r.lotes_con_stock);
	});
}

function recargar(){
	var dias = parseInt($("#f_dias").val(), 10);
	if (!dias || dias < 1) { $("#f_dias").val(30); }
	if (tabla) { tabla.ajax.reload(); }
	cargarResumen();
}

// Baja del stock que queda en el lote: registra un ajuste de salida y queda en el kardex
function darBaja(idlote, articulo, stock){
	appConfirm("Se dará de baja " + window.appCantidad(stock) + " de «" + articulo + "» (lote completo) con motivo Producto vencido. El movimiento queda en el kardex y en auditoría. ¿Continuar?", function(){
		$.post("../ajax/lote.php?op=darBaja", { idlote: idlote, motivo: "VENCIMIENTO" }, function(resp){
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "", r.ok ? 5000 : 7000);
			if (r.ok) {
				tabla.ajax.reload(null, false);
				cargarResumen();
				if (window.cargarAlertasBell) { window.cargarAlertasBell(); }
			}
		});
	}, { titulo: "Dar de baja lote", ok: "Sí, dar de baja", tipo: "danger" });
}

function init(){
	if (!$("#tbllotes").length) { return; }
	listar();
	cargarResumen();
	$("#btnFiltrar").on("click", recargar);
	$("#f_estado").on("change", recargar);
	$(".filtro-rapido").on("click", function(e){
		e.preventDefault();
		$("#f_estado").val($(this).data("estado"));
		recargar();
	});
	if (window.appQueryParam && window.appQueryParam("estado")) {
		$("#f_estado").val(window.appQueryParam("estado"));
		recargar();
	}
}

init();
