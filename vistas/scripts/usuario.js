/* Usuarios / Mi perfil */
var tabla;
var PERFIL_MODE = !!window.appPerfilMode;
var PERFIL_ID = window.appPerfilId || null;
var ES_ADMIN = !!window.appEsAdmin;

function init(){
	if (PERFIL_MODE) {
		$("#formularioregistros").show();
		if (PERFIL_ID) { mostrar(PERFIL_ID); cargarResumenPerfil(); }
	} else {
		mostrarform(false);
		listar();
	}
	$("#formulario").on("submit", function(e){ guardaryeditar(e); });
	$("#formClave").on("submit", cambiarClave);
	$("#imagen").on("change", function(){
		var file = this.files && this.files[0];
		if (!file) { return; }
		if (file.size > 3 * 1024 * 1024) { appNotify("warning", "La imagen supera los 3 MB."); $(this).val(""); return; }
		var reader = new FileReader();
		reader.onload = function(ev){ $("#imagenmuestra").attr("src", ev.target.result).show(); };
		reader.readAsDataURL(file);
	});
	$("#imagenmuestra").hide();
	if (!PERFIL_MODE && window.appQueryParam && window.appQueryParam("nuevo") === "1") { mostrarform(true); }
}

function limpiar(){
	$("#idusuario, #nombre, #num_documento, #direccion, #telefono, #email, #cargo, #login, #clave, #imagenactual").val("");
	$("#idalmacen_usuario").val($("#idalmacen_usuario option[data-principal]").val() || $("#idalmacen_usuario option:first").val());
	$("#tipo_documento").val("DNI");
	$("#imagenmuestra").attr("src", "").hide();
	$("#imagen").val("");
	$("#formTitulo").text("Nuevo usuario");
	$("#claveReq").show();
	$("#claveAyuda").text("Obligatoria para usuarios nuevos.");
	$("#clave").attr("required", true);
}

function cargarPermisos(id){
	$.post("../ajax/usuario.php?op=permisos&id=" + (id || ""), function(r){
		$("#permisos").html(r);
		marcarRolActual();
	});
}

// ---------- Plantillas de rol ----------
// Un clic marca los permisos del puesto; si luego se cambia alguno a mano,
// la tarjeta deja de estar marcada ("rol personalizado").

var PLANTILLAS_ROL = window.appPlantillasRol || {};

function aplicarRol(clave){
	var rol = PLANTILLAS_ROL[clave];
	if (!rol) { return; }
	$("#permisos input[name='permiso[]']").each(function(){
		this.checked = rol.permisos.indexOf(parseInt(this.value, 10)) !== -1;
	});
	// El cargo se sugiere si esta vacio o era el de otra plantilla
	var cargoActual = $.trim($("#cargo").val());
	var esCargoDePlantilla = Object.keys(PLANTILLAS_ROL).some(function(k){ return PLANTILLAS_ROL[k].cargo === cargoActual; });
	if (!cargoActual || esCargoDePlantilla) { $("#cargo").val(rol.cargo); }
	marcarRolActual();
}

function marcarRolActual(){
	var marcados = $("#permisos input[name='permiso[]']:checked").map(function(){ return parseInt(this.value, 10); }).get().sort(function(a, b){ return a - b; });
	var actual = "";
	Object.keys(PLANTILLAS_ROL).forEach(function(k){
		var ids = PLANTILLAS_ROL[k].permisos.slice().sort(function(a, b){ return a - b; });
		if (ids.join(",") === marcados.join(",")) { actual = k; }
	});
	$("#rolPlantillas .rol-card").each(function(){
		var activo = $(this).data("rol") === actual;
		$(this).toggleClass("activo", activo).attr("aria-pressed", activo ? "true" : "false");
	});
}

$(document).on("click", "#rolPlantillas .rol-card", function(){ aplicarRol($(this).data("rol")); });
$(document).on("change", "#permisos input[name='permiso[]']", marcarRolActual);

function mostrarform(flag, uid){
	limpiar();
	if (flag) {
		$("#listadoregistros").hide();
		$("#formularioregistros").show();
		$("#btnGuardar").prop("disabled", false);
		$("#btnagregar").hide();
		if (!uid) { cargarPermisos(""); setTimeout(function(){ $("#nombre").focus(); }, 60); }
	} else {
		$("#listadoregistros").show();
		$("#formularioregistros").hide();
		$("#btnagregar").show();
	}
}

function cancelarform(){
	if (PERFIL_MODE && PERFIL_ID) { mostrar(PERFIL_ID); return; }
	limpiar();
	mostrarform(false);
}

function listar(){
	tabla = $('#tbllistado').dataTable({
		"aProcessing": true, "aServerSide": true, dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Reporte de Usuarios', true),
		"ajax": { url: '../ajax/usuario.php?op=listar', type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } },
		"bDestroy": true, "iDisplayLength": 10, "order": [[1, "asc"]],
		"columnDefs": [{ "orderable": false, "targets": [0, 8] }]
	}).DataTable();
}

