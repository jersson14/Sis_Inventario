(function () {
  var KEY_REMEMBER = "mi_tienda_remember_login";
  var KEY_USER = "mi_tienda_remember_user";

  function decodeHtml(text) {
    return $("<textarea/>").html(text || "").text().trim();
  }

  function escapeHtml(text) {
    return $("<div/>").text(text || "").html();
  }

  function limpiarSubtitulo(nombre, sub) {
    var subtitulo = (sub || "").trim();
    if (!subtitulo || subtitulo === nombre) return "";

    if (subtitulo.indexOf(nombre) !== -1) {
      subtitulo = $.trim(subtitulo.replace(nombre, ""));
    }

    subtitulo = subtitulo.replace(/^[\s"'`|:-]+|[\s"'`|:-]+$/g, "");
    return subtitulo;
  }

  function darkenHex(hex, amount) {
    var h = (hex || "").replace("#", "");
    if (!/^[0-9a-fA-F]{6}$/.test(h)) return "#0b4f4a";
    var factor = 1 - (amount || 0.22);
    var r = Math.max(0, Math.round(parseInt(h.substring(0, 2), 16) * factor));
    var g = Math.max(0, Math.round(parseInt(h.substring(2, 4), 16) * factor));
    var b = Math.max(0, Math.round(parseInt(h.substring(4, 6), 16) * factor));
    var toHex = function (n) { return ("0" + n.toString(16)).slice(-2); };
    return "#" + toHex(r) + toHex(g) + toHex(b);
  }

  function aplicarBrandPublico() {
    $.get("../ajax/empresa.php?op=publicBrand", function (resp) {
      var cfg = null;
      try {
        cfg = JSON.parse(resp);
      } catch (e) {
        cfg = null;
      }
      if (!cfg) return;

      var primary = cfg.color_primario || "#0f766e";
      var accent = cfg.color_secundario || "#f59e0b";
      document.documentElement.style.setProperty("--brand-primary", primary);
      document.documentElement.style.setProperty("--brand-primary-dark", darkenHex(primary, 0.25));
      document.documentElement.style.setProperty("--brand-accent", accent);

      if (cfg.logo_url) {
        $("#loginBrandLogo").attr("src", cfg.logo_url);
        $("#loginFavicon").attr("href", cfg.logo_url);
      }

      var nombre = decodeHtml(cfg.nombre_comercial || "PERNO CENTRO").toUpperCase();
      var subRaw = decodeHtml(cfg.razon_social || "SEÑOR DE HUANCA").toUpperCase();
      var sub = limpiarSubtitulo(nombre, subRaw);
      var titulo = escapeHtml(nombre) + (sub ? "<br>" + escapeHtml(sub) : "");
      $("#loginBrandTitle").html(titulo);
      document.title = nombre + " | Login";
    });
  }

  function cargarRecordarme() {
    var remember = localStorage.getItem(KEY_REMEMBER) === "1";
    var user = localStorage.getItem(KEY_USER) || "";

    $("#rememberLogin").prop("checked", remember);
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

    if (remember) {
      localStorage.setItem(KEY_REMEMBER, "1");
      localStorage.setItem(KEY_USER, user);
    } else {
      localStorage.removeItem(KEY_REMEMBER);
      localStorage.removeItem(KEY_USER);
    }
  }

  function togglePassword() {
    var $clave = $("#clavea");
    var $icon = $("#toggleClave i");
    var isPassword = $clave.attr("type") === "password";

    $clave.attr("type", isPassword ? "text" : "password");
    $icon.removeClass("fa-eye fa-eye-slash").addClass(isPassword ? "fa-eye-slash" : "fa-eye");
    $("#toggleClave")
      .attr("aria-label", isPassword ? "Ocultar contraseña" : "Mostrar contraseña")
      .attr("title", isPassword ? "Ocultar contraseña" : "Mostrar contraseña");
  }

  $("#toggleClave").on("click", function () {
    togglePassword();
  });

  $("#frmAcceso").on("submit", function (e) {
    e.preventDefault();

    var logina = $.trim($("#logina").val());
    var clavea = $("#clavea").val();

    $.post(
      "../ajax/usuario.php?op=verificar",
      { logina: logina, clavea: clavea },
      function (data) {
        if ($.trim(data) !== "null") {
          guardarRecordarme();
          $(location).attr("href", "escritorio.php");
        } else {
          bootbox.alert("Usuario y/o contraseña incorrectos");
        }
      }
    );
  });

  aplicarBrandPublico();
  cargarRecordarme();
})();
