/* Clientes y proveedores (comparten lógica). window.appPersonaTipo = "Cliente" | "Proveedor" */
var tabla;
var TIPO = window.appPersonaTipo === "Proveedor" ? "Proveedor" : "Cliente";
var OP_LISTAR = TIPO === "Proveedor" ? "listarp" : "listarc";
var SINGULAR = TIPO.toLowerCase();

function init(){
	mostrarform(false);
	listar();
	$("#formulario").on("submit", function(e){ guardaryeditar(e); });
	if (window.appQueryParam && window.appQueryParam("nuevo") === "1") { mostrarform(true); }
}

function limpiar(){
	$("#idpersona, #nombre, #num_documento, #direccion, #telefono, #email").val("");
	$("#tipo_documento").val("DNI");
	$("#formTitulo").text("Nuevo " + SINGULAR);
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
		buttons: window.appDataTableButtons('Reporte de ' + TIPO + 's', true),
		"ajax": { url: '../ajax/persona.php?op=' + OP_LISTAR, type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } },
		"bDestroy": true,
		"iDisplayLength": 10,
		"order": [[1, "asc"]],
		"columnDefs": [{ "orderable": false, "targets": [0] }]
	}).DataTable();
}

function guardaryeditar(e){
	e.preventDefault();
	if (!$.trim($("#nombre").val())) { appNotify("warning", "El nombre es obligatorio."); $("#nombre").focus(); return; }
	var email = $.trim($("#email").val());
	if (email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { appNotify("warning", "El email no tiene un formato válido."); $("#email").focus(); return; }
	appSetLoading("#btnGuardar", true);
	$.ajax({
		url: "../ajax/persona.php?op=guardaryeditar",
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

function mostrar(idpersona){
	$.post("../ajax/persona.php?op=mostrar", { idpersona: idpersona }, function(data){
		data = appParseJson(data, null);
		if (!data) { appNotify("error", "No se pudo cargar el registro."); return; }
		mostrarform(true);
		$("#formTitulo").text("Editar " + SINGULAR);
		$("#nombre").val(data.nombre);
		$("#tipo_documento").val(data.tipo_documento || "DNI");
		$("#num_documento").val(data.num_documento);
		$("#direccion").val(data.direccion);
		$("#telefono").val(data.telefono);
		$("#email").val(data.email);
		$("#idpersona").val(data.idpersona);
	});
}

function eliminar(idpersona){
	appConfirm("El " + SINGULAR + " se desactivará y dejará de aparecer en los formularios, pero su historial se conserva. ¿Continuar?", function(){
		$.post("../ajax/persona.php?op=eliminar", { idpersona: idpersona }, function(e){ appNotifyFromResponse(e); tabla.ajax.reload(null, false); });
	}, { titulo: "Desactivar " + SINGULAR, ok: "Sí, desactivar", tipo: "warning" });
}

function activar(idpersona){
	appConfirm("¿Volver a activar este " + SINGULAR + "?", function(){
		$.post("../ajax/persona.php?op=activar", { idpersona: idpersona }, function(e){ appNotifyFromResponse(e); tabla.ajax.reload(null, false); });
	}, { titulo: "Activar " + SINGULAR, ok: "Sí, activar", tipo: "success" });
}

init();
