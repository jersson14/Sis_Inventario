var tabla;

function init(){
	mostrarform(false);
	listar();
	$("#formulario").on("submit", function(e){ guardaryeditar(e); });
	if (window.appQueryParam && window.appQueryParam("nuevo") === "1") { mostrarform(true); }
}

function limpiar(){
	$("#idcategoria, #nombre, #descripcion").val("");
	$("#formTitulo").text("Nueva categoría");
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

function cancelarform(){ limpiar(); mostrarform(false); }

function listar(){
	tabla = $('#tbllistado').dataTable({
		"aProcessing": true,
		"aServerSide": true,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Reporte de Categorías', true),
		"ajax": { url: '../ajax/categoria.php?op=listar', type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } },
		"bDestroy": true,
		"iDisplayLength": 10,
		"order": [[1, "asc"]],
		"columnDefs": [{ "orderable": false, "targets": [0] }]
	}).DataTable();
}

function guardaryeditar(e){
	e.preventDefault();
	if (!$.trim($("#nombre").val())) { appNotify("warning", "El nombre es obligatorio."); return; }
	appSetLoading("#btnGuardar", true);
	$.ajax({
		url: "../ajax/categoria.php?op=guardaryeditar",
		type: "POST",
		data: new FormData($("#formulario")[0]),
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

function mostrar(idcategoria){
	$.post("../ajax/categoria.php?op=mostrar", { idcategoria: idcategoria }, function(data){
		data = appParseJson(data, null);
		if (!data) { appNotify("error", "No se pudo cargar la categoría."); return; }
		mostrarform(true);
		$("#formTitulo").text("Editar categoría");
		$("#nombre").val(data.nombre);
		$("#descripcion").val(data.descripcion);
		$("#idcategoria").val(data.idcategoria);
	});
}

function desactivar(idcategoria){
	appConfirm("¿Desactivar esta categoría? Sus artículos se mantienen, pero no podrás asignarla a nuevos.", function(){
		$.post("../ajax/categoria.php?op=desactivar", { idcategoria: idcategoria }, function(e){ appNotifyFromResponse(e); tabla.ajax.reload(null, false); });
	}, { titulo: "Desactivar categoría", ok: "Sí, desactivar", tipo: "warning" });
}

function activar(idcategoria){
	appConfirm("¿Activar esta categoría?", function(){
		$.post("../ajax/categoria.php?op=activar", { idcategoria: idcategoria }, function(e){ appNotifyFromResponse(e); tabla.ajax.reload(null, false); });
	}, { titulo: "Activar categoría", ok: "Sí, activar", tipo: "success" });
}

init();
