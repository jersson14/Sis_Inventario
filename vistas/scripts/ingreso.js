var tabla;
var tablaArticulos;
var empresaDefaultsIngreso = {
	serie_boleta: "B001",
	serie_factura: "F001",
	serie_ticket: "T001",
	impuesto_default: 18,
	moneda: "PEN",
	simbolo_moneda: "S/"
};
var correlativoRequestIdIngreso = 0;
var proveedoresCargados = false;
var numeroComprobanteManualIngreso = false;

function notifyIngreso(type, message){
	if (typeof appNotify === "function") {
		appNotify(type, message);
		return;
	}
	alert(message);
}

function normalizarCantidadEntera(valor, minimo){
	var num = parseFloat(valor);
	if (!isFinite(num)) {
		return minimo;
	}
	num = Math.round(num);
	if (num < minimo) {
		num = minimo;
	}
	return num;
}

function fechaHoraActualInput(){
	var now = new Date();
	var y = now.getFullYear();
	var m = ("0" + (now.getMonth() + 1)).slice(-2);
	var d = ("0" + now.getDate()).slice(-2);
	var h = ("0" + now.getHours()).slice(-2);
	var min = ("0" + now.getMinutes()).slice(-2);
	return y + "-" + m + "-" + d + "T" + h + ":" + min;
}

function normalizarFechaHoraInput(valor){
	var raw = (valor || "").toString().trim();
	if (!raw) {
		return fechaHoraActualInput();
	}
	var m = raw.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/);
	if (m) {
		return m[1] + "T" + m[2];
	}
	var d = new Date(raw);
	if (!isNaN(d.getTime())) {
		var y = d.getFullYear();
		var mm = ("0" + (d.getMonth() + 1)).slice(-2);
		var dd = ("0" + d.getDate()).slice(-2);
		var hh = ("0" + d.getHours()).slice(-2);
		var mi = ("0" + d.getMinutes()).slice(-2);
		return y + "-" + mm + "-" + dd + "T" + hh + ":" + mi;
	}
	return fechaHoraActualInput();
}

//funcion que se ejecuta al inicio
function init(){
   mostrarform(false);
   listar();

   $("#formulario").on("submit",function(e){
   	guardaryeditar(e);
   });

   $("#btnFiltrarIngreso").on("click", function(){
   	recargarListadoIngreso();
   });
   $("#btnLimpiarFiltroIngreso").on("click", function(){
   	$("#filtro_ingreso_inicio").val("");
   	$("#filtro_ingreso_fin").val("");
   	recargarListadoIngreso();
   });

   cargarProveedores();

   $("#myModal").on("shown.bs.modal", function(){
   	if (!tablaArticulos) {
   		listarArticulos();
   	} else {
   		$("#tblarticulos, #tblarticulos_wrapper").css("width","100%");
   		tablaArticulos.columns.adjust();
   		tablaArticulos.ajax.reload(null, false);
   	}
   	estilizarBuscadorCatalogo();
   	setTimeout(function(){
   		$("#tblarticulos_filter input").focus();
   	}, 80);
   });
   cargarDefaultsEmpresaIngreso();
   $("#formProveedorRapido").on("submit", function(e){
   	guardarProveedorRapido(e);
   });
   $("#modalProveedorIngreso").on("shown.bs.modal", function(){
   	$("#prv_nombre").focus();
   });
   $("#modalProveedorIngreso").on("hidden.bs.modal", function(){
   	limpiarFormProveedorRapido();
   });
   $("#num_comprobante").on("input", function(){
   	numeroComprobanteManualIngreso = true;
   });
   $("#serie_comprobante").on("change blur", function(){
   	cargarCorrelativoIngreso();
   });

}

function cargarProveedores(idPreferido){
	var idActual = (typeof idPreferido !== "undefined" && idPreferido !== null && idPreferido !== "") ? idPreferido : ($("#idproveedor").val() || "");
	$.post("../ajax/ingreso.php?op=selectProveedor", function(r){
		$("#idproveedor").html(r);
		var existeActual = false;
		if (idActual !== "") {
			$("#idproveedor option").each(function(){
				if (String($(this).val()) === String(idActual)) {
					existeActual = true;
					return false;
				}
			});
		}
		var idFinal = existeActual ? String(idActual) : ($("#idproveedor option:first").val() || "");
		$("#idproveedor").val(idFinal);
		$("#idproveedor").selectpicker("refresh");
		proveedoresCargados = true;
	});
}

