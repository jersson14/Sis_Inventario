/* Backup y restauración */
var dtBackup;

function listarBackups(){
	dtBackup = $("#tblbackup").DataTable({
		aProcessing: true, aServerSide: true, bDestroy: true, iDisplayLength: 10,
		dom: 'frtip', order: [[5, "desc"]],
		columnDefs: [{ orderable: false, targets: [0] }],
		ajax: { url: "../ajax/backup.php?op=listar", type: "get", dataType: "json", error: function(e){ console.log(e.responseText); } }
	});
}

function eliminarBackup(archivo){
	appConfirm("Se eliminará el archivo <strong>" + appEscapeHtml(archivo) + "</strong> del servidor. ¿Continuar?", function(){
		$.post("../ajax/backup.php?op=eliminar", { archivo: archivo }, function(resp){
			appNotifyFromResponse(resp);
			dtBackup.ajax.reload(null, false);
		});
	}, { titulo: "Eliminar copia", ok: "Sí, eliminar", tipo: "danger" });
}

function init(){
	listarBackups();

	$("#btnGenerarBackup").on("click", function(){
		appSetLoading("#btnGenerarBackup", true);
		$.get("../ajax/backup.php?op=generar", function(resp){
			appSetLoading("#btnGenerarBackup", false);
			var r = appParseJson(resp, null);
			if (r && r.ok) {
				appNotify("success", "Copia generada: " + (r.filename || "") + " (" + Math.round((r.size || 0) / 1024) + " KB)");
				dtBackup.ajax.reload(null, false);
			} else {
				appNotify("error", (r && r.message) || "No se pudo generar la copia.");
			}
		}).fail(function(){ appSetLoading("#btnGenerarBackup", false); });
	});

	$("#formRestaurar").on("submit", function(e){
		e.preventDefault();
		var file = $("#archivo_sql")[0].files[0];
		if (!file) { appNotify("warning", "Selecciona un archivo .sql."); return; }
		if (!/\.sql$/i.test(file.name)) { appNotify("warning", "El archivo debe tener extensión .sql."); return; }
		appConfirm("Vas a reemplazar TODA la base de datos con <strong>" + appEscapeHtml(file.name) + "</strong>. Se creará una copia previa automáticamente. ¿Continuar?", function(){
			appSetLoading("#btnRestaurar", true);
			$.ajax({
				url: "../ajax/backup.php?op=restaurar", type: "POST",
				data: new FormData($("#formRestaurar")[0]), contentType: false, processData: false,
				success: function(resp){
					appSetLoading("#btnRestaurar", false);
					var r = appParseJson(resp, { ok: false, message: resp });
					appNotify(r.ok ? "success" : "error", r.message || "", 7000);
					if (r.ok) {
						if (r.backup_previo) { appNotify("info", "Copia previa guardada: " + r.backup_previo, 7000); }
						$("#formRestaurar")[0].reset();
						dtBackup.ajax.reload(null, false);
					}
				},
				error: function(){ appSetLoading("#btnRestaurar", false); }
			});
		}, { titulo: "Restaurar base de datos", ok: "Sí, restaurar", tipo: "danger" });
	});
}

init();
