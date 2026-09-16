/* Configuración de empresa */
function actualizarPreview(){
	var p = $("#color_primario").val() || "#0f766e";
	var a = $("#color_secundario").val() || "#f59e0b";
	$("#previewMarca").css("background", "linear-gradient(135deg," + p + "," + p + "cc)");
	$("#previewAccent").css("background", a);
	$("#previewNombre").text($("#nombre_comercial").val() || "Mi Tienda");
}

function cargarEmpresa(){
	$.get("../ajax/empresa.php?op=mostrar", function(resp){
		var d = appParseJson(resp, null);
		if (!d || !d.idconfig) { return; }
		var campos = ["nombre_comercial", "razon_social", "ruc", "direccion", "telefono", "celular", "correo", "web", "serie_boleta", "serie_factura", "serie_ticket", "serie_cotizacion", "impuesto_default", "moneda", "dias_alerta_vencimiento", "mensaje_ticket"];
		campos.forEach(function(c){ if (typeof d[c] !== "undefined" && d[c] !== null) { $("#" + c).val($("<textarea/>").html(String(d[c])).text()); } });
		$("#color_primario").val(d.color_primario || "#0f766e");
		$("#color_secundario").val(d.color_secundario || "#f59e0b");
		$("#logoactual").val(d.logo || "");
		if (d.logo_url) { $("#logomuestra").attr("src", d.logo_url); }
		else if (d.logo) { $("#logomuestra").attr("src", "../files/empresa/" + d.logo); }
		marcarRubro(d.tipo_negocio || "GENERAL");
		actualizarPreview();
	});
}

// Marca visualmente la tarjeta del rubro activo (:has() no llega a navegadores viejos)
function marcarRubro(valor){
	var $radio = $(".rubro-card input[name='tipo_negocio'][value='" + valor + "']");
	if (!$radio.length) { $radio = $(".rubro-card input[name='tipo_negocio'][value='GENERAL']"); }
	$radio.prop("checked", true);
	$(".rubro-card").removeClass("is-activa");
	$radio.closest(".rubro-card").addClass("is-activa");
}

function init(){
	cargarEmpresa();
	$(document).on("change", ".rubro-card input[name='tipo_negocio']", function(){ marcarRubro($(this).val()); });
	$("#color_primario, #color_secundario, #nombre_comercial").on("input change", actualizarPreview);
	$("#logo").on("change", function(){
		var file = this.files && this.files[0];
		if (!file) { return; }
		if (file.size > 3 * 1024 * 1024) { appNotify("warning", "El logo supera los 3 MB."); $(this).val(""); return; }
		var reader = new FileReader();
		reader.onload = function(ev){ $("#logomuestra").attr("src", ev.target.result); };
		reader.readAsDataURL(file);
	});
	$("#empresaForm").on("submit", function(e){
		e.preventDefault();
		if (!$.trim($("#nombre_comercial").val())) { appNotify("warning", "El nombre comercial es obligatorio."); return; }
		appSetLoading("#btnGuardarEmpresa", true);
		$.ajax({
			url: "../ajax/empresa.php?op=guardaryeditar", type: "POST",
			data: new FormData($("#empresaForm")[0]), contentType: false, processData: false,
			success: function(resp){
				appSetLoading("#btnGuardarEmpresa", false);
				var txt = $.trim(resp || "");
				appNotifyFromResponse(txt);
				if (txt.toLowerCase().indexOf("correctamente") !== -1) {
					appNotify("info", "Recargando para aplicar la nueva marca…", 1500);
					setTimeout(function(){ window.location.reload(); }, 1400);
				}
			},
			error: function(){ appSetLoading("#btnGuardarEmpresa", false); }
		});
	});
}

init();
