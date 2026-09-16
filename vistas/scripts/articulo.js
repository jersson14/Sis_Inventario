var tabla;

function init(){
	mostrarform(false);
	listar();

	$("#formulario").on("submit", function(e){ guardaryeditar(e); });
	$("#precio_compra, #precio_venta").on("input", calcularMargen);
	$("#imagen").on("change", previsualizarImagen);
	$("#codigo").on("change", function(){ if ($.trim($(this).val())) { generarbarcode(true); } });

	$.post("../ajax/articulo.php?op=selectCategoria", function(r){
		$("#idcategoria").html(r).selectpicker('refresh');
	});
	$.post("../ajax/articulo.php?op=selectUnidad", function(r){
		$("#idunidad").html(r).selectpicker('refresh');
		aplicarUnidadBase();
	});
	$("#idunidad").on("changed.bs.select change", aplicarUnidadBase);
	$("#imagenmuestra").hide();

	if (window.appQueryParam && window.appQueryParam("nuevo") === "1") {
		mostrarform(true);
	}
}

function limpiar(){
	$("#idarticulo, #codigo, #nombre, #descripcion, #imagenactual").val("");
	$("#stock").val("0");
	$("#stock_minimo").val("1");
	$("#precio_compra, #precio_venta").val("0.00");
	$("#idunidad").val("").selectpicker('refresh');
	$("#imagenmuestra").attr("src", "").hide();
	$("#imagen").val("");
	$("#print").hide();
	$("#formTitulo").text("Nuevo artículo");
	$("#stock").prop("readonly", false);
	$("#stockAyuda").hide();
	$("#tblPresentaciones tbody, #tblEscalas tbody, #tblLotesArticulo tbody").empty();
	$("#tituloLotes, #bloqueLotes").hide();
	aplicarUnidadBase();
	calcularMargen();
}

// ---------- Unidad base: decimales y textos ----------

function unidadSeleccionada(){
	var $opt = $("#idunidad option:selected");
	return {
		fraccion: window.appNegocioTiene && window.appNegocioTiene("fracciones") && String($opt.data("fraccion")) === "1",
		abrev: $opt.data("abrev") || "und"
	};
}

// Con fraccion los campos de cantidad aceptan 3 decimales; si no, enteros.
function aplicarUnidadBase(){
	var u = unidadSeleccionada();
	$(".unidad-base-txt").text(u.abrev);
	$("#formulario .input-cantidad").attr("step", u.fraccion ? "0.001" : "1");
}

// ---------- Lotes (solo lectura) ----------

function mostrarLotes(data){
	var lotes = data.lotes || [];
	if (!$("#tblLotesArticulo").length) { return; }
	var enLotes = 0, html = "";
	lotes.forEach(function(l){
		enLotes += Number(l.stock);
		var dias = l.dias === null ? null : Number(l.dias);
		var estado = dias === null ? '<span class="label bg-gray">Sin fecha</span>'
			: (dias < 0 ? '<span class="label bg-red">Vencido</span>' : (dias <= 30 ? '<span class="label bg-yellow">' + dias + ' día(s)</span>' : '<span class="label bg-green">Vigente</span>'));
		html += '<tr><td>' + appEscapeHtml(l.codigo_lote || ("#" + l.idlote)) + '</td><td>' + (l.fecha_vencimiento ? l.fecha_vencimiento.split("-").reverse().join("/") : "—") + '</td><td>' + estado +
			'</td><td class="text-right">' + window.appCantidad(l.stock) + '</td><td class="text-right">' + window.appCantidad(l.cantidad_inicial) + '</td></tr>';
	});
	if (!lotes.length) { html = '<tr><td colspan="5" class="text-soft">Sin lotes con stock.</td></tr>'; }
	$("#tblLotesArticulo tbody").html(html);
	var sinLote = Math.round((Number(data.stock) - enLotes) * 1000) / 1000;
	$("#lotesSinLote").text(sinLote > 0 ? "Además hay " + window.appCantidad(sinLote) + " sin lote (stock anterior o comprado sin fecha)." : "");
	$("#tituloLotes, #bloqueLotes").show();
}

// ---------- Presentaciones ----------

