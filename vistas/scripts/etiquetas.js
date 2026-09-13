/* Impresión masiva de etiquetas con código de barras */
var articulos = [];

function money(v){ return (window.appSimbolo || "S/") + " " + Number(v || 0).toFixed(2); }

function cargar(){
	$.get("../ajax/articulo.php?op=catalogoEtiquetas", function(resp){
		articulos = appParseJson(resp, []) || [];
		var html = "";
		articulos.forEach(function(a, i){
			var pill = a.stock <= 0 ? "stock-empty" : (a.stock <= a.stock_minimo ? "stock-low" : "stock-ok");
			html += '<tr data-i="' + i + '">' +
				'<td><input type="checkbox" class="chk" data-i="' + i + '"' + (a.codigo ? '' : ' disabled title="Sin código"') + '></td>' +
				'<td><strong>' + appEscapeHtml(a.nombre) + '</strong><br><small class="text-soft">' + appEscapeHtml(a.categoria || "") + '</small></td>' +
				'<td>' + (a.codigo ? '<code>' + appEscapeHtml(a.codigo) + '</code>' : '<span class="text-danger">sin código</span>') + '</td>' +
				'<td class="money">' + money(a.precio_venta) + '</td>' +
				'<td><span class="stock-pill ' + pill + '">' + a.stock + '</span></td>' +
				'<td><input type="number" class="form-control input-sm cant" data-i="' + i + '" value="' + ($("#opCantidad").val() || 1) + '" min="1" max="500"></td>' +
				'</tr>';
		});
		$("#tblarticulos tbody").html(html);
		$("#tblarticulos").DataTable({ bDestroy: true, dom: 'frtip', iDisplayLength: 15, order: [[1, "asc"]], columnDefs: [{ orderable: false, targets: [0, 5] }] });
	});
}

function seleccionados(){
	var lista = [];
	$(".chk:checked").each(function(){
		var i = parseInt($(this).data("i"), 10);
		var cant = parseInt($(".cant[data-i='" + i + "']").val() || 1, 10);
		if (cant < 1) { cant = 1; }
		if (cant > 500) { cant = 500; }
		lista.push({ art: articulos[i], cant: cant });
	});
	return lista;
}

function dimensiones(){
	var t = $("#opTamano").val();
	if (t === "peq") { return { w: 50, h: 25, bar: 30, font: 9, bw: 1.2 }; }
	if (t === "gra") { return { w: 80, h: 45, bar: 56, font: 13, bw: 2 }; }
	return { w: 60, h: 35, bar: 40, font: 11, bw: 1.6 };
}

function etiquetaHtml(a, d){
	var partes = [];
	if ($("#opEmpresa").is(":checked")) { partes.push('<div class="et-emp">' + appEscapeHtml(window.appEmpresaNombre || "") + '</div>'); }
	if ($("#opNombre").is(":checked")) { partes.push('<div class="et-nom">' + appEscapeHtml(a.nombre) + '</div>'); }
	partes.push('<svg class="et-bar" data-code="' + appEscapeHtml(a.codigo) + '"></svg>');
	if ($("#opPrecio").is(":checked")) { partes.push('<div class="et-pre">' + money(a.precio_venta) + '</div>'); }
	return '<div class="et" style="width:' + d.w + 'mm;height:' + d.h + 'mm">' + partes.join("") + '</div>';
}

function renderBarcodes(root, d){
	$(root).find("svg.et-bar").each(function(){
		try { JsBarcode(this, $(this).data("code"), { format: "CODE128", width: d.bw, height: d.bar, displayValue: true, fontSize: d.font, margin: 2 }); } catch (e) { $(this).replaceWith('<div class="text-danger" style="font-size:10px">código inválido</div>'); }
	});
}

function estiloEtiquetas(d){
	return '.hoja{display:flex;flex-wrap:wrap;gap:2mm;padding:4mm}.et{border:1px dashed #cbd5e1;border-radius:2mm;display:flex;flex-direction:column;align-items:center;justify-content:center;overflow:hidden;padding:1mm;box-sizing:border-box;page-break-inside:avoid;font-family:Segoe UI,Arial,sans-serif;text-align:center}' +
		'.et-emp{font-size:' + (d.font - 2) + 'px;color:#475569;letter-spacing:.5px;text-transform:uppercase}.et-nom{font-size:' + d.font + 'px;font-weight:700;line-height:1.1;max-height:2.3em;overflow:hidden}.et-pre{font-size:' + (d.font + 3) + 'px;font-weight:800}.et-bar{max-width:100%}' +
		'@media print{.et{border-color:transparent}@page{margin:5mm}}';
}

function actualizarPreview(){
	var sel = seleccionados();
	var d = dimensiones();
	if (!sel.length) { $("#preview").html('<span class="text-soft">Marca un artículo para ver la vista previa.</span>'); return; }
	$("#preview").html('<style>' + estiloEtiquetas(d) + '</style><div class="hoja" style="justify-content:center;padding:0">' + etiquetaHtml(sel[0].art, d) + '</div>');
	renderBarcodes("#preview", d);
}

function generar(){
	var sel = seleccionados();
	if (!sel.length) { appNotify("warning", "Marca al menos un artículo."); return; }
	var d = dimensiones();
	var total = 0, html = "";
	sel.forEach(function(s){ for (var k = 0; k < s.cant; k++) { html += etiquetaHtml(s.art, d); total++; } });
	if (total > 2000) { appNotify("warning", "Demasiadas etiquetas (" + total + "). Reduce la cantidad."); return; }
	var w = window.open("", "_blank");
	if (!w) { appNotify("error", "El navegador bloqueó la ventana emergente. Permite pop-ups para este sitio."); return; }
	w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Etiquetas (' + total + ')</title><style>body{margin:0}' + estiloEtiquetas(d) + '</style></head><body><div class="hoja">' + html + '</div><script src="../public/js/JsBarcode.all.min.js"><\/script><script>document.querySelectorAll("svg.et-bar").forEach(function(s){try{JsBarcode(s,s.getAttribute("data-code"),{format:"CODE128",width:' + d.bw + ',height:' + d.bar + ',displayValue:true,fontSize:' + d.font + ',margin:2});}catch(e){}});setTimeout(function(){window.print();},400);<\/script></body></html>');
	w.document.close();
	appNotify("success", total + " etiqueta(s) generadas.");
}

function init(){
	cargar();
	$("#btnGenerar").on("click", generar);
	$("#btnTodos").on("click", function(){ $(".chk:not(:disabled)").prop("checked", true); actualizarPreview(); });
	$("#btnNinguno").on("click", function(){ $(".chk").prop("checked", false); actualizarPreview(); });
	$("#btnBajoMinimo").on("click", function(){
		$(".chk").prop("checked", false);
		$(".chk:not(:disabled)").each(function(){ var a = articulos[parseInt($(this).data("i"), 10)]; if (a && a.stock <= a.stock_minimo) { $(this).prop("checked", true); } });
		actualizarPreview();
	});
	$("#tblarticulos").on("change", ".chk", actualizarPreview);
	$("#opTamano, #opNombre, #opPrecio, #opEmpresa").on("change", actualizarPreview);
	$("#opCantidad").on("change", function(){ $(".cant").val($(this).val()); });
}

init();