function limpiarFormProveedorRapido(){
	$("#prv_nombre").val("");
	$("#prv_tipo_documento").val("DNI");
	$("#prv_num_documento").val("");
	$("#prv_direccion").val("");
	$("#prv_telefono").val("");
	$("#prv_email").val("");
	$("#btnGuardarProveedorRapido").prop("disabled", false);
}

function guardarProveedorRapido(e){
	e.preventDefault();
	var nombre = $.trim($("#prv_nombre").val());
	if (!nombre) {
		notifyIngreso("warning", "El nombre del proveedor es obligatorio.");
		return;
	}
	$("#btnGuardarProveedorRapido").prop("disabled", true);
	$.ajax({
		url: "../ajax/ingreso.php?op=crearProveedorRapido",
		type: "POST",
		data: $("#formProveedorRapido").serialize(),
		success: function(resp){
			var r = {};
			try {
				r = JSON.parse(resp);
			} catch (e) {
				r = {ok:false, message:"No se pudo registrar el proveedor."};
			}
			if (!r.ok) {
				notifyIngreso("error", r.message || "No se pudo registrar el proveedor.");
				$("#btnGuardarProveedorRapido").prop("disabled", false);
				return;
			}
			notifyIngreso("success", r.message || "Proveedor registrado correctamente.");
			$("#modalProveedorIngreso").modal("hide");
			cargarProveedores(r.idproveedor || "");
		},
		error: function(){
			notifyIngreso("error", "Ocurrio un error al registrar el proveedor.");
			$("#btnGuardarProveedorRapido").prop("disabled", false);
		}
	});
}

function cargarDefaultsEmpresaIngreso(){
	$.get("../ajax/empresa.php?op=defaults", function(resp){
		try{
			var r = JSON.parse(resp);
			empresaDefaultsIngreso.serie_boleta = r.serie_boleta || empresaDefaultsIngreso.serie_boleta;
			empresaDefaultsIngreso.serie_factura = r.serie_factura || empresaDefaultsIngreso.serie_factura;
			empresaDefaultsIngreso.serie_ticket = r.serie_ticket || empresaDefaultsIngreso.serie_ticket;
			empresaDefaultsIngreso.impuesto_default = parseFloat(r.impuesto_default || empresaDefaultsIngreso.impuesto_default);
			empresaDefaultsIngreso.moneda = r.moneda || empresaDefaultsIngreso.moneda;
			empresaDefaultsIngreso.simbolo_moneda = r.simbolo_moneda || empresaDefaultsIngreso.simbolo_moneda;
		}catch(e){}
		aplicarSerieImpuestoIngreso();
	});
}

//funcion limpiar
function limpiar(){

	$("#idingreso").val("");
	if (proveedoresCargados) {
		var primerProveedor = $("#idproveedor option:first").val() || "";
		$("#idproveedor").val(primerProveedor);
		$("#idproveedor").selectpicker("refresh");
	} else {
		$("#idproveedor").val("");
	}
	$("#proveedor").val("");
	$("#serie_comprobante").val("");
	$("#num_comprobante").val("");
	$("#impuesto").val("");
	numeroComprobanteManualIngreso = false;

	$("#total_compra").val("");
	$(".filas").remove();
	$("#total").html(window.appMoney ? window.appMoney(0,2) : ((window.appCurrencySymbol || "S/") + " 0.00"));
	actualizarContadorItems();

	$("#fecha_hora").val(fechaHoraActualInput());

	//marcamos el primer tipo_documento
	$("#tipo_comprobante").val("Boleta");
	$("#tipo_comprobante").selectpicker('refresh');
	aplicarSerieImpuestoIngreso();

}

