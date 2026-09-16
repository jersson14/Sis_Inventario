/* Ajustes de inventario */
var tabla;
var articulosCargados = false;

function money(v){ return window.appMoney ? window.appMoney(v, 2) : Number(v || 0).toFixed(2); }

function cargarArticulos(){
	$.post("../ajax/inventario.php?op=selectArticulo", function(r){
		$("#aj_articulo").html('<option value="">— Selecciona —</option>' + r).selectpicker("refresh");
		articulosCargados = true;
	});
}

function listar(){
	tabla = $("#tblajustes").DataTable({
		aProcessing: true, aServerSide: true, bDestroy: true, iDisplayLength: 10,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Ajustes de inventario', false),
		order: [[0, "desc"]],
		ajax: {
			url: "../ajax/inventario.php?op=listar", type: "get", dataType: "json",
			data: function(d){ d.fecha_inicio = $("#f_inicio").val(); d.fecha_fin = $("#f_fin").val(); d.tipo = $("#f_tipo").val(); },
			error: function(e){ console.log(e.responseText); }
		}
	});
}

function cargarResumen(){
	$.get("../ajax/inventario.php?op=resumen", { fecha_inicio: $("#f_inicio").val(), fecha_fin: $("#f_fin").val() }, function(resp){
		var r = appParseJson(resp, null);
		if (!r) { return; }
		$("#resAjustes").text(r.ajustes || 0);
		$("#resEntradas").text(Number(r.entradas || 0).toLocaleString("es-PE"));
		$("#resEntradasTxt").text("Unidades ingresadas · " + money(r.valor_entradas));
		$("#resSalidas").text(Number(r.salidas || 0).toLocaleString("es-PE"));
		$("#resSalidasTxt").text("Unidades retiradas · " + money(r.valor_salidas));
	});
}

// En una salida, lista los lotes del articulo para dar de baja uno concreto
function cargarLotesSalida(){
	var $sel = $("#aj_idlote");
	if (!$sel.length || $("#aj_tipo").val() !== "SALIDA") { return; }
	var id = $("#aj_articulo").val();
	$sel.html('<option value="0">Automático: primero lo vencido y lo que vence antes</option>');
	if (!id) { return; }
	$.get("../ajax/inventario.php?op=lotesArticulo", { idarticulo: id }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.lotes) { return; }
		r.lotes.forEach(function(l){
			var vence = l.fecha_vencimiento ? l.fecha_vencimiento.split("-").reverse().join("/") : "sin fecha";
			var estado = l.dias === null ? "" : (l.dias < 0 ? " · VENCIDO" : " · " + l.dias + " día(s)");
			$sel.append($("<option>").val(l.idlote).attr("data-stock", l.stock)
				.text((l.codigo_lote || "Lote #" + l.idlote) + " — vence " + vence + estado + " — " + window.appCantidad(l.stock)));
		});
	});
}

function abrirAjuste(tipo){
	$("#aj_tipo").val(tipo);
	$("#aj_titulo").html(tipo === "ENTRADA" ? '<i class="fa fa-arrow-down" style="color:#16a34a"></i> Entrada de inventario' : '<i class="fa fa-arrow-up" style="color:#dc2626"></i> Salida de inventario');
	$("#btnGuardarAjuste").removeClass("btn-success btn-danger").addClass(tipo === "ENTRADA" ? "btn-success" : "btn-danger");
	$("#aj_cantidad, #aj_costo, #aj_obs, #aj_lote_codigo, #aj_lote_vence").val("");
	$("#aj_idlote").html('<option value="0">Automático: primero lo vencido y lo que vence antes</option>');
	$(".lote-entrada").toggle(tipo === "ENTRADA");
	$(".lote-salida").toggle(tipo === "SALIDA");
	$("#aj_articulo").val("").selectpicker("refresh");
	$("#aj_info").text("Selecciona un artículo para ver su stock actual.");
	$("#aj_preview").hide();
	// Motivos sugeridos por tipo
	$("#aj_motivo").val(tipo === "ENTRADA" ? "CONTEO" : "MERMA");
	$("#modalAjuste").modal("show");
}

