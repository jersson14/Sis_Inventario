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
		// Ticket: los que faltan (BD sin migrar) quedan con sus valores por defecto
		$("#ticket_ancho").val(String(d.ticket_ancho || 80));
		$("#ticket_copias").val(String(d.ticket_copias || 1));
		$("#ticket_cabecera").val($("<textarea/>").html(String(d.ticket_cabecera || "")).text());
		$("#ticket_auto_imprimir").prop("checked", typeof d.ticket_auto_imprimir === "undefined" || String(d.ticket_auto_imprimir) === "1");
		$("#ticket_logo").prop("checked", typeof d.ticket_logo === "undefined" || String(d.ticket_logo) === "1");
		$("#arqueo_ciego").prop("checked", typeof d.arqueo_ciego === "undefined" || String(d.arqueo_ciego) === "1");
		$("#ticket_qr").prop("checked", typeof d.ticket_qr === "undefined" || String(d.ticket_qr) === "1");
		$("#ticket_leyenda").val($("<textarea/>").html(String(d.ticket_leyenda || "")).text());
		$("#url_publica").val(d.url_publica || "");
		avisoUrlPublica(d.url_detectada || "");
		$("#color_primario").val(d.color_primario || "#0f766e");
		$("#color_secundario").val(d.color_secundario || "#f59e0b");
		$("#logoactual").val(d.logo || "");
		if (d.logo_url) { $("#logomuestra").attr("src", d.logo_url); }
		else if (d.logo) { $("#logomuestra").attr("src", "../files/empresa/" + d.logo); }
		marcarRubro(d.tipo_negocio || "GENERAL");
		actualizarPreview();
	});
}

// Sin direccion publica el QR usa la actual: con localhost el celular del cliente no la abre
function avisoUrlPublica(detectada){
	var $ayuda = $("#urlPublicaAyuda");
	var local = /^https?:\/\/(localhost|127\.0\.0\.1|\[::1\])(:\d+)?(\/|$)/i.test(detectada);
	if ($("#url_publica").val() || !detectada) {
		$ayuda.removeClass("text-danger").text("La dirección con la que el cliente abre el QR desde su celular.");
		return;
	}
	$ayuda.toggleClass("text-danger", local).text(local
		? "Sin dirección pública el QR apunta a " + detectada + " y el celular del cliente no podrá abrirlo. Escribe tu dominio o la IP de esta PC en la red (ej. http://192.168.1.10/mi_tienda)."
		: "Vacía: el QR usará " + detectada);
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
