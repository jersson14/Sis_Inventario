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
	});
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
	calcularMargen();
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
	$("#stock").val(normalizarEnteroNoNegativo($("#stock").val(), 0));
	$("#stock_minimo").val(normalizarEnteroNoNegativo($("#stock_minimo").val(), 1));
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
		$("#codigo").val(data.codigo);
		$("#nombre").val(data.nombre);
		$("#stock").val(normalizarEnteroNoNegativo(data.stock, 0));
		$("#stock_minimo").val(normalizarEnteroNoNegativo(data.stock_minimo, 1));
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
