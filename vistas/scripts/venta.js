var tabla;
var tablaArticulos;
var empresaDefaults = {
	serie_boleta: "B001",
	serie_factura: "F001",
	serie_ticket: "T001",
	impuesto_default: 18,
	moneda: "PEN",
	simbolo_moneda: "S/"
};
var posScanTimer = null;
var posCodeQueue = [];
var posProcessing = false;
var correlativoRequestId = 0;
var clientesCargados = false;
var numeroComprobanteManual = false;

function notifyVenta(type, message){
	if (typeof appNotify === "function") {
		appNotify(type, message);
		return;
	}
	alert(message);
}

function normalizarEnteroNoNegativo(valor){
	var num = parseFloat(valor);
	if (!isFinite(num)) {
		return 0;
	}
	num = Math.round(num);
	if (num < 0) {
		num = 0;
	}
	return num;
}

function normalizarCantidadEntera(valor, minimo){
	var num = normalizarEnteroNoNegativo(valor);
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

   $("#btnFiltrarVenta").on("click", function(){
   	recargarListadoVenta();
   });
   $("#btnLimpiarFiltroVenta").on("click", function(){
   	$("#filtro_venta_inicio").val("");
   	$("#filtro_venta_fin").val("");
   	recargarListadoVenta();
   });

   cargarClientes();

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
   cargarDefaultsEmpresa();

   $("#formClienteRapido").on("submit", function(e){
   	guardarClienteRapido(e);
   });
   $("#modalClienteVenta").on("shown.bs.modal", function(){
   	$("#cli_nombre").focus();
   });
   $("#modalClienteVenta").on("hidden.bs.modal", function(){
   	limpiarFormClienteRapido();
   });
   $("#num_comprobante").on("input", function(){
   	numeroComprobanteManual = true;
   });

   $("#btnBuscarCodigo").on("click", function(){
   	encolarCodigoPOS($("#codigo_rapido").val());
   });
   $("#codigo_rapido").on("keypress", function(e){
   	if (e.which===13) {
   		e.preventDefault();
   		clearTimeout(posScanTimer);
   		encolarCodigoPOS($(this).val());
   	}
   });
   $("#codigo_rapido").on("input", function(){
   	clearTimeout(posScanTimer);
   	var codigoLeido = ($(this).val() || "").trim();
   	if (!codigoLeido || codigoLeido.length < 3) {
   		return;
   	}
   	posScanTimer = setTimeout(function(){
   		encolarCodigoPOS(codigoLeido);
   	}, 220);
   });
   $("#serie_comprobante").on("change blur", function(){
   	cargarCorrelativoComprobante();
   });

   $(document).on("keydown", function(e){
   	if (e.ctrlKey && (e.key === "b" || e.key === "B")) {
   		e.preventDefault();
   		$("#codigo_rapido").focus().select();
   	}
   	if (e.key === "F2") {
   		e.preventDefault();
   		$('#myModal').modal('show');
   	}
   	if (e.key === "F4") {
   		e.preventDefault();
   		if ($("#btnGuardar").is(":visible")) {
   			$("#formulario").trigger("submit");
   		}
   	}
   });

}

function cargarClientes(idPreferido){
	var idActual = (typeof idPreferido !== "undefined" && idPreferido !== null && idPreferido !== "") ? idPreferido : ($("#idcliente").val() || "");
	$.post("../ajax/venta.php?op=selectCliente", function(r){
		$("#idcliente").html(r);
		var existeActual = false;
		if (idActual !== "") {
			$("#idcliente option").each(function(){
				if (String($(this).val()) === String(idActual)) {
					existeActual = true;
					return false;
				}
			});
		}
		var idFinal = existeActual ? String(idActual) : ($("#idcliente option:first").val() || "");
		$("#idcliente").val(idFinal);
		$('#idcliente').selectpicker('refresh');
		clientesCargados = true;
	});
}

function limpiarFormClienteRapido(){
	$("#cli_nombre").val("");
	$("#cli_tipo_documento").val("DNI");
	$("#cli_num_documento").val("");
	$("#cli_direccion").val("");
	$("#cli_telefono").val("");
	$("#cli_email").val("");
	$("#btnGuardarClienteRapido").prop("disabled", false);
}

