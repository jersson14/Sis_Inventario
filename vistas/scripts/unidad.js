var tabla;

function init(){
	mostrarform(false);
	listar();

	$("#formulario").on("submit",function(e){
		guardaryeditar(e);
	});
}

function limpiar(){
	$("#idunidad").val("");
	$("#nombre").val("");
	$("#abreviatura").val("");
	$("#descripcion").val("");
}

function mostrarform(flag){
	limpiar();
	if(flag){
		$("#listadoregistros").hide();
		$("#formularioregistros").show();
		$("#btnGuardar").prop("disabled",false);
	}else{
		$("#listadoregistros").show();
		$("#formularioregistros").hide();
	}
}

function cancelarform(){
	limpiar();
	mostrarform(false);
}

function listar(){
	tabla=$('#tbllistado').dataTable({
		"aProcessing": true,
		"aServerSide": true,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Reporte de Unidades de Medida', true),
		"ajax":
		{
			url:'../ajax/unidad.php?op=listar',
			type: "get",
			dataType : "json",
			error:function(e){
				console.log(e.responseText);
			}
		},
		"bDestroy":true,
		"iDisplayLength":10,
		"order":[[1,"asc"]]
	}).DataTable();
}

function guardaryeditar(e){
	e.preventDefault();
	$("#btnGuardar").prop("disabled",true);
	var formData=new FormData($("#formulario")[0]);

	$.ajax({
		url: "../ajax/unidad.php?op=guardaryeditar",
		type: "POST",
		data: formData,
		contentType: false,
		processData: false,
		success: function(datos){
			bootbox.alert(datos);
			mostrarform(false);
			tabla.ajax.reload();
		}
	});

	limpiar();
}

function mostrar(idunidad){
	$.post("../ajax/unidad.php?op=mostrar",{idunidad : idunidad}, function(data){
		data=JSON.parse(data);
		mostrarform(true);
		$("#idunidad").val(data.idunidad);
		$("#nombre").val(data.nombre);
		$("#abreviatura").val(data.abreviatura);
		$("#descripcion").val(data.descripcion);
	});
}

function desactivar(idunidad){
	bootbox.confirm("¿Esta seguro de desactivar esta unidad?", function(result){
		if (result) {
			$.post("../ajax/unidad.php?op=desactivar", {idunidad : idunidad}, function(e){
				bootbox.alert(e);
				tabla.ajax.reload();
			});
		}
	});
}

function activar(idunidad){
	bootbox.confirm("¿Esta seguro de activar esta unidad?", function(result){
		if (result) {
			$.post("../ajax/unidad.php?op=activar", {idunidad : idunidad}, function(e){
				bootbox.alert(e);
				tabla.ajax.reload();
			});
		}
	});
}

init();