function agregarPresentacion(datos){
	datos = datos || {};
	var fila = '<tr>' +
		'<td><input type="hidden" name="pres_id[]" value="' + (parseInt(datos.idpresentacion, 10) || 0) + '">' +
		'<input class="form-control" type="text" name="pres_nombre[]" maxlength="60" placeholder="Ej. Caja x100" value="' + appEscapeHtml(datos.nombre || "") + '"></td>' +
		'<td><input class="form-control" type="number" step="0.001" min="0" name="pres_factor[]" placeholder="100" value="' + (datos.factor ? Number(datos.factor) : "") + '"></td>' +
		'<td><input class="form-control" type="number" step="0.01" min="0" name="pres_precio_venta[]" value="' + Number(datos.precio_venta || 0).toFixed(2) + '"></td>' +
		'<td><input class="form-control" type="number" step="0.01" min="0" name="pres_precio_compra[]" value="' + Number(datos.precio_compra || 0).toFixed(2) + '"></td>' +
		'<td><input class="form-control" type="text" name="pres_codigo[]" maxlength="50" placeholder="Opcional" value="' + appEscapeHtml(datos.codigo || "") + '"></td>' +
		'<td class="text-center"><button type="button" class="btn btn-danger btn-xs btn-icon" title="Quitar presentación" onclick="$(this).closest(\'tr\').remove()"><i class="fa fa-trash"></i></button></td>' +
		'</tr>';
	$("#tblPresentaciones tbody").append(fila);
	if (!datos.nombre) { $("#tblPresentaciones tbody tr:last input[name='pres_nombre[]']").focus(); }
}

// ---------- Precio por mayor ----------

function agregarEscala(datos){
	datos = datos || {};
	var u = unidadSeleccionada();
	var fila = '<tr>' +
		'<td><input class="form-control input-cantidad" type="number" step="' + (u.fraccion ? "0.001" : "1") + '" min="0" name="escala_cantidad[]" placeholder="12" value="' + (datos.cantidad_minima ? Number(datos.cantidad_minima) : "") + '"></td>' +
		'<td><input class="form-control" type="number" step="0.01" min="0" name="escala_precio[]" placeholder="0.00" value="' + (datos.precio ? Number(datos.precio).toFixed(2) : "") + '"></td>' +
		'<td class="text-center"><button type="button" class="btn btn-danger btn-xs btn-icon" title="Quitar precio por mayor" onclick="$(this).closest(\'tr\').remove()"><i class="fa fa-trash"></i></button></td>' +
		'</tr>';
	$("#tblEscalas tbody").append(fila);
	if (!datos.precio) { $("#tblEscalas tbody tr:last input:first").focus(); }
}

function mostrarform(flag){
	limpiar();
	if (flag) {
		$("#listadoregistros").hide();
		$("#formularioregistros").show();
		$("#btnGuardar").prop("disabled", false);
		$("#btnagregar").hide();
		setTimeout(function(){ $("#nombre").focus(); }, 60);
	} else {
		$("#listadoregistros").show();
		$("#formularioregistros").hide();
		$("#btnagregar").show();
	}
}

function cancelarform(){
	limpiar();
	mostrarform(false);
}

function listar(){
	tabla = $('#tbllistado').dataTable({
		"aProcessing": true,
		"aServerSide": true,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Reporte de Artículos', true),
		"ajax": {
			url: '../ajax/articulo.php?op=listar',
			type: "get",
			dataType: "json",
			error: function(e){ console.log(e.responseText); }
		},
		"bDestroy": true,
		"iDisplayLength": 10,
		"order": [[1, "asc"]],
		"columnDefs": [{ "orderable": false, "targets": [0, 9] }]
	}).DataTable();
}

function calcularMargen(){
	var pc = parseFloat($("#precio_compra").val()) || 0;
	var pv = parseFloat($("#precio_venta").val()) || 0;
	var $m = $("#margenAyuda");
	if (pv <= 0) { $m.text("Margen: —").css("color", ""); return; }
	var ganancia = pv - pc;
	var margen = pv > 0 ? (ganancia / pv) * 100 : 0;
	$m.text("Margen: " + (window.appMoney ? window.appMoney(ganancia) : ganancia.toFixed(2)) + " (" + margen.toFixed(1) + "%)")
		.css("color", ganancia < 0 ? "#dc2626" : (ganancia === 0 ? "" : "#16a34a"));
}

function previsualizarImagen(){
	var file = this.files && this.files[0];
	if (!file) { return; }
	if (file.size > 3 * 1024 * 1024) {
		appNotify("warning", "La imagen supera los 3 MB permitidos.");
		$(this).val("");
		return;
	}
	var reader = new FileReader();
	reader.onload = function(ev){ $("#imagenmuestra").attr("src", ev.target.result).show(); };
	reader.readAsDataURL(file);
}