function guardarClienteRapido(e){
	e.preventDefault();
	var nombre = $.trim($("#cli_nombre").val());
	if (!nombre) {
		notifyVenta("warning", "El nombre del cliente es obligatorio.");
		return;
	}
	$("#btnGuardarClienteRapido").prop("disabled", true);
	$.ajax({
		url: "../ajax/venta.php?op=crearClienteRapido",
		type: "POST",
		data: $("#formClienteRapido").serialize(),
		success: function(resp){
			var r = {};
			try {
				r = JSON.parse(resp);
			} catch (e) {
				r = {ok:false, message:"No se pudo registrar el cliente."};
			}
			if (!r.ok) {
				notifyVenta("error", r.message || "No se pudo registrar el cliente.");
				$("#btnGuardarClienteRapido").prop("disabled", false);
				return;
			}
			notifyVenta("success", r.message || "Cliente registrado correctamente.");
			$("#modalClienteVenta").modal("hide");
			cargarClientes(r.idcliente || "");
		},
		error: function(){
			notifyVenta("error", "Ocurrio un error al registrar el cliente.");
			$("#btnGuardarClienteRapido").prop("disabled", false);
		}
	});
}

function cargarDefaultsEmpresa(){
	$.get("../ajax/empresa.php?op=defaults", function(resp){
		try{
			var r = JSON.parse(resp);
			empresaDefaults.serie_boleta = r.serie_boleta || empresaDefaults.serie_boleta;
			empresaDefaults.serie_factura = r.serie_factura || empresaDefaults.serie_factura;
			empresaDefaults.serie_ticket = r.serie_ticket || empresaDefaults.serie_ticket;
			empresaDefaults.impuesto_default = parseFloat(r.impuesto_default || empresaDefaults.impuesto_default);
			empresaDefaults.moneda = r.moneda || empresaDefaults.moneda;
			empresaDefaults.simbolo_moneda = r.simbolo_moneda || empresaDefaults.simbolo_moneda;
		}catch(e){}
		aplicarSerieImpuesto();
	});
}

//funcion limpiar
function limpiar(){

	$("#idventa").val("");
	if (clientesCargados) {
		var primerCliente = $("#idcliente option:first").val() || "";
		$("#idcliente").val(primerCliente);
		$("#idcliente").selectpicker("refresh");
	} else {
		$("#idcliente").val("");
	}
	$("#cliente").val("");
	$("#serie_comprobante").val("");
	$("#num_comprobante").val("");
	$("#impuesto").val("");
	numeroComprobanteManual = false;

	$("#total_venta").val("");
	$(".filas").remove();
	$("#total").html(window.appMoney ? window.appMoney(0,2) : ((window.appCurrencySymbol || "S/") + " 0.00"));
	actualizarContadorItems();

	$("#fecha_hora").val(fechaHoraActualInput());

	//marcamos el primer tipo_documento
	$("#tipo_comprobante").val("Boleta");
	$("#tipo_comprobante").selectpicker('refresh');
	aplicarSerieImpuesto();

}

