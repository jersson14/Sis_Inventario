/**
 * app-core.js — comportamiento global del panel:
 *  - Token CSRF en todas las peticiones AJAX
 *  - Manejo global de sesión expirada / sin permiso
 *  - Menú lateral: marcar página activa
 *  - Campana de alertas
 *  - Atajos de teclado globales (Alt+V venta, Alt+C compra, Alt+A artículo)
 *  - Utilidades: appConfirm, appSetLoading, appQueryParam, appDebounce
 */
(function (window, document, $) {
  "use strict";

  // ---------- CSRF ----------
  $.ajaxSetup({
    headers: { "X-CSRF-Token": window.appCsrfToken || "" }
  });

  // ---------- Errores globales de AJAX ----------
  var sesionRedirigiendo = false;
  $(document).ajaxError(function (event, xhr, settings) {
    if (!xhr) { return; }
    var status = xhr.status;
    var mensaje = "";
    try {
      var r = JSON.parse(xhr.responseText || "{}");
      mensaje = r.message || "";
    } catch (e) {
      mensaje = "";
    }
    if (status === 401) {
      if (sesionRedirigiendo) { return; }
      sesionRedirigiendo = true;
      appNotify("warning", mensaje || "Tu sesión ha expirado. Vuelve a iniciar sesión.", 2500);
      setTimeout(function () { window.location.href = "login.php?expirada=1"; }, 1200);
      return;
    }
    if (status === 403) {
      appNotify("error", mensaje || "No tienes permiso para realizar esta acción.");
      return;
    }
    if (status === 419) {
      appNotify("error", mensaje || "Token de seguridad inválido. Recarga la página.", 5000);
      return;
    }
    if (status >= 500) {
      appNotify("error", "Ocurrió un error en el servidor. Revisa el registro o inténtalo de nuevo.");
    }
  });

  // ---------- Utilidades ----------
  window.appQueryParam = function (name) {
    var m = new RegExp("[?&]" + name + "=([^&#]*)").exec(window.location.search);
    return m ? decodeURIComponent(m[1].replace(/\+/g, " ")) : null;
  };

  window.appDebounce = function (fn, wait) {
    var t;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, wait || 250);
    };
  };

  window.appSetLoading = function (selector, loading) {
    var $b = $(selector);
    if (!$b.length) { return; }
    if (loading) {
      $b.addClass("is-loading").prop("disabled", true);
    } else {
      $b.removeClass("is-loading").prop("disabled", false);
    }
  };

  /**
   * appConfirm(mensaje, callback, opciones)
   * opciones: { titulo, ok, cancelar, tipo: 'danger'|'primary'|'warning' }
   */
  window.appConfirm = function (mensaje, callback, opciones) {
    opciones = opciones || {};
    var tipo = opciones.tipo || "primary";
    if (window.bootbox && typeof window.bootbox.confirm === "function") {
      window.bootbox.confirm({
        title: opciones.titulo || "Confirmar",
        message: mensaje,
        closeButton: true,
        buttons: {
          cancel: { label: '<i class="fa fa-times"></i> ' + (opciones.cancelar || "Cancelar"), className: "btn-default" },
          confirm: { label: '<i class="fa fa-check"></i> ' + (opciones.ok || "Sí, continuar"), className: "btn-" + tipo }
        },
        callback: function (r) { if (r && typeof callback === "function") { callback(); } }
      });
      return;
    }
    if (window.confirm(mensaje) && typeof callback === "function") { callback(); }
  };

  window.appParseJson = function (texto, fallback) {
    try { return JSON.parse(texto); } catch (e) { return fallback === undefined ? null : fallback; }
  };

  window.appEscapeHtml = function (texto) {
    return $("<div/>").text(texto == null ? "" : String(texto)).html();
  };

  window.appFechaHoy = function () {
    var d = new Date();
    return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2);
  };

  window.appFechaSumarDias = function (fechaISO, dias) {
    var d = fechaISO ? new Date(fechaISO + "T00:00:00") : new Date();
    d.setDate(d.getDate() + (dias || 0));
    return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2);
  };

  // ---------- Menú activo ----------
  function marcarMenuActivo() {
    var path = window.location.pathname.split("/").pop() || "escritorio.php";
    var $links = $(".sidebar-menu a[href]");
    var encontrado = false;
    $links.each(function () {
      var href = ($(this).attr("href") || "").split("?")[0];
      if (href && href === path) {
        var $li = $(this).closest("li");
        $li.addClass("active");
        var $parent = $li.closest("ul.treeview-menu").closest("li.treeview");
        if ($parent.length) {
          $parent.addClass("active menu-open");
          $parent.children("ul.treeview-menu").css("display", "block");
        }
        encontrado = true;
        return false;
      }
    });
    if (!encontrado && path === "escritorio.php") {
      $(".sidebar-menu a[href='escritorio.php']").closest("li").addClass("active");
    }
  }

  // ---------- Campana de alertas ----------
  function cargarAlertas() {
    window.cargarAlertasBell = cargarAlertas;
    var $bell = $("#navbarBell");
    if (!$bell.length) { return; }
    $.get("../ajax/consultas.php?op=dashboardAlertas", function (resp) {
      var r = window.appParseJson(resp, null);
      if (!r) {
        $("#bellItems").html('<div class="bell-empty">No se pudieron cargar las alertas.</div>');
        return;
      }
      var items = [];
      var total = 0;
      var money = window.appMoney || function (v) { return v; };
      if (Number(r.articulos_agotados) > 0) {
        items.push({ cls: "bell-danger", ico: "fa-ban", href: "procenter.php", t: r.articulos_agotados + " artículo(s) agotado(s)", s: "Sin stock disponible" });
        total += Number(r.articulos_agotados);
      }
      if (Number(r.articulos_bajo_minimo) > 0) {
        items.push({ cls: "bell-warning", ico: "fa-exclamation-triangle", href: "procenter.php", t: r.articulos_bajo_minimo + " artículo(s) bajo el mínimo", s: "Revisa compras sugeridas" });
        total += Number(r.articulos_bajo_minimo);
      }
      if (Number(r.variantes_agotadas) > 0) {
        items.push({ cls: "bell-danger", ico: "fa-tags", href: "articulo.php", t: r.variantes_agotadas + " talla(s)/color(es) agotado(s)", s: "Revisa qué reponer" });
        total += Number(r.variantes_agotadas);
      }
      if (Number(r.variantes_bajo_minimo) > 0) {
        items.push({ cls: "bell-warning", ico: "fa-tags", href: "articulo.php", t: r.variantes_bajo_minimo + " talla(s)/color(es) bajo el mínimo", s: "Stock por combinación" });
        total += Number(r.variantes_bajo_minimo);
      }
      if (Number(r.lotes_vencidos) > 0) {
        items.push({ cls: "bell-danger", ico: "fa-calendar-times-o", href: "vencimientos.php?estado=VENCIDO", t: r.lotes_vencidos + " lote(s) vencido(s)", s: money(r.lotes_vencidos_valor) + " sin poder venderse · dar de baja" });
        total += Number(r.lotes_vencidos);
      }
      if (Number(r.lotes_por_vencer) > 0) {
        items.push({ cls: "bell-warning", ico: "fa-hourglass-half", href: "vencimientos.php?estado=POR_VENCER", t: r.lotes_por_vencer + " lote(s) por vencer", s: "En los próximos " + r.dias_alerta_vencimiento + " días · " + money(r.lotes_por_vencer_valor) });
        total += Number(r.lotes_por_vencer);
      }
      if (Number(r.cxc_vencidas) > 0) {
        items.push({ cls: "bell-danger", ico: "fa-hand-holding-usd fa-money", href: "cuentas.php", t: r.cxc_vencidas + " cuenta(s) por cobrar vencida(s)", s: money(r.cxc_vencidas_monto) + " pendiente" });
        total += Number(r.cxc_vencidas);
      }
      if (Number(r.cxp_vencidas) > 0) {
        items.push({ cls: "bell-warning", ico: "fa-credit-card", href: "cuentas.php#cxp", t: r.cxp_vencidas + " cuenta(s) por pagar vencida(s)", s: money(r.cxp_vencidas_monto) + " por pagar" });
        total += Number(r.cxp_vencidas);
      }
      if (Number(r.caja_abierta) === 1) {
        items.push({ cls: "bell-success", ico: "fa-unlock", href: "caja.php", t: "Tienes una caja abierta", s: "Recuerda cerrarla al terminar el día" });
      }
      if (!items.length) {
        $("#bellItems").html('<div class="bell-empty"><i class="fa fa-check-circle" style="color:#16a34a"></i> Todo en orden. Sin alertas.</div>');
      } else {
        var html = "";
        for (var i = 0; i < items.length; i++) {
          var it = items[i];
          html += '<a class="bell-item ' + it.cls + '" href="' + it.href + '"><span class="bell-icon"><i class="fa ' + it.ico + '"></i></span><span><strong>' + window.appEscapeHtml(it.t) + '</strong><small>' + window.appEscapeHtml(it.s) + '</small></span></a>';
        }
        $("#bellItems").html(html);
      }
      var $count = $("#bellCount");
      if (total > 0) {
        $count.text(total > 99 ? "99+" : total).show();
      } else {
        $count.hide();
      }
    });
  }

  // ---------- Atajos globales ----------
  function atajos() {
    $(document).on("keydown", function (e) {
      if (!e.altKey || e.ctrlKey || e.metaKey) { return; }
      var k = (e.key || "").toLowerCase();
      var p = (window.appUser && window.appUser.permisos) || {};
      if (k === "v" && p.ventas) { e.preventDefault(); window.location.href = "venta.php?nuevo=1"; }
      if (k === "c" && p.compras) { e.preventDefault(); window.location.href = "ingreso.php?nuevo=1"; }
      if (k === "a" && p.almacen) { e.preventDefault(); window.location.href = "articulo.php?nuevo=1"; }
      if (k === "d" && p.escritorio) { e.preventDefault(); window.location.href = "escritorio.php"; }
    });
  }

  // ---------- Tooltips ----------
  function tooltips() {
    if ($.fn.tooltip) {
      $("body").tooltip({ selector: "[title]:not(.no-tooltip)", container: "body", trigger: "hover", delay: { show: 500, hide: 50 } });
    }
  }

  $(function () {
    marcarMenuActivo();
    cargarAlertas();
    atajos();
    tooltips();
    if (window.location.hash) {
      var $tab = $('a[data-toggle="tab"][href="' + window.location.hash + '"]');
      if ($tab.length) { $tab.tab("show"); }
    }
  });
})(window, document, jQuery);