function guardaryeditar(e){
	e.preventDefault();
	if (!$.trim($("#nombre").val())) { appNotify("warning", "El nombre es obligatorio."); return; }
	if (!$.trim($("#login").val())) { appNotify("warning", "El nombre de usuario es obligatorio."); return; }
	var clave = $("#clave").val();
	if (!$("#idusuario").val() && !clave) { appNotify("warning", "La contraseña es obligatoria para un usuario nuevo."); return; }
	if (clave && (clave.length < 8 || !/[A-Za-z]/.test(clave) || !/\d/.test(clave))) { appNotify("warning", "La contraseña debe tener al menos 8 caracteres con letras y números."); return; }
	appSetLoading("#btnGuardar", true);
	$.ajax({
		url: "../ajax/usuario.php?op=guardaryeditar", type: "POST",
		data: new FormData($("#formulario")[0]), contentType: false, processData: false,
		success: function(datos){
			appSetLoading("#btnGuardar", false);
			var txt = $.trim(datos || "");
			appNotifyFromResponse(txt);
			if (txt.toLowerCase().indexOf("correctamente") !== -1) {
				if (PERFIL_MODE && PERFIL_ID) { mostrar(PERFIL_ID); cargarResumenPerfil(); setTimeout(function(){ window.location.reload(); }, 1200); }
				else { mostrarform(false); tabla.ajax.reload(null, false); }
			}
		},
		error: function(){ appSetLoading("#btnGuardar", false); }
	});
}

function mostrar(idusuario){
	$.post("../ajax/usuario.php?op=mostrar", { idusuario: idusuario }, function(data){
		data = appParseJson(data, null);
		if (!data) { appNotify("error", "No se pudo cargar el usuario."); return; }
		if (!PERFIL_MODE) { mostrarform(true, data.idusuario); }
		$("#formTitulo").text(PERFIL_MODE ? "Mis datos" : "Editar usuario");
		$("#nombre").val(data.nombre);
		$("#tipo_documento").val(data.tipo_documento || "DNI");
		$("#num_documento").val(data.num_documento);
		$("#direccion").val(data.direccion);
		$("#telefono").val(data.telefono);
		$("#email").val(data.email);
		$("#cargo").val(data.cargo);
		if ($("#idalmacen_usuario").length) {
			var $almU = $("#idalmacen_usuario");
			$almU.val(String(data.idalmacen || ""));
			if (!$almU.val()) { $almU.val($almU.find("option[data-principal]").val() || $almU.find("option:first").val()); }
		}
		$("#login").val(data.login);
		$("#clave").val("").removeAttr("required");
		$("#claveReq").hide();
		$("#claveAyuda").text("Déjala vacía para no cambiarla.");
		if (data.imagen) { $("#imagenmuestra").attr("src", "../files/usuarios/" + data.imagen).show(); }
		$("#imagenactual").val(data.imagen || "");
		$("#idusuario").val(data.idusuario);
		cargarPermisos(idusuario);
	});
}

function cargarResumenPerfil(){
	$.get("../ajax/usuario.php?op=resumenPerfil", function(resp){
		var r = appParseJson(resp, null);
		if (!r) { $("#resumenPerfil").html('<span class="text-soft">No disponible.</span>'); return; }
		var perms = (r.permisos || []).map(function(p){ return '<span class="chip">' + appEscapeHtml(p) + '</span>'; }).join(" ");
		$("#resumenPerfil").html(
			'<p><strong>Usuario:</strong> ' + appEscapeHtml(r.login) + '</p>' +
			'<p><strong>Cargo:</strong> ' + appEscapeHtml(r.cargo || "-") + '</p>' +
			'<p><strong>Último acceso:</strong> ' + appEscapeHtml(r.ultimo_acceso || "-") + '</p>' +
			'<p class="mb-0"><strong>Permisos:</strong><br>' + (perms || '<span class="text-soft">Sin permisos</span>') + '</p>'
		);
	});
}

function cambiarClave(e){
	e.preventDefault();
	var nueva = $("#clave_nueva").val();
	if (nueva !== $("#clave_confirma").val()) { appNotify("warning", "La confirmación no coincide con la nueva contraseña."); return; }
	if (nueva.length < 8 || !/[A-Za-z]/.test(nueva) || !/\d/.test(nueva)) { appNotify("warning", "La nueva contraseña debe tener al menos 8 caracteres con letras y números."); return; }
	appSetLoading("#btnCambiarClave", true);
	$.post("../ajax/usuario.php?op=cambiarClave", $("#formClave").serialize(), function(resp){
		appSetLoading("#btnCambiarClave", false);
		var r = appParseJson(resp, { ok: false, message: resp });
		appNotify(r.ok ? "success" : "error", r.message || "");
		if (r.ok) { $("#formClave")[0].reset(); }
	}).fail(function(){ appSetLoading("#btnCambiarClave", false); });
}

function desactivar(idusuario){
	appConfirm("El usuario no podrá iniciar sesión hasta que lo actives de nuevo. ¿Desactivar?", function(){
		$.post("../ajax/usuario.php?op=desactivar", { idusuario: idusuario }, function(e){ appNotifyFromResponse(e); tabla.ajax.reload(null, false); });
	}, { titulo: "Desactivar usuario", ok: "Sí, desactivar", tipo: "warning" });
}

function activar(idusuario){
	appConfirm("¿Activar este usuario?", function(){
		$.post("../ajax/usuario.php?op=activar", { idusuario: idusuario }, function(e){ appNotifyFromResponse(e); tabla.ajax.reload(null, false); });
	}, { titulo: "Activar usuario", ok: "Sí, activar", tipo: "success" });
}

init();
