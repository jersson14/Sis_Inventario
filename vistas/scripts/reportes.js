var tbUtilidad;
var tbTopMas;
var tbTopMenos;
var tbStock;
var tbKardex;
var tbPersonas;

function filtrosReportes() {
  return {
    fecha_inicio: $("#fecha_inicio_reportes").val(),
    fecha_fin: $("#fecha_fin_reportes").val(),
    limite: $("#limite_top").val(),
    tipo: $("#tipo_persona_reporte").val()
  };
}

function listarUtilidad() {
  var f = filtrosReportes();
  tbUtilidad = $("#tblutilidad").dataTable({
    aProcessing: true,
    aServerSide: true,
    dom: "Bfrtip",
    buttons: window.appDataTableButtons("Utilidad por periodo", false),
    ajax: {
      url: "../ajax/consultas.php?op=utilidadperiodo",
      data: { fecha_inicio: f.fecha_inicio, fecha_fin: f.fecha_fin },
      type: "get",
      dataType: "json",
      error: function (e) { console.log(e.responseText); }
    },
    bDestroy: true,
    iDisplayLength: 10,
    autoWidth: false,
    order: [[6, "desc"]]
  }).DataTable();
}

function listarTopProductosMas() {
  var f = filtrosReportes();
  tbTopMas = $("#tbltopmas").dataTable({
    aProcessing: true,
    aServerSide: true,
    dom: "Bfrtip",
    buttons: window.appDataTableButtons("Top productos mas vendidos", false),
    ajax: {
      url: "../ajax/consultas.php?op=topproductos",
      data: { fecha_inicio: f.fecha_inicio, fecha_fin: f.fecha_fin, limite: f.limite, modo: "MAS" },
      type: "get",
      dataType: "json",
      error: function (e) { console.log(e.responseText); }
    },
    bDestroy: true,
    iDisplayLength: 10,
    autoWidth: false,
    order: [[5, "desc"]]
  }).DataTable();
}

function listarTopProductosMenos() {
  var f = filtrosReportes();
  tbTopMenos = $("#tbltopmenos").dataTable({
    aProcessing: true,
    aServerSide: true,
    dom: "Bfrtip",
    buttons: window.appDataTableButtons("Top productos menos vendidos", false),
    ajax: {
      url: "../ajax/consultas.php?op=topproductos",
      data: { fecha_inicio: f.fecha_inicio, fecha_fin: f.fecha_fin, limite: f.limite, modo: "MENOS" },
      type: "get",
      dataType: "json",
      error: function (e) { console.log(e.responseText); }
    },
    bDestroy: true,
    iDisplayLength: 10,
    autoWidth: false,
    order: [[5, "asc"]]
  }).DataTable();
}

function listarStockCritico() {
  tbStock = $("#tblstock").dataTable({
    aProcessing: true,
    aServerSide: true,
    dom: "Bfrtip",
    buttons: window.appDataTableButtons("Stock critico", false),
    ajax: {
      url: "../ajax/consultas.php?op=stockcritico",
      type: "get",
      dataType: "json",
      error: function (e) { console.log(e.responseText); }
    },
    bDestroy: true,
    iDisplayLength: 10,
    autoWidth: false,
    order: [[5, "asc"]]
  }).DataTable();
}

function listarKardexValorizado() {
  var f = filtrosReportes();
  tbKardex = $("#tblkardex").dataTable({
    aProcessing: true,
    aServerSide: true,
    dom: "Bfrtip",
    buttons: window.appDataTableButtons("Kardex valorizado", false),
    ajax: {
      url: "../ajax/consultas.php?op=kardexvalorizado",
      data: { fecha_inicio: f.fecha_inicio, fecha_fin: f.fecha_fin },
      type: "get",
      dataType: "json",
      error: function (e) { console.log(e.responseText); }
    },
    bDestroy: true,
    iDisplayLength: 10,
    autoWidth: false,
    order: [[6, "desc"]]
  }).DataTable();
}

function listarClientesProveedores() {
  var f = filtrosReportes();
  tbPersonas = $("#tblpersonas").dataTable({
    aProcessing: true,
    aServerSide: true,
    dom: "Bfrtip",
    buttons: window.appDataTableButtons("Clientes y proveedores", false),
    ajax: {
      url: "../ajax/consultas.php?op=clientesproveedores",
      data: { fecha_inicio: f.fecha_inicio, fecha_fin: f.fecha_fin, tipo: f.tipo },
      type: "get",
      dataType: "json",
      error: function (e) { console.log(e.responseText); }
    },
    bDestroy: true,
    iDisplayLength: 10,
    autoWidth: false,
    order: [[5, "desc"]]
  }).DataTable();
}

function refrescarReportes() {
  listarUtilidad();
  listarTopProductosMas();
  listarTopProductosMenos();
  listarStockCritico();
  listarKardexValorizado();
  listarClientesProveedores();
}

function init() {
  refrescarReportes();

  $("#btnActualizarReportes").on("click", function () {
    refrescarReportes();
  });

  $("#fecha_inicio_reportes,#fecha_fin_reportes,#limite_top,#tipo_persona_reporte").on("change", function () {
    refrescarReportes();
  });

  $('a[data-toggle="tab"]').on("shown.bs.tab", function () {
    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
  });
}

init();
