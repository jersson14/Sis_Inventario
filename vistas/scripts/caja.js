var dtMovCaja;
var dtHistCaja;

function notifyCaja(type, message) {
  if (typeof appNotify === "function") {
    appNotify(type, message);
  } else {
    alert(message);
  }
}

function cargarEstadoCaja() {
  $.get("../ajax/caja.php?op=estado", function (resp) {
    var r = {};
    try {
      r = JSON.parse(resp);
    } catch (e) {
      r = { abierta: false };
    }

    if (r.abierta) {
      $("#cajaEstado").html(
        "<strong>Caja ABIERTA</strong> | ID: " + r.idcaja +
        " | Apertura: S/ " + r.monto_apertura +
        " | Ingresos: S/ " + r.ingresos +
        " | Egresos: S/ " + r.egresos +
        " | Total sistema: <strong>S/ " + r.sistema + "</strong>"
      );
    } else {
      $("#cajaEstado").html("<strong>Caja cerrada.</strong> Abre una caja para registrar movimientos.");
    }
  });
}

function listarMovimientosCaja() {
  dtMovCaja = $("#tblmovcaja").DataTable({
    aProcessing: true,
    aServerSide: true,
    bDestroy: true,
    iDisplayLength: 10,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons('Movimientos de Caja', false),
    ajax: {
      url: "../ajax/caja.php?op=listarMovimientos",
      type: "get",
      dataType: "json",
      error: function (e) {
        console.log(e.responseText);
      }
    }
  });
}

function listarHistorialCaja() {
  dtHistCaja = $("#tblhistcaja").DataTable({
    aProcessing: true,
    aServerSide: true,
    bDestroy: true,
    iDisplayLength: 10,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons('Historial de Cajas', false),
    ajax: {
      url: "../ajax/caja.php?op=historial",
      type: "get",
      dataType: "json",
      error: function (e) {
        console.log(e.responseText);
      }
    }
  });
}

function refrescarTodoCaja() {
  cargarEstadoCaja();
  if (dtMovCaja) dtMovCaja.ajax.reload(null, false);
  if (dtHistCaja) dtHistCaja.ajax.reload(null, false);
}

function init() {
  cargarEstadoCaja();
  listarMovimientosCaja();
  listarHistorialCaja();

  $("#formAbrirCaja").on("submit", function (e) {
    e.preventDefault();
    $.post("../ajax/caja.php?op=abrir", $(this).serialize(), function (resp) {
      var ok = (resp || "").toLowerCase().indexOf("correctamente") !== -1;
      notifyCaja(ok ? "success" : "warning", resp);
      if (ok) {
        $("#formAbrirCaja")[0].reset();
        refrescarTodoCaja();
      }
    });
  });

  $("#formMovimientoCaja").on("submit", function (e) {
    e.preventDefault();
    $.post("../ajax/caja.php?op=movimiento", $(this).serialize(), function (resp) {
      var ok = (resp || "").toLowerCase().indexOf("registrado") !== -1;
      notifyCaja(ok ? "success" : "warning", resp);
      if (ok) {
        $("#formMovimientoCaja")[0].reset();
        refrescarTodoCaja();
      }
    });
  });

  $("#formCerrarCaja").on("submit", function (e) {
    e.preventDefault();
    $.post("../ajax/caja.php?op=cerrar", $(this).serialize(), function (resp) {
      var ok = (resp || "").toLowerCase().indexOf("correctamente") !== -1;
      notifyCaja(ok ? "success" : "warning", resp);
      if (ok) {
        $("#formCerrarCaja")[0].reset();
        refrescarTodoCaja();
      }
    });
  });
}

init();
