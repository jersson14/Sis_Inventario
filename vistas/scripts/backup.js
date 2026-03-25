var dtBackup;

function notifyBackup(type, message) {
  if (typeof appNotify === "function") {
    appNotify(type, message);
  } else {
    alert(message);
  }
}

function listarBackup() {
  dtBackup = $("#tblbackup").DataTable({
    aProcessing: true,
    aServerSide: true,
    bDestroy: true,
    iDisplayLength: 10,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons('Historial de Backups', false),
    ajax: {
      url: "../ajax/backup.php?op=listar",
      type: "get",
      dataType: "json",
      error: function (e) {
        console.log(e.responseText);
      }
    }
  });
}

function init() {
  listarBackup();

  $("#btnGenerarBackup").on("click", function () {
    $.get("../ajax/backup.php?op=generar", function (resp) {
      var r = {};
      try {
        r = JSON.parse(resp);
      } catch (e) {
        notifyBackup("error", "No se pudo generar el backup");
        return;
      }

      if (r.ok) {
        notifyBackup("success", "Backup generado: " + r.filename);
        if (dtBackup) dtBackup.ajax.reload(null, false);
      } else {
        notifyBackup("error", "No se pudo generar el backup");
      }
    });
  });

  $("#formRestaurar").on("submit", function (e) {
    e.preventDefault();

    var confirmar = confirm("Esta accion reemplazara datos actuales. Deseas continuar?");
    if (!confirmar) return;

    var formData = new FormData(this);
    $.ajax({
      url: "../ajax/backup.php?op=restaurar",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (resp) {
        var ok = (resp || "").toLowerCase().indexOf("correctamente") !== -1;
        notifyBackup(ok ? "success" : "error", resp);
        if (dtBackup) dtBackup.ajax.reload(null, false);
      }
    });
  });
}

init();