//funcion mostrar formulario
function mostrarform(flag){
	limpiar();
	if(flag){
		$("#listadoregistros").hide();
		$("#formularioregistros").show();
		//$("#btnGuardar").prop("disabled",false);
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
		buttons: window.appDataTableButtons('Reporte de Ventas', true),
		"ajax":
		{
			url:'../ajax/venta.php?op=listar',
			type: "get",
			data: function(d){
				d.fecha_inicio = ($("#filtro_venta_inicio").val() || "").trim();
				d.fecha_fin = ($("#filtro_venta_fin").val() || "").trim();
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

function listarArticulos(){
	tablaArticulos=$('#tblarticulos').dataTable({
		"aProcessing": true,//activamos el procedimiento del datatable
		"aServerSide": true,//paginacion y filrado realizados por el server
		"autoWidth": false,
		dom: 'frtip',//definimos los elementos del control de la tabla
		"ajax":
		{
			url:'../ajax/venta.php?op=listarArticulos',
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
			"sSearch":"Buscar artÃ­culo:",
			"sSearchPlaceholder":"Nombre o cÃ³digo"
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

function recargarListadoVenta(){
	var fi = ($("#filtro_venta_inicio").val() || "").trim();
	var ff = ($("#filtro_venta_fin").val() || "").trim();
	if (fi && ff && fi > ff) {
		notifyVenta("warning", "La fecha 'Desde' no puede ser mayor que 'Hasta'.");
		return;
	}
	if (tabla) {
		tabla.ajax.reload();
	}
}
//funcion para guardaryeditar
function guardaryeditar(e){
     e.preventDefault();//no se activara la accion predeterminada 
     var clienteSeleccionado = ($("#idcliente").val() || "").toString().trim();
     if (!clienteSeleccionado) {
     	notifyVenta("warning", "Selecciona un cliente antes de guardar.");
     	return;
     }
     if (!validarStockDetalleAntesGuardar()) {
     	return;
     }
     //$("#btnGuardar").prop("disabled",true);
     var formData=new FormData($("#formulario")[0]);

     $.ajax({
     	url: "../ajax/venta.php?op=guardaryeditar",
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
     				notifyVenta("success", r.message || "Datos registrados correctamente");
     				if (r.alertas && r.alertas.length > 0) {
     					var texto = "Alerta de stock bajo: " + r.alertas.map(function(a){
     						return (a.nombre || "Articulo") + " (" + normalizarEnteroNoNegativo(a.stock) + ")";
     					}).join(", ");
     					notifyVenta("warning", texto);
     				}
     				mostrarform(false);
     				listar();
     			} else {
     				notifyVenta("error", r.message || "No se pudo registrar la venta.");
     			}
     		} else {
     			notifyVenta("success", datos);
     			mostrarform(false);
     			listar();
     		}
    	}
     });
}

function mostrar(idventa){
	$.post("../ajax/venta.php?op=mostrar",{idventa : idventa},
		function(data,status)
		{
			data=JSON.parse(data);
			mostrarform(true);

			$("#idcliente").val(data.idcliente);
			$("#idcliente").selectpicker('refresh');
			$("#tipo_comprobante").val(data.tipo_comprobante);
			$("#tipo_comprobante").selectpicker('refresh');
			$("#serie_comprobante").val(data.serie_comprobante);
			$("#num_comprobante").val(data.num_comprobante);
			$("#fecha_hora").val(normalizarFechaHoraInput(data.fecha));
			$("#impuesto").val(data.impuesto);
			$("#idventa").val(data.idventa);
			
			//ocultar y mostrar los botones
			$("#btnGuardar").hide();
			$("#btnCancelar").show();
			$("#btnAgregarArt").hide();
		});
	$.post("../ajax/venta.php?op=listarDetalle&id="+idventa,function(r){
		$("#detalles").html(r);
	});

}


//funcion para desactivar
function anular(idventa){
	bootbox.confirm("Â¿Esta seguro de desactivar este dato?", function(result){
		if (result) {
			$.post("../ajax/venta.php?op=anular", {idventa : idventa}, function(e){
				notifyVenta("warning", e);
				tabla.ajax.reload();
			});
		}
	})
}

//declaramos variables necesarias para trabajar con las compras y sus detalles
var impuesto=18;
var cont=0;
var detalles=0;

$("#btnGuardar").hide();
$("#tipo_comprobante").change(marcarImpuesto);

function marcarImpuesto(){
	aplicarSerieImpuesto();
}

function aplicarSerieImpuesto(){
	var tipo = $("#tipo_comprobante").val();
	if (tipo==='Factura') {
		$("#serie_comprobante").val(empresaDefaults.serie_factura || "F001");
		$("#impuesto").val((empresaDefaults.impuesto_default || impuesto).toFixed(2));
	} else if (tipo==='Ticket') {
		$("#serie_comprobante").val(empresaDefaults.serie_ticket || "T001");
		$("#impuesto").val("0");
	} else {
		$("#serie_comprobante").val(empresaDefaults.serie_boleta || "B001");
		$("#impuesto").val("0");
	}
	cargarCorrelativoComprobante();
}

function cargarCorrelativoComprobante(){
	var tipo = ($("#tipo_comprobante").val() || "Boleta").trim();
	var serie = ($("#serie_comprobante").val() || "").trim();
	if (!serie) {
		$("#num_comprobante").val("");
		return;
	}
	correlativoRequestId++;
	var reqId = correlativoRequestId;
	$.get("../ajax/venta.php?op=siguienteCorrelativo", {
		tipo_comprobante: tipo,
		serie_comprobante: serie
	}, function(resp){
		if (reqId !== correlativoRequestId) {
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
		if (!numeroComprobanteManual || !$.trim($("#num_comprobante").val())) {
			$("#num_comprobante").val(r.numero || "");
			numeroComprobanteManual = false;
		}
	});
}

function agregarDetalle(idarticulo,articulo,precio_venta,unidad,stockDisponible){
	var cantidad=1;
	var descuento=0;
	var unidadTexto = unidad || "und";
	var stockDisponibleNum = normalizarEnteroNoNegativo(stockDisponible || 0);
	var articulos = document.getElementsByName("idarticulo[]");
	var cantidades = document.getElementsByName("cantidad[]");
	var stocksDisponibles = document.getElementsByName("stock_disponible[]");
	if (stockDisponibleNum <= 0) {
		notifyVenta("warning", "Este articulo no tiene stock disponible.");
		return;
	}
	if (idarticulo!="") {
		for (var i = 0; i < articulos.length; i++) {
			if (parseInt(articulos[i].value, 10) === parseInt(idarticulo, 10)) {
				var stockFila = normalizarEnteroNoNegativo((stocksDisponibles[i] && stocksDisponibles[i].value) ? stocksDisponibles[i].value : stockDisponibleNum);
				var nuevaCantidadNum = normalizarCantidadEntera(parseFloat(cantidades[i].value || 0) + 1, 1);
				if (nuevaCantidadNum > stockFila) {
					notifyVenta("warning", "No puedes vender mas de " + stockFila + " " + unidadTexto + " para este articulo.");
					return;
				}
				cantidades[i].value = nuevaCantidadNum;
				modificarSubtotales();
				$('#myModal').modal('hide');
				notifyVenta("info", "El articulo ya estaba agregado. Se incremento la cantidad.");
				return;
			}
		}
		var subtotal=cantidad*precio_venta;
		var fila='<tr class="filas" id="fila'+cont+'">'+
        '<td><button type="button" class="btn btn-danger" onclick="eliminarDetalle('+cont+')">X</button></td>'+
        '<td><input type="hidden" name="idarticulo[]" value="'+idarticulo+'"><input type="hidden" name="stock_disponible[]" value="'+stockDisponibleNum+'">'+articulo+'</td>'+
        '<td>'+unidadTexto+'</td>'+
        '<td><input type="number" step="1" min="1" max="'+stockDisponibleNum+'" name="cantidad[]" id="cantidad[]" value="'+cantidad+'" oninput="modificarSubtotales()"></td>'+
        '<td><input type="number" step="0.01" min="0.01" name="precio_venta[]" id="precio_venta[]" value="'+precio_venta+'" oninput="modificarSubtotales()"></td>'+
        '<td><input type="number" step="0.01" min="0.00" name="descuento[]" value="'+descuento+'" oninput="modificarSubtotales()"></td>'+
        '<td><span id="subtotal'+cont+'" name="subtotal">'+subtotal+'</span></td>'+
        '<td><button type="button" onclick="modificarSubtotales()" class="btn btn-info"><i class="fa fa-refresh"></i></button></td>'+
		'</tr>';
		cont++;
		detalles++;
		$('#detalles').append(fila);
		modificarSubtotales();
		actualizarContadorItems();
		$('#myModal').modal('hide');
		notifyVenta("success", "Articulo agregado a la venta.");
	}else{
		notifyVenta("warning", "No se pudo agregar el articulo. Revisa la informacion del producto.");
	}
}
function modificarSubtotales(){
	var cant=document.getElementsByName("cantidad[]");
	var prev=document.getElementsByName("precio_venta[]");
	var desc=document.getElementsByName("descuento[]");
	var sub=document.getElementsByName("subtotal");
	var stockDisp=document.getElementsByName("stock_disponible[]");
	var huboAjusteStock=false;
	for (var i = 0; i < cant.length; i++) {
		var inpV=cant[i];
		var inpP=prev[i];
		var inpS=sub[i];
		var des=desc[i];
		var maxStock = normalizarEnteroNoNegativo((stockDisp[i] && stockDisp[i].value) ? stockDisp[i].value : 0);
		var cantidadActual = normalizarCantidadEntera(inpV.value, 1);
		if (cantidadActual <= 0) {
			cantidadActual = 1;
		}
		if (maxStock > 0 && cantidadActual > maxStock) {
			cantidadActual = maxStock;
			huboAjusteStock = true;
		}
		inpV.value = cantidadActual;
		inpS.value=((parseFloat(inpV.value||0)*parseFloat(inpP.value||0))-parseFloat(des.value||0)).toFixed(2);
		document.getElementsByName("subtotal")[i].innerHTML=inpS.value;
	}
	if (huboAjusteStock) {
		notifyVenta("warning", "Se ajusto la cantidad al stock disponible.");
	}
	calcularTotales();
}
function validarStockDetalleAntesGuardar(){
	var cant=document.getElementsByName("cantidad[]");
	var stockDisp=document.getElementsByName("stock_disponible[]");
	for (var i = 0; i < cant.length; i++) {
		var cantidad = normalizarCantidadEntera(cant[i].value, 1);
		var stock = normalizarEnteroNoNegativo((stockDisp[i] && stockDisp[i].value) ? stockDisp[i].value : 0);
		cant[i].value = cantidad;
		if (isNaN(cantidad) || cantidad <= 0) {
			notifyVenta("warning", "Hay un articulo con cantidad invalida. Corrige antes de guardar.");
			return false;
		}
		if (isNaN(stock) || stock < 0) {
			stock = 0;
		}
		if (cantidad > stock) {
			notifyVenta("warning", "Hay un articulo con cantidad mayor al stock disponible. Corrige antes de guardar.");
			return false;
		}
	}
	return true;
}
function calcularTotales(){
	var sub = document.getElementsByName("subtotal");
	var total=0.0;

	for (var i = 0; i < sub.length; i++) {
		total += parseFloat(document.getElementsByName("subtotal")[i].value || 0);
	}
	$("#total").html(window.appMoney ? window.appMoney(total,2) : ((window.appCurrencySymbol || "S/") + " " + total.toFixed(2)));
	$("#total_venta").val(total.toFixed(2));
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
	$("#ventasItemsSeleccionados").text(count);
}

function estilizarBuscadorCatalogo(){
	var $filtro = $("#tblarticulos_filter");
	if ($filtro.length) {
		$filtro.addClass("catalog-search-wrap");
		$filtro.find("label").addClass("catalog-search-label");
		$filtro.find("input").addClass("catalog-search-input").attr("placeholder","Buscar por nombre o cÃ³digo");
	}
}

function encolarCodigoPOS(codigo){
	var limpio = (codigo || "").trim();
	if (!limpio) {
		notifyVenta("warning", "Ingresa o escanea un codigo de producto.");
		return;
	}
	clearTimeout(posScanTimer);
	posCodeQueue.push(limpio);
	$("#codigo_rapido").val("");
	procesarColaPOS();
}

function procesarColaPOS(){
	if (posProcessing || posCodeQueue.length === 0) {
		return;
	}
	posProcessing = true;
	var codigo = posCodeQueue.shift();
	buscarCodigoRapido(codigo, function(){
		posProcessing = false;
		procesarColaPOS();
	});
}

function buscarCodigoRapido(codigoForzado, callback){
	var codigo = (codigoForzado || $("#codigo_rapido").val() || "").trim();
	if (!codigo) {
		notifyVenta("warning", "Ingresa o escanea un codigo de producto.");
		if (typeof callback === "function") {
			callback();
		}
		return;
	}

	$.post("../ajax/venta.php?op=buscarArticuloCodigo", {codigo: codigo}, function(resp){
		var r = {};
		try {
			r = JSON.parse(resp);
		} catch (e) {
			notifyVenta("error", "No se pudo buscar el articulo por codigo.");
			if (typeof callback === "function") {
				callback();
			}
			return;
		}

		if (!r.ok) {
			notifyVenta("warning", r.message || "No se encontro el articulo");
			if (typeof callback === "function") {
				callback();
			}
			return;
		}

		agregarDetalle(r.idarticulo, r.nombre, r.precio_venta || 0, r.unidad || "und", r.stock || 0);
		$("#codigo_rapido").focus();
		if (typeof callback === "function") {
			callback();
		}
	});
}

init();
