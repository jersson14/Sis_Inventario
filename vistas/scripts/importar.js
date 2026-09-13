/* Importación desde Excel / CSV */
var tokenImport = "";
var previewActual = null;

var COLUMNAS = {
	articulos: [["nombre", "Nombre", true], ["codigo", "Código", false], ["categoria", "Categoría", false], ["unidad", "Unidad", false], ["stock", "Stock", false], ["stock_minimo", "Stock mínimo", false], ["precio_compra", "P. compra", false], ["precio_venta", "P. venta", false], ["descripcion", "Descripción", false]],
	clientes: [["nombre", "Nombre", true], ["tipo_documento", "Tipo doc.", false], ["num_documento", "N° documento", false], ["direccion", "Dirección", false], ["telefono", "Teléfono", false], ["email", "Email", false]],
	proveedores: [["nombre", "Nombre", true], ["tipo_documento", "Tipo doc.", false], ["num_documento", "N° documento", false], ["direccion", "Dirección", false], ["telefono", "Teléfono", false], ["email", "Email", false]]
};

function tipo(){ return $("#tipo").val() || "articulos"; }

function actualizarAyuda(){
	var t = tipo();
	$("#btnPlantillaXlsx").attr("href", "../ajax/importar.php?op=plantilla&tipo=" + t + "&formato=xlsx");
	$("#btnPlantillaCsv").attr("href", "../ajax/importar.php?op=plantilla&tipo=" + t + "&formato=csv");
	var cols = COLUMNAS[t].map(function(c){ return c[2] ? "<strong>" + c[1] + "</strong>" : c[1]; }).join(", ");
	var extra = t === "articulos" ? " La categoría es obligatoria para artículos nuevos; la unidad debe existir (und, kg, lt…). Se reconoce por <em>código</em> y, si no hay, por <em>nombre</em>." : " Se reconoce por <em>número de documento</em> y, si no hay, por <em>nombre</em>.";
	$("#ayudaColumnas").html('<i class="fa fa-info-circle"></i> Columnas: ' + cols + '.' + extra);
	$(".opt-art").toggle(t === "articulos");
	$("#linkVer").attr("href", t === "articulos" ? "articulo.php" : (t === "clientes" ? "cliente.php" : "proveedor.php"));
}

function pintarPreview(r){
	previewActual = r;
	$("#boxInicio, #boxResultado").hide();
	$("#boxPreview").show();
	$("#chipArchivo").text(r.total + " fila(s)" + (r.truncado ? " · se leyeron solo las primeras 5000" : ""));
	$("#resCrear").text(r.crear); $("#resActualizar").text(r.actualizar); $("#resError").text(r.error);
	var faltan = COLUMNAS[tipo()].filter(function(c){ return r.cabeceras.indexOf(c[0]) === -1; });
	var avisos = "";
	if (faltan.length) { avisos += '<div class="alert alert-warning" style="font-size:13px"><i class="fa fa-exclamation-triangle"></i> Columnas no encontradas en el archivo: ' + faltan.map(function(c){ return c[1]; }).join(", ") + '. Se tomarán valores vacíos/por defecto.</div>'; }
	if (r.categorias_nuevas && r.categorias_nuevas.length) { avisos += '<div class="alert alert-info" style="font-size:13px"><i class="fa fa-tags"></i> Categorías nuevas detectadas: ' + r.categorias_nuevas.map(appEscapeHtml).join(", ") + '.</div>'; }
	$("#avisoColumnas").html(avisos);
	var cols = COLUMNAS[tipo()];
	var head = "<tr><th>#</th><th>Acción</th>" + cols.map(function(c){ return "<th>" + c[1] + "</th>"; }).join("") + "<th>Detalle</th></tr>";
	var body = "";
	(r.filas || []).forEach(function(f){
		var badge = f.accion === "crear" ? '<span class="label bg-green">Nuevo</span>' : (f.accion === "actualizar" ? '<span class="label bg-aqua">Actualizar</span>' : '<span class="label bg-red">Error</span>');
		body += "<tr" + (f.accion === "error" ? ' style="background:#fff1f2"' : "") + "><td>" + f.fila + "</td><td>" + badge + "</td>";
		cols.forEach(function(c){
			var v = f[c[0]]; if (v === null || typeof v === "undefined") v = "";
			if (c[0] === "stock" && f.stock_actual !== null && typeof f.stock_actual !== "undefined") v = v + ' <small class="text-soft">(actual ' + f.stock_actual + ')</small>';
			else v = appEscapeHtml(String(v));
			if (c[0] === "categoria" && f.categoria_nueva) v += ' <small class="text-warning">(nueva)</small>';
			body += "<td>" + v + "</td>";
		});
		body += "<td>" + (f.errores && f.errores.length ? '<small class="text-danger">' + f.errores.map(appEscapeHtml).join("; ") + '</small>' : "") + "</td></tr>";
	});
	$("#tblPreview thead").html(head); $("#tblPreview tbody").html(body);
	$("#btnImportar").prop("disabled", (r.crear + r.actualizar) === 0);
}

