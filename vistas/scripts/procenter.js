var dtAlertaStock;
var dtSinMov;
var dtTop;
var dtUtil;
var dtSug;
var dtKardex;
var loadedTabs = {
  alertas: false,
  utilidad: false,
  sugerencias: false
};

function notifyPro(type, msg) {
  if (typeof appNotify === "function") {
    appNotify(type, msg);
  } else {
    alert(msg);
  }
}

function hoyISO() {
  var d = new Date();
  var y = d.getFullYear();
  var m = ("0" + (d.getMonth() + 1)).slice(-2);
  var da = ("0" + d.getDate()).slice(-2);
  return y + "-" + m + "-" + da;
}

function haceDiasISO(n) {
  var d = new Date();
  d.setDate(d.getDate() - n);
  var y = d.getFullYear();
  var m = ("0" + (d.getMonth() + 1)).slice(-2);
  var da = ("0" + d.getDate()).slice(-2);
  return y + "-" + m + "-" + da;
}

function cargarSelectArticulos() {
  $.post("../ajax/procenter.php?op=selectArticulo", function (r) {
    $("#kardex_articulo").html(r);
    $('#kardex_articulo').selectpicker('refresh');
  });
}

function construirKardex(data) {
  if (dtKardex) {
    dtKardex.destroy();
  }
  var body = "";
  for (var i = 0; i < data.movimientos.length; i++) {
    var r = data.movimientos[i];
    body += "<tr>" +
      "<td>" + r.fecha + "</td>" +
      "<td><span class='label " + (r.tipo === "INGRESO" ? "bg-aqua" : "bg-green") + "'>" + r.tipo + "</span></td>" +
      "<td>" + r.documento + "</td>" +
      "<td>" + r.tercero + "</td>" +
      "<td>" + r.entrada + "</td>" +
      "<td>" + r.salida + "</td>" +
      "<td>" + r.saldo + "</td>" +
      "<td>S/ " + r.costo + "</td>" +
      "<td>S/ " + r.precio_ref + "</td>" +
      "</tr>";
  }
  $("#tblkardex tbody").html(body);
  dtKardex = $("#tblkardex").DataTable({
    bDestroy: true,
    autoWidth: false,
    scrollX: true,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons('Kardex de Inventario', false),
    iDisplayLength: 10,
    order: [[0, "asc"]],
    initComplete: function () {
      fixDataTableLayout("#tblkardex", this.api());
    },
    drawCallback: function () {
      fixDataTableLayout("#tblkardex", this.api());
    }
  });
  fixDataTableLayout("#tblkardex", dtKardex);

  $("#kardexResumen").html(
    "<strong>" + data.articulo + "</strong> (" + data.codigo + ") | Unidad: " + data.unidad +
    " | Saldo inicial: <strong>" + data.saldo_inicial + "</strong> | Stock actual: <strong>" + data.stock_actual +
    "</strong> | Stock minimo: <strong>" + data.stock_minimo + "</strong>"
  );
}

function fixDataTableLayout(selector, api) {
  $(selector).css("width", "100%");
  $(selector + "_wrapper").css("width", "100%");
  if (api && typeof api.columns === "function") {
    api.columns.adjust();
  }
}

function adjustVisibleTables() {
  if (dtKardex) fixDataTableLayout("#tblkardex", dtKardex);
  if (dtAlertaStock) fixDataTableLayout("#tblalertstock", dtAlertaStock);
  if (dtSinMov) fixDataTableLayout("#tblsinmov", dtSinMov);
  if (dtTop) fixDataTableLayout("#tbltopvend", dtTop);
  if (dtUtil) fixDataTableLayout("#tblutilidad", dtUtil);
  if (dtSug) fixDataTableLayout("#tblsugerencias", dtSug);
}

function cargarKardex() {
  var idarticulo = $("#kardex_articulo").val();
  if (!idarticulo) {
    notifyPro("warning", "Selecciona un articulo para generar kardex.");
    return;
  }
  var desde = $("#kardex_desde").val();
  var hasta = $("#kardex_hasta").val();

  $.get("../ajax/procenter.php", {
    op: "kardex",
    idarticulo: idarticulo,
    desde: desde,
    hasta: hasta
  }, function (resp) {
    var r;
    try {
      r = JSON.parse(resp);
    } catch (e) {
      notifyPro("error", "No se pudo generar el kardex.");
      return;
    }

    if (!r.ok) {
      notifyPro("warning", r.message || "No se pudo generar kardex.");
      return;
    }

    construirKardex(r);
  });
}

