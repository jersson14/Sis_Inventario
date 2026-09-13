/**
 * app-notify.js — toasts de notificación.
 *  appNotify(tipo, mensaje, duracionMs)   tipo: success | error | warning | info
 *  appNotifyFromResponse(texto)           deduce el tipo por el contenido del mensaje
 */
(function (window, document) {
  var CONTAINER_ID = "app-toast-container";

  function ensureContainer() {
    var container = document.getElementById(CONTAINER_ID);
    if (container) { return container; }
    container = document.createElement("div");
    container.id = CONTAINER_ID;
    container.className = "app-toast-container";
    document.body.appendChild(container);
    return container;
  }

  function getIconClass(type) {
    if (type === "success") return "fa-check-circle";
    if (type === "warning") return "fa-exclamation-triangle";
    if (type === "error") return "fa-times-circle";
    return "fa-info-circle";
  }

  function getTitle(type) {
    if (type === "success") return "Listo";
    if (type === "warning") return "Atención";
    if (type === "error") return "Error";
    return "Información";
  }

  function removeToast(toast) {
    if (!toast || !toast.parentNode) return;
    toast.classList.remove("show");
    setTimeout(function () {
      if (toast && toast.parentNode) { toast.parentNode.removeChild(toast); }
    }, 250);
  }

  function notify(type, message, timeout) {
    var container = ensureContainer();
    // Evitar duplicados exactos consecutivos
    var last = container.lastElementChild;
    if (last && last.getAttribute("data-msg") === String(message) && last.classList.contains("app-toast-" + type)) {
      return;
    }
    var toast = document.createElement("div");
    toast.className = "app-toast app-toast-" + (type || "info");
    toast.setAttribute("data-msg", String(message));
    toast.setAttribute("role", type === "error" ? "alert" : "status");

    var duration = typeof timeout === "number" ? timeout : (type === "error" ? 5200 : 3600);

    toast.innerHTML =
      '<div class="app-toast-icon"><i class="fa ' + getIconClass(type) + '"></i></div>' +
      '<div class="app-toast-content">' +
      '<div class="app-toast-title">' + getTitle(type) + "</div>" +
      '<div class="app-toast-text"></div>' +
      '<div class="app-toast-progress"><span></span></div>' +
      "</div>" +
      '<button type="button" class="app-toast-close" aria-label="Cerrar">&times;</button>';

    toast.querySelector(".app-toast-text").textContent = message || "Proceso completado.";
    toast.querySelector(".app-toast-close").addEventListener("click", function () { removeToast(toast); });

    container.appendChild(toast);
    setTimeout(function () { toast.classList.add("show"); }, 10);

    var progress = toast.querySelector(".app-toast-progress span");
    if (progress) {
      progress.style.transition = "transform " + duration + "ms linear";
      setTimeout(function () { progress.style.transform = "scaleX(0)"; }, 20);
    }
    setTimeout(function () { removeToast(toast); }, duration);
  }

  function classify(message) {
    var text = (message || "").toLowerCase();
    if (!text) return "info";
    if (text.indexOf("no se pudo") !== -1 || text.indexOf("error") !== -1 || text.indexOf("fatal") !== -1 ||
        text.indexOf("no tienes permiso") !== -1 || text.indexOf("inválid") !== -1 || text.indexOf("invalid") !== -1 ||
        text.indexOf("no puede") !== -1 || text.indexOf("ya existe") !== -1 || text.indexOf("obligatori") !== -1 ||
        text.indexOf("no encontr") !== -1 || text.indexOf("insuficiente") !== -1) return "error";
    if (text.indexOf("advertencia") !== -1 || text.indexOf("warning") !== -1 || text.indexOf("atencion") !== -1 ||
        text.indexOf("atención") !== -1 || text.indexOf("desactivad") !== -1 || text.indexOf("anulad") !== -1) return "warning";
    return "success";
  }

  window.appNotify = notify;
  window.appNotifyFromResponse = function (message, timeout) {
    var txt = (message === undefined || message === null) ? "" : String(message).trim();
    // Si vino JSON con {ok, message}, úsalo
    if (txt.charAt(0) === "{") {
      try {
        var r = JSON.parse(txt);
        if (r && typeof r.message !== "undefined") {
          notify(r.ok === false ? "error" : "success", r.message, timeout);
          return;
        }
      } catch (e) { /* no era JSON */ }
    }
    if (txt.indexOf("<") === 0) {
      notify("error", "El servidor devolvió una respuesta inesperada. Revisa el registro de errores.", timeout);
      return;
    }
    notify(classify(txt), txt, timeout);
  };

  // Compatibilidad: bootbox.alert -> toast
  if (window.bootbox && typeof window.bootbox.alert === "function") {
    window.bootbox.alert = function (message, callback) {
      var txt = (typeof message === "object" && message !== null && message.message) ? message.message : message;
      notify(classify(txt), txt, 3600);
      if (typeof callback === "function") { callback(); }
    };
  }
})(window, document);
