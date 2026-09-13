(function ($) {
  "use strict";
  var KEY_REMEMBER = "mi_tienda_remember_login";
  var KEY_USER = "mi_tienda_remember_user";
  var enviando = false;
  var bloqueoTimer = null;

  function storage(fn) {
    try { return fn(); } catch (e) { return null; }
  }

  function mostrarAlerta(html, tipo) {
    var $a = $("#loginAlert");
    $a.attr("class", "login-alert" + (tipo ? " login-alert-" + tipo : "")).html(html).prop("hidden", false);
  }

  function ocultarAlerta() {
    $("#loginAlert").prop("hidden", true);
  }

  function setLoading(flag) {
    enviando = flag;
    $("#btnLogin").toggleClass("is-loading", flag).prop("disabled", flag);
  }

  function cargarRecordarme() {
    var remember = storage(function () { return localStorage.getItem(KEY_REMEMBER) === "1"; });
    var user = storage(function () { return localStorage.getItem(KEY_USER) || ""; }) || "";
    $("#rememberLogin").prop("checked", !!remember);
    if (remember && user !== "") {
      $("#logina").val(user);
      $("#clavea").focus();
    } else {
      $("#logina").focus();
    }
  }

  function guardarRecordarme() {
    var remember = $("#rememberLogin").is(":checked");
    var user = $.trim($("#logina").val());
    storage(function () {
      if (remember) {
        localStorage.setItem(KEY_REMEMBER, "1");
        localStorage.setItem(KEY_USER, user);
      } else {
        localStorage.removeItem(KEY_REMEMBER);
        localStorage.removeItem(KEY_USER);
      }
    });
  }

  function togglePassword() {
    var $clave = $("#clavea");
    var $icon = $("#toggleClave i");
    var isPassword = $clave.attr("type") === "password";
    $clave.attr("type", isPassword ? "text" : "password");
    $icon.removeClass("fa-eye fa-eye-slash").addClass(isPassword ? "fa-eye-slash" : "fa-eye");
    $("#toggleClave").attr("aria-label", isPassword ? "Ocultar contraseña" : "Mostrar contraseña").attr("title", isPassword ? "Ocultar contraseña" : "Mostrar contraseña");
    $clave.focus();
  }

  function iniciarCuentaRegresiva(segundos) {
    clearInterval(bloqueoTimer);
    var restante = Math.max(1, parseInt(segundos, 10) || 60);
    $("#btnLogin").prop("disabled", true);
    var pintar = function () {
      var m = Math.floor(restante / 60);
      var s = restante % 60;
      mostrarAlerta('<i class="fa fa-lock"></i> Demasiados intentos fallidos. Podrás volver a intentar en <strong>' + m + ":" + ("0" + s).slice(-2) + "</strong>.", "danger");
    };
    pintar();
    bloqueoTimer = setInterval(function () {
      restante--;
      if (restante <= 0) {
        clearInterval(bloqueoTimer);
        $("#btnLogin").prop("disabled", false);
        mostrarAlerta('<i class="fa fa-unlock"></i> Ya puedes volver a intentarlo.', "info");
        return;
      }
      pintar();
    }, 1000);
  }

  function enviar(e) {
    e.preventDefault();
    if (enviando) { return; }
    var logina = $.trim($("#logina").val());
    var clavea = $("#clavea").val();
    if (!logina || !clavea) {
      mostrarAlerta('<i class="fa fa-exclamation-circle"></i> Ingresa tu usuario y contraseña.', "warning");
      (!logina ? $("#logina") : $("#clavea")).focus();
      return;
    }
    ocultarAlerta();
    setLoading(true);

    $.ajax({
      url: "../ajax/usuario.php?op=verificar",
      type: "POST",
      data: { logina: logina, clavea: clavea, _csrf: $("#csrf").val() },
      headers: { "X-CSRF-Token": $("#csrf").val() },
      dataType: "text"
    }).done(function (data) {
      var r = null;
      try { r = JSON.parse(data); } catch (err) { r = null; }
      if (r && r.ok) {
        guardarRecordarme();
        mostrarAlerta('<i class="fa fa-check-circle"></i> ¡Hola ' + $("<div/>").text(r.nombre || "").html() + '! Ingresando…', "success");
        window.location.href = r.redirect || "escritorio.php";
        return;
      }
      setLoading(false);
      if (r && r.bloqueado) {
        iniciarCuentaRegresiva(r.segundos);
        return;
      }
      // Compatibilidad con respuesta antigua ("null")
      var msg = (r && r.message) ? r.message : "Usuario y/o contraseña incorrectos";
      mostrarAlerta('<i class="fa fa-times-circle"></i> ' + $("<div/>").text(msg).html(), "danger");
      $("#clavea").val("").focus();
      $(".login-form-card").addClass("shake");
      setTimeout(function () { $(".login-form-card").removeClass("shake"); }, 500);
    }).fail(function (xhr) {
      setLoading(false);
      var msg = "No se pudo conectar con el servidor.";
      try {
        var r = JSON.parse(xhr.responseText || "{}");
        if (r.message) { msg = r.message; }
        if (xhr.status === 419) { msg += " Recargando la página…"; setTimeout(function () { window.location.reload(); }, 1500); }
      } catch (err) { /* ignore */ }
      mostrarAlerta('<i class="fa fa-plug"></i> ' + $("<div/>").text(msg).html(), "danger");
    });
  }

  $(function () {
    $("#toggleClave").on("click", togglePassword);
    $("#frmAcceso").on("submit", enviar);
    $("#logina, #clavea").on("input", ocultarAlerta);
    cargarRecordarme();
  });
})(jQuery);