//funcion mostrar formulario
function mostrarform(flag){
	limpiar();
	if(flag){
		$("#listadoregistros").hide();
		$("#formularioregistros").show();
		$("#btnagregar").hide();

		$("#btnGuardar").hide();
		$("#btnCancelar").show();
		detalles=0;
		$("#btnAgregarArt").show();

	}else{
		$("#listadoregistros").show();
		$("#formularioregistros").hide();
		$("#btnagregar").show();
	}
}

//cancelar form
function cancelarform(){
	limpiar();
	mostrarform(false);
}

//funcion listar
function listar(){
	tabla=$('#tbllistado').dataTable({
		"aProcessing": true,//activamos el procedimiento del datatable
		"aServerSide": true,//paginacion y filrado realizados por el server
		dom: 'Bfrtip',//definimos los elementos del control de la tabla
		buttons: window.appDataTableButtons('Reporte de Ingresos', true),
		"ajax":
		{
			url:'../ajax/ingreso.php?op=listar',
			type: "get",
			data: function(d){
				d.fecha_inicio = ($("#filtro_ingreso_inicio").val() || "").trim();
				d.fecha_fin = ($("#filtro_ingreso_fin").val() || "").trim();
			},
			dataType : "json",
			error:function(e){
				console.log(e.responseText);
			}
		},
		"bDestroy":true,
		"iDisplayLength":10,//paginacion
		"order":[[0,"desc"]]//ordenar (columna, orden)
	}).DataTable();
}

function recargarListadoIngreso(){
	var fi = ($("#filtro_ingreso_inicio").val() || "").trim();
	var ff = ($("#filtro_ingreso_fin").val() || "").trim();
	if (fi && ff && fi > ff) {
		notifyIngreso("warning", "La fecha 'Desde' no puede ser mayor que 'Hasta'.");
		return;
	}
	if (tabla) {
		tabla.ajax.reload();
	}
}

function listarArticulos(){
	tablaArticulos=$('#tblarticulos').dataTable({
		"aProcessing": true,//activamos el procedimiento del datatable
		"aServerSide": true,//paginacion y filrado realizados por el server
		"autoWidth": false,
		dom: 'frtip',//definimos los elementos del control de la tabla
		"ajax":
		{
			url:'../ajax/ingreso.php?op=listarArticulos',
			type: "get",
			dataType : "json",
			error:function(e){
				console.log(e.responseText);
			}
		},
		"bDestroy":true,
		"iDisplayLength":10,//paginacion
		"order":[[1,"asc"]],//ordenar por nombre
		"language":{
			"sSearch":"Buscar artículo:",
			"sSearchPlaceholder":"Nombre o código"
		},
		"initComplete":function(){
			$("#tblarticulos, #tblarticulos_wrapper").css("width","100%");
			this.api().columns.adjust();
			estilizarBuscadorCatalogo();
		},
		"drawCallback":function(){
			$("#tblarticulos, #tblarticulos_wrapper").css("width","100%");
			this.api().columns.adjust();
			estilizarBuscadorCatalogo();
		}
	}).DataTable();
}

//funcion para guardaryeditar
function guardaryeditar(e){
     e.preventDefault();//no se activara la accion predeterminada
     var proveedorSeleccionado = ($("#idproveedor").val() || "").toString().trim();
     if (!proveedorSeleccionado) {
     	notifyIngreso("warning", "Selecciona un proveedor antes de guardar.");
     	return;
     }
     var formData=new FormData($("#formulario")[0]);

     $.ajax({
     	url: "../ajax/ingreso.php?op=guardaryeditar",
     	type: "POST",
     	data: formData,
     	contentType: false,
     	processData: false,
     	success: function(datos){
     		var r = null;
     		try {
     			r = JSON.parse(datos);
     		} catch (e) {
     			r = null;
     		}

     		if (r && typeof r.ok !== "undefined") {
     			if (r.ok) {
     				notifyIngreso("success", r.message || "Datos registrados correctamente");
     				mostrarform(false);
     				listar();
     			} else {
     				notifyIngreso("error", r.message || "No se pudo registrar el ingreso.");
     			}
     	} else {
     		if ((datos || "").trim() === "") {
     			notifyIngreso("error", "No se recibio respuesta del servidor.");
     		} else {
     			notifyIngreso("success", datos);
     			mostrarform(false);
     			listar();
     		}
     	}
     	}
     });
}

