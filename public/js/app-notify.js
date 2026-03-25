(function (window, document) {
  var CONTAINER_ID = "app-toast-container";

  function ensureContainer() {
    var container = document.getElementById(CONTAINER_ID);
    if (container) {
      return container;
    }

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
    if (type === "success") return "Operacion exitosa";
    if (type === "warning") return "Advertencia";
    if (type === "error") return "Error";
    return "Informacion";
  }

  function removeToast(toast) {
    if (!toast || !toast.parentNode) return;
    toast.classList.remove("show");
    setTimeout(function () {
      if (toast && toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 250);
  }

  function notify(type, message, timeout) {
    var container = ensureContainer();
    var toast = document.createElement("div");
    toast.className = "app-toast app-toast-" + (type || "info");

    var duration = typeof timeout === "number" ? timeout : 3600;
    var icon = getIconClass(type);
    var title = getTitle(type);

    toast.innerHTML =
      '<div class="app-toast-icon"><i class="fa ' + icon + '"></i></div>' +
      '<div class="app-toast-content">' +
      '<div class="app-toast-title">' + title + "</div>" +
      '<div class="app-toast-text"></div>' +
      '<div class="app-toast-progress"><span></span></div>' +
      "</div>";

    toast.querySelector(".app-toast-text").textContent = message || "Proceso completado.";

    container.appendChild(toast);

    setTimeout(function () {
      toast.classList.add("show");
    }, 10);

    var progress = toast.querySelector(".app-toast-progress span");
    if (progress) {
      progress.style.transition = "transform " + duration + "ms linear";
      setTimeout(function () {
        progress.style.transform = "scaleX(0)";
      }, 20);
    }

    setTimeout(function () {
      removeToast(toast);
    }, duration);
  }

  function classify(message) {
    var text = (message || "").toLowerCase();
    if (!text) return "info";
    if (text.indexOf("no se pudo") !== -1 || text.indexOf("error") !== -1 || text.indexOf("fatal") !== -1) return "error";
    if (text.indexOf("advertencia") !== -1 || text.indexOf("warning") !== -1 || text.indexOf("atencion") !== -1) return "warning";
    return "success";
  }

  window.appNotify = notify;
  window.appNotifyFromResponse = function (message, timeout) {
    notify(classify(message), message, timeout);
  };

  // Compatibilidad: convierte bootbox.alert en toast para mensajes mas profesionales.
  if (window.bootbox && typeof window.bootbox.alert === "function") {
    window.bootbox.alert = function (message, callback) {
      notify(classify(message), message, 3600);
      if (typeof callback === "function") {
        callback();
      }
    };
  }
})(window, document);