function actualizarPreview(){
	var $opt = $("#aj_articulo option:selected");
	var stock = parseFloat($opt.data("stock") || 0);
	var unidad = $opt.data("unidad") || "und";
	var cantidad = window.appNormalizarCantidad($("#aj_cantidad").val(), String($opt.data("fraccion")) === "1");
	if (!$opt.val()) { $("#aj_preview").hide(); return; }
	var tipo = $("#aj_tipo").val();
	var nuevo = Math.round((tipo === "ENTRADA" ? stock + cantidad : stock - cantidad) * 1000) / 1000;
	var html = "Stock actual: <strong>" + window.appCantidad(stock) + " " + appEscapeHtml(unidad) + "</strong>";
	if (cantidad > 0) {
		html += " → nuevo stock: <strong style='color:" + (nuevo < 0 ? "#dc2626" : "#0f766e") + "'>" + window.appCantidad(nuevo) + " " + appEscapeHtml(unidad) + "</strong>";
		if (nuevo < 0) { html += " <span class='text-danger'>(no puedes retirar más de lo disponible)</span>"; }
	}
	$("#aj_preview").html(html).show();
}

function init(){
	cargarArticulos();
	listar();
	cargarResumen();
	$("#btnNuevaEntrada").on("click", function(){ abrirAjuste("ENTRADA"); });
	$("#btnNuevaSalida").on("click", function(){ abrirAjuste("SALIDA"); });
	$("#btnFiltrar").on("click", function(){ tabla.ajax.reload(); cargarResumen(); });
	$("#aj_articulo").on("changed.bs.select change", function(){
		var $opt = $("#aj_articulo option:selected");
		if ($opt.val()) {
			$("#aj_info").text("Stock actual: " + window.appCantidad($opt.data("stock")) + " " + $opt.data("unidad") + " · costo ref. " + money($opt.data("costo")));
			$("#aj_cantidad").attr({ step: String($opt.data("fraccion")) === "1" ? "0.001" : "1", min: String($opt.data("fraccion")) === "1" ? "0.001" : "1" });
			if (!$("#aj_costo").val()) { $("#aj_costo").attr("placeholder", money($opt.data("costo"))); }
		}
		cargarLotesSalida();
		actualizarPreview();
	});
	$("#aj_cantidad").on("input", actualizarPreview);
	$("#aj_idlote").on("change", function(){
		var st = $(this).find("option:selected").data("stock");
		if (st && !$("#aj_cantidad").val()) { $("#aj_cantidad").val(st); actualizarPreview(); }
	});
	$("#modalAjuste").on("shown.bs.modal", function(){ if (!$("#aj_articulo").val()) { $("#aj_articulo").selectpicker("toggle"); } });

	$("#formAjuste").on("submit", function(e){
		e.preventDefault();
		if (!$("#aj_articulo").val()) { appNotify("warning", "Selecciona un artículo."); return; }
		if ((parseFloat($("#aj_cantidad").val()) || 0) <= 0) { appNotify("warning", "La cantidad debe ser mayor que cero."); return; }
		appSetLoading("#btnGuardarAjuste", true);
		$.post("../ajax/inventario.php?op=registrar", $(this).serialize(), function(resp){
			appSetLoading("#btnGuardarAjuste", false);
			var r = appParseJson(resp, { ok: false, message: resp });
			appNotify(r.ok ? "success" : "error", r.message || "", r.ok ? 5000 : 6000);
			if (r.ok) {
				$("#modalAjuste").modal("hide");
				tabla.ajax.reload(null, false);
				cargarResumen();
				cargarArticulos();
				if (window.cargarAlertasBell) { window.cargarAlertasBell(); }
			}
		}).fail(function(){ appSetLoading("#btnGuardarAjuste", false); });
	});
}

init();