function init(){
	actualizarAyuda();
	$("#tipo").on("change", function(){ actualizarAyuda(); cancelar(); });
	$("#formArchivo").on("submit", function(e){
		e.preventDefault();
		var file = $("#archivo")[0].files[0];
		if (!file) { appNotify("warning", "Selecciona un archivo."); return; }
		var fd = new FormData(); fd.append("archivo", file); fd.append("tipo", tipo());
		appSetLoading("#btnPrevisualizar", true);
		$.ajax({ url: "../ajax/importar.php?op=previsualizar&tipo=" + tipo(), type: "POST", data: fd, contentType: false, processData: false,
			success: function(resp){
				appSetLoading("#btnPrevisualizar", false);
				var r = appParseJson(resp, null);
				if (!r || !r.ok) { appNotify("error", (r && r.message) || "No se pudo leer el archivo."); return; }
				tokenImport = r.token;
				pintarPreview(r);
				appNotify("success", "Archivo analizado: " + r.total + " fila(s).");
			},
			error: function(){ appSetLoading("#btnPrevisualizar", false); }
		});
	});
	$("#btnImportar").on("click", function(){
		if (!previewActual) { return; }
		var msg = "Se crearán <strong>" + previewActual.crear + "</strong> y se " + ($("#opActualizar").is(":checked") ? "actualizarán <strong>" + previewActual.actualizar + "</strong>" : "omitirán " + previewActual.actualizar + " existentes") + ". Las filas con error se omiten. ¿Continuar?";
		appConfirm(msg, function(){
			appSetLoading("#btnImportar", true);
			$.post("../ajax/importar.php?op=importar&tipo=" + tipo(), { token: tokenImport, actualizar_existentes: $("#opActualizar").is(":checked") ? 1 : 0, crear_categorias: $("#opCategorias").is(":checked") ? 1 : 0, actualizar_stock: $("#opStock").is(":checked") ? 1 : 0 }, function(resp){
				appSetLoading("#btnImportar", false);
				var r = appParseJson(resp, { ok: false, message: resp });
				if (!r.ok) { appNotify("error", r.message, 8000); return; }
				appNotify("success", r.message, 7000);
				$("#boxPreview").hide();
				$("#resultadoImport").html('<div class="empty-state"><i class="fa fa-check-circle" style="color:#16a34a"></i><strong>Importación completada</strong>' + appEscapeHtml(r.message) + (r.categorias ? "<br>Categorías creadas: " + r.categorias : "") + (r.ajustes ? "<br>Ajustes de stock: " + r.ajustes : "") + "</div>");
				$("#boxResultado").show();
				$("#archivo").val(""); tokenImport = ""; previewActual = null;
				if (window.cargarAlertasBell) { window.cargarAlertasBell(); }
			}).fail(function(){ appSetLoading("#btnImportar", false); });
		}, { titulo: "Confirmar importación", ok: "Sí, importar", tipo: "success" });
	});
	$("#btnCancelar").on("click", cancelar);
	$("#btnOtra").on("click", function(){ $("#boxResultado").hide(); $("#boxInicio").show(); });
}

function cancelar(){
	if (tokenImport) { $.post("../ajax/importar.php?op=cancelar&tipo=" + tipo(), {}); }
	tokenImport = ""; previewActual = null;
	$("#boxPreview, #boxResultado").hide(); $("#boxInicio").show();
	$("#archivo").val("");
}

init();