function mostrar(idingreso){
	$.post("../ajax/ingreso.php?op=mostrar",{idingreso : idingreso},
		function(data,status)
		{
			data=JSON.parse(data);
			mostrarform(true);

			$("#idproveedor").val(data.idproveedor);
			$("#idproveedor").selectpicker('refresh');
			$("#tipo_comprobante").val(data.tipo_comprobante);
			$("#tipo_comprobante").selectpicker('refresh');
			$("#serie_comprobante").val(data.serie_comprobante);
			$("#num_comprobante").val(data.num_comprobante);
			$("#fecha_hora").val(normalizarFechaHoraInput(data.fecha));
			$("#impuesto").val(data.impuesto);
			$("#idingreso").val(data.idingreso);

			//ocultar y mostrar los botones
			$("#btnGuardar").hide();
			$("#btnCancelar").show();
			$("#btnAgregarArt").hide();
		});
	$.post("../ajax/ingreso.php?op=listarDetalle&id="+idingreso,function(r){
		$("#detalles").html(r);
		actualizarContadorItems();
	});

}


//funcion para desactivar
function anular(idingreso){
	bootbox.confirm("¿Esta seguro de desactivar este dato?", function(result){
		if (result) {
			$.post("../ajax/ingreso.php?op=anular", {idingreso : idingreso}, function(e){
				notifyIngreso("warning", e);
				tabla.ajax.reload();
			});
		}
	});
}

//declaramos variables necesarias para trabajar con las compras y sus detalles
var impuesto=18;
var cont=0;
var detalles=0;

$("#btnGuardar").hide();
$("#tipo_comprobante").change(marcarImpuesto);

function marcarImpuesto(){
	aplicarSerieImpuestoIngreso();
}

function aplicarSerieImpuestoIngreso(){
	var tipo = $("#tipo_comprobante").val();
	if (tipo==='Factura') {
		$("#serie_comprobante").val(empresaDefaultsIngreso.serie_factura || "F001");
		$("#impuesto").val((empresaDefaultsIngreso.impuesto_default || impuesto).toFixed(2));
	} else if (tipo==='Ticket') {
		$("#serie_comprobante").val(empresaDefaultsIngreso.serie_ticket || "T001");
		$("#impuesto").val("0");
	} else {
		$("#serie_comprobante").val(empresaDefaultsIngreso.serie_boleta || "B001");
		$("#impuesto").val("0");
	}
	cargarCorrelativoIngreso();
}

function cargarCorrelativoIngreso(){
	var tipo = ($("#tipo_comprobante").val() || "Boleta").trim();
	var serie = ($("#serie_comprobante").val() || "").trim();
	if (!serie) {
		$("#num_comprobante").val("");
		return;
	}
	correlativoRequestIdIngreso++;
	var reqId = correlativoRequestIdIngreso;
	$.get("../ajax/ingreso.php?op=siguienteCorrelativo", {
		tipo_comprobante: tipo,
		serie_comprobante: serie
	}, function(resp){
		if (reqId !== correlativoRequestIdIngreso) {
			return;
		}
		var r = {};
		try {
			r = JSON.parse(resp);
		} catch (e) {
			return;
		}
		if (!r.ok) {
			return;
		}
		$("#serie_comprobante").val(r.serie_comprobante || serie);
		if (!numeroComprobanteManualIngreso || !$.trim($("#num_comprobante").val())) {
			$("#num_comprobante").val(r.numero || "");
			numeroComprobanteManualIngreso = false;
		}
	});
}

