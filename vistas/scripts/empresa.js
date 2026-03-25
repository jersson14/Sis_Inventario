var empresaForm;

function notifyEmpresa(type, message) {
  if (typeof appNotify === "function") {
    appNotify(type, message);
  } else {
    alert(message);
  }
}

function cargarEmpresa() {
  $.get("../ajax/empresa.php?op=mostrar", function (data) {
    var r = {};
    try {
      r = data ? JSON.parse(data) : {};
    } catch (e) {
      r = {};
    }

    $("#nombre_comercial").val(r.nombre_comercial || "");
    $("#razon_social").val(r.razon_social || "");
    $("#ruc").val(r.ruc || "");
    $("#direccion").val(r.direccion || "");
    $("#telefono").val(r.telefono || "");
    $("#celular").val(r.celular || "");
    $("#correo").val(r.correo || "");
    $("#web").val(r.web || "");
    $("#color_primario").val(r.color_primario || "#0f766e");
    $("#color_secundario").val(r.color_secundario || "#f59e0b");
    $("#serie_boleta").val(r.serie_boleta || "B001");
    $("#serie_factura").val(r.serie_factura || "F001");
    $("#serie_ticket").val(r.serie_ticket || "T001");
    $("#impuesto_default").val(r.impuesto_default || "18.00");
    $("#moneda").val(r.moneda || "PEN");
    $("#logoactual").val(r.logo || "");

    if (r.logo) {
      $("#logomuestra").attr("src", "../files/empresa/" + r.logo).show();
    } else {
      $("#logomuestra").attr("src", "../vistas/logo1.jpeg").show();
    }
  });
}

function init() {
  empresaForm = $("#empresaForm");
  cargarEmpresa();

  empresaForm.on("submit", function (e) {
    e.preventDefault();
    var formData = new FormData(empresaForm[0]);

    $.ajax({
      url: "../ajax/empresa.php?op=guardaryeditar",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      success: function (resp) {
        var lower = (resp || "").toLowerCase();
        var type = (lower.indexOf("no se pudo") !== -1 || lower.indexOf("obligatorio") !== -1 || lower.indexOf("permiso") !== -1) ? "error" : "success";
        notifyEmpresa(type, resp);
        if (type === "success") {
          cargarEmpresa();
        }
      }
    });
  });
}

init();
