/* Auditoría */
var dtAud, dtInt;

function cargarFiltros(){
	$.get("../ajax/auditoria.php?op=filtros", function(resp){
		var r = appParseJson(resp, null);
		if (!r) { return; }
		(r.modulos || []).forEach(function(m){ $("#f_modulo").append('<option value="' + appEscapeHtml(m.modulo) + '">' + appEscapeHtml(m.modulo) + '</option>'); });
		(r.usuarios || []).forEach(function(u){ $("#f_usuario").append('<option value="' + parseInt(u.idusuario, 10) + '">' + appEscapeHtml(u.nombre) + ' (' + appEscapeHtml(u.login) + ')</option>'); });
	});
}

function listar(){
	dtAud = $("#tblauditoria").DataTable({
		aProcessing: true, aServerSide: true, bDestroy: true, iDisplayLength: 25,
		dom: 'Bfrtip',
		buttons: window.appDataTableButtons('Auditoria', false),
		order: [[0, "desc"]],
		ajax: {
			url: "../ajax/auditoria.php?op=listar", type: "get", dataType: "json",
			data: function(d){ d.fecha_inicio = $("#f_inicio").val(); d.fecha_fin = $("#f_fin").val(); d.modulo = $("#f_modulo").val(); d.idusuario = $("#f_usuario").val(); },
			error: function(e){ console.log(e.responseText); }
		}
	});
}

function listarIntentos(){
	dtInt = $("#tblintentos").DataTable({
		aProcessing: true, aServerSide: true, bDestroy: true, iDisplayLength: 25,
		dom: 'frtip', order: [[0, "desc"]],
		ajax: { url: "../ajax/auditoria.php?op=intentos", type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } }
	});
}

function init(){
	cargarFiltros();
	listar();
	$("#btnFiltrar").on("click", function(){ dtAud.ajax.reload(); });
	$('a[data-toggle="tab"]').on("shown.bs.tab", function(e){
		if ($(e.target).attr("href") === "#tabIntentos" && !dtInt) { listarIntentos(); }
	});
}

init();
