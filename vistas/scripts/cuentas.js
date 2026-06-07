var dtCobrar;
var dtPagar;
var cuentasTabsLoaded = {
  pagar: false
};

function notifyCuentas(type, message) {
  if (typeof appNotify === "function") {
    appNotify(type, message);
  } else {
    alert(message);
  }
}

function fechaHoy() {
  var d = new Date();
  var y = d.getFullYear();
  var m = ("0" + (d.getMonth() + 1)).slice(-2);
  var day = ("0" + d.getDate()).slice(-2);
  return y + "-" + m + "-" + day;
}

function cargarSelects() {
  $.post("../ajax/cuentas.php?op=selectCliente", function (r) {
    $("#cobrar_idcliente").html(r);
    $('#cobrar_idcliente').selectpicker('refresh');
  });

  $.post("../ajax/cuentas.php?op=selectProveedor", function (r) {
    $("#pagar_idproveedor").html(r);
    $('#pagar_idproveedor').selectpicker('refresh');
  });
}

function listarCobrar() {
  dtCobrar = $("#tblcobrar").DataTable({
    aProcessing: true,
    aServerSide: true,
    bDestroy: true,
    autoWidth: false,
    scrollX: true,
    iDisplayLength: 10,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons('Cuentas por Cobrar', true),
    ajax: {
      url: "../ajax/cuentas.php?op=listarCobrar",
      type: "get",
      dataType: "json",
      error: function (e) {
        console.log(e.responseText);
      }
    },
    initComplete: function () {
      fixCuentasTable("#tblcobrar", this.api());
    },
    drawCallback: function () {
      fixCuentasTable("#tblcobrar", this.api());
    }
  });
  fixCuentasTable("#tblcobrar", dtCobrar);
}

function listarPagar() {
  dtPagar = $("#tblpagar").DataTable({
    aProcessing: true,
    aServerSide: true,
    bDestroy: true,
    autoWidth: false,
    scrollX: true,
    iDisplayLength: 10,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons('Cuentas por Pagar', true),
    ajax: {
      url: "../ajax/cuentas.php?op=listarPagar",
      type: "get",
      dataType: "json",
      error: function (e) {
        console.log(e.responseText);
      }
    },
    initComplete: function () {
      fixCuentasTable("#tblpagar", this.api());
    },
    drawCallback: function () {
      fixCuentasTable("#tblpagar", this.api());
    }
  });
  fixCuentasTable("#tblpagar", dtPagar);
}

function fixCuentasTable(selector, api) {
  $(selector).css("width", "100%");
  $(selector + "_wrapper").css("width", "100%");
  if (api && typeof api.columns === "function") {
    api.columns.adjust();
  }
}

function adjustCuentasTables() {
  if (dtCobrar) fixCuentasTable("#tblcobrar", dtCobrar);
  if (dtPagar) fixCuentasTable("#tblpagar", dtPagar);
}

function recargarTablas() {
  if (dtCobrar) dtCobrar.ajax.reload(null, false);
  if (dtPagar) dtPagar.ajax.reload(null, false);
}

function abrirPagoCobrar(idcuenta, saldo) {
  var saldoTxt = window.appMoney ? window.appMoney(parseFloat(saldo || 0), 2) : ("S/ " + parseFloat(saldo || 0).toFixed(2));
  var monto = prompt("Saldo pendiente: " + saldoTxt + "\nIngresa monto a cobrar:");
  if (monto === null) return;

  $.post("../ajax/cuentas.php?op=abonarCobrar", {
    idcuenta: idcuenta,
    monto: monto,
    medio_pago: "EFECTIVO",
    observacion: "Abono desde panel"
  }, function (resp) {
    var ok = (resp || "").toLowerCase().indexOf("correctamente") !== -1;
    notifyCuentas(ok ? "success" : "error", resp);
    if (ok) recargarTablas();
  });
}

function abrirPagoPagar(idcuenta, saldo) {
  var saldoTxt = window.appMoney ? window.appMoney(parseFloat(saldo || 0), 2) : ("S/ " + parseFloat(saldo || 0).toFixed(2));
  var monto = prompt("Saldo pendiente: " + saldoTxt + "\nIngresa monto a pagar:");
  if (monto === null) return;

  $.post("../ajax/cuentas.php?op=abonarPagar", {
    idcuenta: idcuenta,
    monto: monto,
    medio_pago: "EFECTIVO",
    observacion: "Pago desde panel"
  }, function (resp) {
    var ok = (resp || "").toLowerCase().indexOf("correctamente") !== -1;
    notifyCuentas(ok ? "success" : "error", resp);
    if (ok) recargarTablas();
  });
}

function init() {
  $("#cobrar_emision, #cobrar_venc, #pagar_emision, #pagar_venc").val(fechaHoy());

  cargarSelects();
  listarCobrar();
  setTimeout(adjustCuentasTables, 120);

  $("#formCobrar").on("submit", function (e) {
    e.preventDefault();
    $.ajax({
      url: "../ajax/cuentas.php?op=guardarCobrar",
      type: "POST",
      data: $(this).serialize(),
      success: function (resp) {
        var ok = (resp || "").toLowerCase().indexOf("registrada") !== -1;
        notifyCuentas(ok ? "success" : "error", resp);
        if (ok) {
          $("#formCobrar")[0].reset();
          $("#cobrar_emision, #cobrar_venc").val(fechaHoy());
          $('#cobrar_idcliente').selectpicker('refresh');
          recargarTablas();
        }
      }
    });
  });

  $("#formPagar").on("submit", function (e) {
    e.preventDefault();
    $.ajax({
      url: "../ajax/cuentas.php?op=guardarPagar",
      type: "POST",
      data: $(this).serialize(),
      success: function (resp) {
        var ok = (resp || "").toLowerCase().indexOf("registrada") !== -1;
        notifyCuentas(ok ? "success" : "error", resp);
        if (ok) {
          $("#formPagar")[0].reset();
          $("#pagar_emision, #pagar_venc").val(fechaHoy());
          $('#pagar_idproveedor').selectpicker('refresh');
          recargarTablas();
        }
      }
    });
  });

  $('a[data-toggle="tab"]').on("shown.bs.tab", function (e) {
    var target = $(e.target).attr("href");
    if (target === "#cxptab" && !cuentasTabsLoaded.pagar) {
      listarPagar();
      cuentasTabsLoaded.pagar = true;
    }
    setTimeout(adjustCuentasTables, 80);
  });
}

init();
