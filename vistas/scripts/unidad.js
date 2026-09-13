var tabla;

function init(){
	mostrarform(false);
	listar();
	$("#formulario").on("submit", function(e){ guardaryeditar(e); });
}

function limpiar(){
	$("#idunidad, #nombre, #abreviatura, #descripcion").val("");
	$("#formTitulo").text("Nueva unidad");
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
		buttons: window.appDataTableButtons('Unidades de medida', true),
		"ajax": { url: '../ajax/unidad.php?op=listar', type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } },
		"bDestroy": true,
		"iDisplayLength": 10,
		"order": [[1, "asc"]],
		"columnDefs": [{ "orderable": false, "targets": [0] }]
	}).DataTable();
}

function guardaryeditar(e){
	e.preventDefault();
	if (!$.trim($("#nombre").val()) || !$.trim($("#abreviatura").val())) { appNotify("warning", "Nombre y abreviatura son obligatorios."); return; }
	appSetLoading("#btnGuardar", true);
	$.ajax({
		url: "../ajax/unidad.php?op=guardaryeditar",
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

function mostrar(idunidad){
	$.post("../ajax/unidad.php?op=mostrar", { idunidad: idunidad }, function(data){
		data = appParseJson(data, null);
		if (!data) { appNotify("error", "No se pudo cargar la unidad."); return; }
		mostrarform(true);
		$("#formTitulo").text("Editar unidad");
		$("#nombre").val(data.nombre);
		$("#abreviatura").val(data.abreviatura);
		$("#descripcion").val(data.descripcion);
		$("#idunidad").val(data.idunidad);
	});
}

function desactivar(idunidad){
	appConfirm("¿Desactivar esta unidad de medida?", function(){
		$.post("../ajax/unidad.php?op=desactivar", { idunidad: idunidad }, function(e){ appNotifyFromResponse(e); tabla.ajax.reload(null, false); });
	}, { titulo: "Desactivar unidad", ok: "Sí, desactivar", tipo: "warning" });
}

function activar(idunidad){
	appConfirm("¿Activar esta unidad de medida?", function(){
		$.post("../ajax/unidad.php?op=activar", { idunidad: idunidad }, function(e){ appNotifyFromResponse(e); tabla.ajax.reload(null, false); });
	}, { titulo: "Activar unidad", ok: "Sí, activar", tipo: "success" });
}

init();