function agregarDetalle(idarticulo,articulo,unidad,precio_compra_ref){
	var cantidad=1;
	var precio_compra=(precio_compra_ref && parseFloat(precio_compra_ref) > 0) ? parseFloat(precio_compra_ref) : 1;
	var precio_venta=1;
	var unidadTexto = unidad || "und";
	var articulos = document.getElementsByName("idarticulo[]");
	var cantidades = document.getElementsByName("cantidad[]");

	if (idarticulo!="") {
		for (var i = 0; i < articulos.length; i++) {
			if (parseInt(articulos[i].value, 10) === parseInt(idarticulo, 10)) {
				var nuevaCantidad = normalizarCantidadEntera(parseFloat(cantidades[i].value || 0) + 1, 1);
				cantidades[i].value = nuevaCantidad;
				modificarSubtotales();
				$('#myModal').modal('hide');
				notifyIngreso("info", "El artículo ya estaba agregado. Se incrementó la cantidad.");
				return;
			}
		}

		var subtotal=cantidad*precio_compra;
		var fila='<tr class="filas" id="fila'+cont+'">'+
        '<td><button type="button" class="btn btn-danger" onclick="eliminarDetalle('+cont+')">X</button></td>'+
        '<td><input type="hidden" name="idarticulo[]" value="'+idarticulo+'">'+articulo+'</td>'+
        '<td>'+unidadTexto+'</td>'+
        '<td><input type="number" step="1" min="1" name="cantidad[]" id="cantidad[]" value="'+cantidad+'" oninput="modificarSubtotales()"></td>'+
        '<td><input type="number" step="0.01" min="0.01" name="precio_compra[]" id="precio_compra[]" value="'+precio_compra.toFixed(2)+'" oninput="modificarSubtotales()"></td>'+
        '<td><input type="number" step="0.01" min="0.01" name="precio_venta[]" value="'+precio_venta.toFixed(2)+'"></td>'+
        '<td><span id="subtotal'+cont+'" name="subtotal">'+subtotal.toFixed(2)+'</span></td>'+
        '<td><button type="button" onclick="modificarSubtotales()" class="btn btn-info"><i class="fa fa-refresh"></i></button></td>'+
		'</tr>';
		cont++;
		detalles++;
		$('#detalles').append(fila);
		modificarSubtotales();
		actualizarContadorItems();
		$('#myModal').modal('hide');
		notifyIngreso("success", "Artículo agregado a la compra.");

	}else{
		notifyIngreso("warning", "No se pudo agregar el artículo. Revisa la información del producto.");
	}
}

function modificarSubtotales(){
	var cant=document.getElementsByName("cantidad[]");
	var prec=document.getElementsByName("precio_compra[]");
	var sub=document.getElementsByName("subtotal");

	for (var i = 0; i < cant.length; i++) {
		var inpC=cant[i];
		var inpP=prec[i];
		var inpS=sub[i];

		inpC.value=normalizarCantidadEntera(inpC.value, 1);
		inpS.value=(parseFloat(inpC.value||0)*parseFloat(inpP.value||0)).toFixed(2);
		document.getElementsByName("subtotal")[i].innerHTML=inpS.value;
	}

	calcularTotales();
}

function calcularTotales(){
	var sub = document.getElementsByName("subtotal");
	var total=0.0;

	for (var i = 0; i < sub.length; i++) {
		total += parseFloat(document.getElementsByName("subtotal")[i].value || 0);
	}
	$("#total").html(window.appMoney ? window.appMoney(total,2) : ((window.appCurrencySymbol || "S/") + " " + total.toFixed(2)));
	$("#total_compra").val(total.toFixed(2));
	evaluar();
}

function evaluar(){

	if (detalles>0)
	{
		$("#btnGuardar").show();
	}
	else
	{
		$("#btnGuardar").hide();
		cont=0;
	}
}

function eliminarDetalle(indice){
	$("#fila"+indice).remove();
	calcularTotales();
	detalles=detalles-1;
	actualizarContadorItems();
}

function actualizarContadorItems(){
	var count = document.getElementsByName("idarticulo[]").length;
	$("#comprasItemsSeleccionados").text(count);
}

function estilizarBuscadorCatalogo(){
	var $filtro = $("#tblarticulos_filter");
	if ($filtro.length) {
		$filtro.addClass("catalog-search-wrap");
		$filtro.find("label").addClass("catalog-search-label");
		$filtro.find("input").addClass("catalog-search-input").attr("placeholder","Buscar por nombre o código");
	}
}

init();
