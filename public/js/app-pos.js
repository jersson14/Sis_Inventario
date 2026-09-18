/*
 * Utilidades compartidas por el punto de venta y la pantalla de compras:
 *  - modo caja: oculta menu y cabecera para usar toda la pantalla
 *  - borrador local: autoguardado del documento en curso (por usuario y PC)
 *  - impresion del ticket en un iframe oculto, sin abrir otra pestana
 *  - selector de medio de pago con botones
 */
(function ($) {
  "use strict";

  function noop() {}

  // ---------- Modo caja / pantalla completa ----------

  window.appModoCaja = function (activo) {
    $("body").toggleClass("modo-caja", !!activo);
    if (!activo && document.fullscreenElement && document.exitFullscreen) {
      document.exitFullscreen().catch(noop);
    }
    // DataTables y selectpicker recalculan anchos al cambiar el layout
    setTimeout(function () { $(window).trigger("resize"); }, 60);
  };

  window.appAlternarPantallaCompleta = function () {
    var el = document.documentElement;
    if (!document.fullscreenElement) {
      if (el.requestFullscreen) { el.requestFullscreen().catch(noop); }
    } else if (document.exitFullscreen) {
      document.exitFullscreen().catch(noop);
    }
  };

  $(document).on("fullscreenchange", function () {
    $(".btn-pantalla-completa").each(function () {
      var activo = !!document.fullscreenElement;
      $(this).find("i").toggleClass("fa-expand", !activo).toggleClass("fa-compress", activo);
      $(this).attr("title", activo ? "Salir de pantalla completa (F11)" : "Pantalla completa (F11)");
    });
  });

  // ---------- Borrador local ----------
  // localStorage puede no existir o fallar (modo privado, almacenamiento lleno):
  // el borrador es una ayuda, nunca debe romper la pantalla.

  window.appBorrador = function (nombre) {
    var clave = "mitienda:borrador:" + nombre + ":" + ((window.appUser && window.appUser.id) || 0);
    return {
      leer: function () {
        try {
          var raw = window.localStorage.getItem(clave);
          return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
      },
      guardar: function (datos) {
        try {
          datos.guardado = new Date().toISOString();
          window.localStorage.setItem(clave, JSON.stringify(datos));
          return true;
        } catch (e) { return false; }
      },
      borrar: function () {
        try { window.localStorage.removeItem(clave); } catch (e) { noop(); }
      }
    };
  };

  window.appHoraCorta = function (iso) {
    var d = iso ? new Date(iso) : new Date();
    if (isNaN(d.getTime())) { return ""; }
    return ("0" + d.getHours()).slice(-2) + ":" + ("0" + d.getMinutes()).slice(-2);
  };

  // Preferencias de esta PC (ej. imprimir ticket al cobrar)
  window.appPreferenciaLocal = function (nombre, valor) {
    var clave = "mitienda:pref:" + nombre;
    try {
      if (typeof valor === "undefined") { return window.localStorage.getItem(clave); }
      window.localStorage.setItem(clave, String(valor));
    } catch (e) { noop(); }
    return null;
  };

  // ---------- Ticket ----------
  // El ticket se carga en un iframe oculto y se imprime desde alli: la
  // ticketera recibe solo el ticket y el cajero no pierde la pantalla.

  window.appImprimirTicket = function (idventa) {
    $("#appTicketFrame").remove();
    var iframe = $('<iframe id="appTicketFrame" title="Ticket" aria-hidden="true" tabindex="-1"></iframe>').css({
      position: "fixed", right: 0, bottom: 0, width: "1px", height: "1px", border: 0, opacity: 0
    });
    $("body").append(iframe);
    iframe.attr("src", "../reportes/exTicket.php?auto=1&id=" + encodeURIComponent(idventa));
  };

  // ---------- Medio de pago con botones ----------
  // <div class="medio-grid" data-target="#medio_pago"> con <button data-medio="EFECTIVO">

  window.appMedioPagoTexto = {
    EFECTIVO: "Efectivo", DEPOSITO: "Depósito en cuenta", TRANSFERENCIA: "Transferencia",
    YAPE: "Yape", PLIN: "Plin", TARJETA: "Tarjeta", OTRO: "Otro", MIXTO: "Mixto", CREDITO: "Crédito", NOTA_CREDITO: "Nota de crédito"
  };

  window.appFijarMedioPago = function ($grid, medio) {
    $grid.find("[data-medio]").removeClass("active").attr("aria-pressed", "false")
      .filter("[data-medio='" + medio + "']").addClass("active").attr("aria-pressed", "true");
    $($grid.data("target")).val(medio).trigger("change");
  };

  $(document).on("click", ".medio-grid [data-medio]", function () {
    window.appFijarMedioPago($(this).closest(".medio-grid"), $(this).data("medio"));
  });
})(jQuery);