function tablaAjax(selector, url, buttonsTitle) {
  return $(selector).DataTable({
    aProcessing: true,
    aServerSide: true,
    bDestroy: true,
    autoWidth: false,
    scrollX: true,
    iDisplayLength: 10,
    dom: 'Bfrtip',
    buttons: window.appDataTableButtons(buttonsTitle, false),
    ajax: {
      url: url,
      type: 'get',
      dataType: 'json',
      error: function (e) {
        console.log(e.responseText);
      }
    },
    initComplete: function () {
      fixDataTableLayout(selector, this.api());
    },
    drawCallback: function () {
      fixDataTableLayout(selector, this.api());
    }
  });
}

function cargarAlertaStock() {
  if (dtAlertaStock) {
    dtAlertaStock.ajax.reload();
    return;
  }
  dtAlertaStock = tablaAjax("#tblalertstock", "../ajax/procenter.php?op=alertaStock", "Alerta Stock Minimo");
}

function cargarSinMov() {
  var dias = $("#alerta_dias").val() || 30;
  var url = "../ajax/procenter.php?op=alertaSinMov&dias=" + dias;
  if (dtSinMov) {
    dtSinMov.ajax.url(url).load();
    return;
  }
  dtSinMov = tablaAjax("#tblsinmov", url, "Articulos Sin Movimiento");
}

function cargarTopVendidos() {
  var desde = $("#top_desde").val();
  var hasta = $("#top_hasta").val();
  var url = "../ajax/procenter.php?op=topVendidos&desde=" + encodeURIComponent(desde) + "&hasta=" + encodeURIComponent(hasta);
  if (dtTop) {
    dtTop.ajax.url(url).load();
    return;
  }
  dtTop = tablaAjax("#tbltopvend", url, "Top Vendidos");
}

function cargarUtilidad() {
  var desde = $("#util_desde").val();
  var hasta = $("#util_hasta").val();
  var agrupar = $("#util_agrupar").val();
  var url = "../ajax/procenter.php?op=utilidad&desde=" + encodeURIComponent(desde) + "&hasta=" + encodeURIComponent(hasta) + "&agrupar=" + encodeURIComponent(agrupar);
  if (dtUtil) {
    dtUtil.ajax.url(url).load();
    return;
  }
  dtUtil = tablaAjax("#tblutilidad", url, "Reporte de Utilidad");
}

function cargarSugerencias() {
  var da = $("#sug_dias_analisis").val() || 30;
  var dc = $("#sug_dias_cobertura").val() || 15;
  var url = "../ajax/procenter.php?op=sugerencias&dias_analisis=" + da + "&dias_cobertura=" + dc;

  if (dtSug) {
    dtSug.ajax.url(url).load();
    return;
  }
  dtSug = tablaAjax("#tblsugerencias", url, "Compras Sugeridas");
}

function init() {
  cargarSelectArticulos();
  $("#kardex_desde").val(haceDiasISO(30));
  $("#kardex_hasta").val(hoyISO());

  $("#top_desde").val(haceDiasISO(30));
  $("#top_hasta").val(hoyISO());
  $("#util_desde").val(haceDiasISO(30));
  $("#util_hasta").val(hoyISO());

  // Cargar solo pestaña inicial para evitar tablas encogidas en tabs ocultos
  setTimeout(adjustVisibleTables, 120);

  $("#btnKardexBuscar").on("click", cargarKardex);
  $("#btnAlertaSinMov").on("click", cargarSinMov);
  $("#btnTopVendidos").on("click", cargarTopVendidos);
  $("#btnUtilidad").on("click", cargarUtilidad);
  $("#btnSugerencias").on("click", cargarSugerencias);

  $('a[data-toggle="tab"]').on("shown.bs.tab", function (e) {
    var target = $(e.target).attr("href");

    if (target === "#tabAlertas" && !loadedTabs.alertas) {
      cargarAlertaStock();
      cargarSinMov();
      cargarTopVendidos();
      loadedTabs.alertas = true;
    }
    if (target === "#tabUtilidad" && !loadedTabs.utilidad) {
      cargarUtilidad();
      loadedTabs.utilidad = true;
    }
    if (target === "#tabSugerencias" && !loadedTabs.sugerencias) {
      cargarSugerencias();
      loadedTabs.sugerencias = true;
    }

    setTimeout(adjustVisibleTables, 80);
  });
}

init();
