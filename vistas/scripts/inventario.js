/* Ajustes de inventario */
var tabla;
var articulosCargados = false;
// Lo que trae un codigo escaneado, para elegirlo cuando carguen tallas y lotes
var preVariante = 0;
var preLote = 0;

function money(v){ return window.appMoney ? window.appMoney(v, 2) : Number(v || 0).toFixed(2); }

function cargarArticulos(){
	$.post("../ajax/inventario.php?op=selectArticulo", function(r){
		var actual = $("#aj_articulo").val();
		$("#aj_articulo").html('<option value="">— Selecciona —</option>' + r).val(actual || "").selectpicker("refresh");
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

// Articulo con tallas/colores: el ajuste se hace sobre una combinacion
function cargarVariantesAjuste(){
	var $opt = $("#aj_articulo option:selected");
	var $grupo = $("#grupoVarianteAjuste"), $sel = $("#aj_idvariante");
	$sel.html("");
	if (!$opt.val() || !(parseInt($opt.data("variantes"), 10) > 0)) { $grupo.hide(); preVariante = 0; return; }
	$.get("../ajax/inventario.php?op=variantesArticulo", { idarticulo: $opt.val() }, function(resp){
		var r = appParseJson(resp, null);
		$sel.html('<option value="0">— Elige talla / color —</option>');
		((r && r.variantes) || []).forEach(function(v){
			$sel.append($("<option>").val(v.idvariante).attr("data-stock", v.stock).text(v.etiqueta + " · stock " + window.appCantidad(v.stock)));
		});
		if (preVariante) { $sel.val(String(preVariante)); preVariante = 0; }
		$grupo.show();
	});
}

// Lotes del articulo. Salida: de cual sale (o FEFO automatico).
// Entrada: stock sin lote, un lote nuevo (codigo/fecha) o sumar a uno existente.
function cargarLotes(){
	var $sel = $("#aj_idlote");
	if (!$sel.length) { return; }
	var entrada = $("#aj_tipo").val() === "ENTRADA";
	$("#aj_lote_label").text(entrada ? "Lote que ingresa" : "Retirar del lote");
	$sel.html(entrada
		? '<option value="0">Sin lote</option><option value="nuevo">Lote nuevo (código y vencimiento)…</option>'
		: '<option value="0">Automático: primero lo vencido y lo que vence antes</option>');
	mostrarLoteNuevo();
	var id = $("#aj_articulo").val();
	if (!id) { preLote = 0; return; }
	$.get("../ajax/inventario.php?op=lotesArticulo", { idarticulo: id }, function(resp){
		var r = appParseJson(resp, null);
		if (!r || !r.lotes) { return; }
		r.lotes.forEach(function(l){
			var vence = l.fecha_vencimiento ? l.fecha_vencimiento.split("-").reverse().join("/") : "sin fecha";
			var estado = l.dias === null ? "" : (l.dias < 0 ? " · VENCIDO" : " · " + l.dias + " día(s)");
			$sel.append($("<option>").val(l.idlote).attr("data-stock", l.stock)
				.text((entrada ? "Sumar a " : "") + (l.codigo_lote || "Lote #" + l.idlote) + " — vence " + vence + estado + " — " + window.appCantidad(l.stock)));
		});
		if (preLote && $sel.find("option[value='" + preLote + "']").length) { $sel.val(String(preLote)); mostrarLoteNuevo(); }
		preLote = 0;
	});
}

function mostrarLoteNuevo(){
	var nuevo = $("#aj_tipo").val() === "ENTRADA" && $("#aj_idlote").val() === "nuevo";
	$(".lote-entrada").toggle(nuevo);
	if (!nuevo) { $("#aj_lote_codigo, #aj_lote_vence").val(""); }
}

function abrirAjuste(tipo){
	$("#aj_tipo").val(tipo);
	$("#aj_titulo").html(tipo === "ENTRADA" ? '<i class="fa fa-arrow-down" style="color:#16a34a"></i> Entrada de inventario' : '<i class="fa fa-arrow-up" style="color:#dc2626"></i> Salida de inventario');
	$("#btnGuardarAjuste").removeClass("btn-success btn-danger").addClass(tipo === "ENTRADA" ? "btn-success" : "btn-danger");
	$("#aj_cantidad, #aj_costo, #aj_obs, #aj_lote_codigo, #aj_lote_vence, #aj_scan").val("");
	$("#aj_scan_info").removeClass("text-danger text-success").text("Con el lector: cada lectura del mismo producto suma a la cantidad.");
	$("#grupoVarianteAjuste").hide();
	$("#aj_idvariante").html("");
	preVariante = 0;
	preLote = 0;
	$("#aj_articulo").val("").selectpicker("refresh");
	cargarLotes();
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

// Lectura del lector: elige articulo, talla y lote; una caja suma su equivalencia
function escanearAjuste(codigo){
	var $info = $("#aj_scan_info");
	$.post("../ajax/inventario.php?op=buscarCodigo", { codigo: codigo }, function(resp){
		var r = appParseJson(resp, { ok: false, message: resp });
		$("#aj_scan").val("").focus();
		if (!r.ok) {
			window.appSonido("error");
			$info.removeClass("text-success").addClass("text-danger").text(r.message || "Código no encontrado.");
			return;
		}
		var $opt = $("#aj_articulo option[value='" + r.idarticulo + "']");
		if (!$opt.length) {
			window.appSonido("error");
			$info.removeClass("text-success").addClass("text-danger").text("El artículo está desactivado.");
			return;
		}
		window.appSonido("ok");
		var factor = parseFloat(r.factor) || 1;
		if ($("#aj_articulo").val() === String(r.idarticulo)) {
			// Mismo articulo: otra talla u otro lote reinicia la cantidad; el mismo la suma
			var cambia = (r.idvariante && $("#aj_idvariante").val() !== String(r.idvariante))
				|| (r.idlote && $("#aj_idlote").val() !== String(r.idlote));
			if (r.idvariante) { $("#aj_idvariante").val(String(r.idvariante)); }
			if (r.idlote && $("#aj_idlote option[value='" + r.idlote + "']").length) { $("#aj_idlote").val(String(r.idlote)); mostrarLoteNuevo(); }
			var actual = cambia ? 0 : (parseFloat($("#aj_cantidad").val()) || 0);
			$("#aj_cantidad").val(Math.round((actual + factor) * 1000) / 1000);
			actualizarPreview();
		} else {
			preVariante = parseInt(r.idvariante, 10) || 0;
			preLote = parseInt(r.idlote, 10) || 0;
			$("#aj_cantidad").val(factor);
			$("#aj_articulo").val(String(r.idarticulo)).selectpicker("refresh").trigger("change");
		}
		$info.removeClass("text-danger").addClass("text-success")
			.text($opt.text() + (r.presentacion ? " · " + r.presentacion + " = " + window.appCantidad(factor) + " " + ($opt.data("unidad") || "und") : "") + " · cantidad " + window.appCantidad($("#aj_cantidad").val()));
	});
}

function init(){
	cargarArticulos();
	listar();
	cargarResumen();
	$("#btnNuevaEntrada").on("click", function(){ abrirAjuste("ENTRADA"); });
	$("#btnNuevaSalida").on("click", function(){ abrirAjuste("SALIDA"); });
	$("#btnFiltrar").on("click", function(){ tabla.ajax.reload(); cargarResumen(); });
	// bootstrap-select dispara "changed.bs.select" y "change": se procesa una vez por valor
	var ultimoArticulo = null;
	$("#aj_articulo").on("changed.bs.select change", function(){
		var $opt = $("#aj_articulo option:selected");
		if ($opt.val() === ultimoArticulo) { return; }
		ultimoArticulo = $opt.val();
		if ($opt.val()) {
			$("#aj_info").text("Stock actual: " + window.appCantidad($opt.data("stock")) + " " + $opt.data("unidad") + " · costo ref. " + money($opt.data("costo")));
			$("#aj_cantidad").attr({ step: String($opt.data("fraccion")) === "1" ? "0.001" : "1", min: String($opt.data("fraccion")) === "1" ? "0.001" : "1" });
			if (!$("#aj_costo").val()) { $("#aj_costo").attr("placeholder", money($opt.data("costo"))); }
		}
		cargarLotes();
		cargarVariantesAjuste();
		actualizarPreview();
	});
	$("#modalAjuste").on("show.bs.modal", function(){ ultimoArticulo = null; });
	$("#aj_cantidad").on("input", actualizarPreview);
	$("#aj_idlote").on("change", function(){
		mostrarLoteNuevo();
		var st = $(this).find("option:selected").data("stock");
		if (st && $("#aj_tipo").val() === "SALIDA" && !$("#aj_cantidad").val()) { $("#aj_cantidad").val(st); actualizarPreview(); }
	});
	window.appLectorCodigo("#aj_scan", escanearAjuste);
	$("#modalAjuste").on("shown.bs.modal", function(){ $("#aj_scan").focus(); });

	$("#formAjuste").on("submit", function(e){
		e.preventDefault();
		if (!$("#aj_articulo").val()) { appNotify("warning", "Selecciona un artículo."); return; }
		if ($("#grupoVarianteAjuste").is(":visible") && !(parseInt($("#aj_idvariante").val(), 10) > 0)) { appNotify("warning", "Elige la talla y el color."); return; }
		if ((parseFloat($("#aj_cantidad").val()) || 0) <= 0) { appNotify("warning", "La cantidad debe ser mayor que cero."); return; }
		if ($("#aj_idlote").val() === "nuevo" && !$("#aj_lote_codigo").val() && !$("#aj_lote_vence").val()) { appNotify("warning", "Escribe el código o la fecha de vencimiento del lote nuevo."); return; }
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