function guardaryeditar(e){
	e.preventDefault();
	if (!$.trim($("#nombre").val())) { appNotify("warning", "El nombre es obligatorio."); $("#nombre").focus(); return; }
	if (!$("#idcategoria").val()) { appNotify("warning", "Selecciona una categoría."); return; }
	if (!$("#idunidad").val()) { appNotify("warning", "Selecciona una unidad de medida."); return; }
	if (!$.trim($("#codigo").val())) { appNotify("warning", "Ingresa o genera un código."); $("#codigo").focus(); return; }
	var fraccion = unidadSeleccionada().fraccion;
	$("#stock").val(window.appNormalizarCantidad($("#stock").val(), fraccion, 0));
	$("#stock_minimo").val(window.appNormalizarCantidad($("#stock_minimo").val(), fraccion, 0));
	appSetLoading("#btnGuardar", true);
	var formData = new FormData($("#formulario")[0]);

	$.ajax({
		url: "../ajax/articulo.php?op=guardaryeditar",
		type: "POST",
		data: formData,
		contentType: false,
		processData: false,
		success: function(datos){
			appSetLoading("#btnGuardar", false);
			var txt = $.trim(datos || "");
			appNotifyFromResponse(txt);
			if (txt.toLowerCase().indexOf("correctamente") !== -1) {
				mostrarform(false);
				tabla.ajax.reload(null, false);
			}
		},
		error: function(){ appSetLoading("#btnGuardar", false); }
	});
}

function mostrar(idarticulo){
	$.post("../ajax/articulo.php?op=mostrar", { idarticulo: idarticulo }, function(data){
		data = appParseJson(data, null);
		if (!data) { appNotify("error", "No se pudo cargar el artículo."); return; }
		mostrarform(true);
		$("#formTitulo").text("Editar artículo");
		$("#idcategoria").val(data.idcategoria).selectpicker('refresh');
		$("#idunidad").val(data.idunidad).selectpicker('refresh');
		aplicarUnidadBase();
		$("#codigo").val(data.codigo);
		$("#nombre").val(data.nombre);
		$("#stock").val(Number(data.stock || 0));
		$("#stock_minimo").val(Number(data.stock_minimo || 0));
		(data.presentaciones || []).forEach(function(p){ agregarPresentacion(p); });
		(data.escalas || []).forEach(function(es){ agregarEscala(es); });
		mostrarLotes(data);
		$("#precio_compra").val(parseFloat(data.precio_compra || 0).toFixed(2));
		$("#precio_venta").val(parseFloat(data.precio_venta || 0).toFixed(2));
		$("#descripcion").val(data.descripcion);
		if (data.imagen) {
			$("#imagenmuestra").attr("src", "../files/articulos/" + data.imagen).show();
		}
		$("#imagenactual").val(data.imagen || "");
		$("#idarticulo").val(data.idarticulo);
		$("#stockAyuda").show();
		calcularMargen();
		generarbarcode(true);
	});
}

function normalizarEnteroNoNegativo(valor, fallback){
	var num = parseFloat(valor);
	if (!isFinite(num)) { return fallback; }
	num = Math.round(num);
	return num < 0 ? 0 : num;
}

function desactivar(idarticulo){
	appConfirm("El artículo dejará de aparecer en ventas y compras. ¿Desactivar?", function(){
		$.post("../ajax/articulo.php?op=desactivar", { idarticulo: idarticulo }, function(e){
			appNotifyFromResponse(e);
			tabla.ajax.reload(null, false);
		});
	}, { titulo: "Desactivar artículo", ok: "Sí, desactivar", tipo: "warning" });
}

function activar(idarticulo){
	appConfirm("¿Volver a activar este artículo?", function(){
		$.post("../ajax/articulo.php?op=activar", { idarticulo: idarticulo }, function(e){
			appNotifyFromResponse(e);
			tabla.ajax.reload(null, false);
		});
	}, { titulo: "Activar artículo", ok: "Sí, activar", tipo: "success" });
}

function generarCodigoArticulo(){
	var nombre = $.trim($("#nombre").val()).toUpperCase().replace(/[^A-Z0-9]/g, "");
	var prefijo = nombre.length >= 3 ? nombre.substring(0, 3) : "ART";
	var aleatorio = Math.floor(100000 + (Math.random() * 900000));
	var codigo = prefijo + "-" + aleatorio;
	$("#codigo").val(codigo);
	generarbarcode(true);
	appNotify("success", "Código generado: " + codigo);
}

function generarbarcode(silencioso){
	var codigo = $.trim($("#codigo").val());
	if (!codigo) {
		if (!silencioso) { appNotify("warning", "Ingresa o genera un código antes de crear el código de barras."); }
		return;
	}
	if (typeof JsBarcode !== "function") {
		appNotify("error", "No se pudo cargar la librería de código de barras.");
		return;
	}
	try {
		JsBarcode("#barcode", codigo, { format: "CODE128", lineColor: "#0f172a", width: 2, height: 56, displayValue: true, fontSize: 13 });
		$("#print").show();
		if (!silencioso) { appNotify("success", "Código de barras generado."); }
	} catch (err) {
		appNotify("error", "El código contiene caracteres no válidos para CODE128.");
	}
}

function imprimir(){
	if (!$.trim($("#codigo").val())) {
		appNotify("warning", "No hay código para imprimir.");
		return;
	}
	if (!$("#print").is(":visible")) { generarbarcode(true); }
	$("#print").printArea();
}

init();
