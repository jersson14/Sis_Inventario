/**
 * app-lector.js — lector de codigo de barras USB (modo teclado).
 *
 *   appLectorCodigo(selector, alLeer, opciones)
 *     alLeer(codigo) se llama cuando termina una lectura:
 *       - con Enter al final (si opciones.enter no es false; con enter:false
 *         el modulo sigue manejando su propio Enter)
 *       - con Tab al final (lectores configurados asi): no salta de campo
 *       - SIN sufijo: si el codigo llego en rafaga (el lector teclea mucho mas
 *         rapido que una persona) y luego hay un silencio corto
 *     Una persona escribiendo nunca dispara la lectura automatica: solo cuenta
 *     si el campo estaba vacio y todas las teclas llegaron casi juntas.
 *
 *   appSonido("ok" | "error")  pitido corto para contar sin mirar la pantalla
 */
(function (window, $) {
  "use strict";

  var ENTRE_TECLAS_MS = 45;   // un lector tipico: 5-20 ms; una persona: 80 ms o mas
  var SILENCIO_MS = 140;      // fin de la lectura sin sufijo
  var MINIMO = 4;             // codigos mas cortos solo con Enter

  window.appLectorCodigo = function (selector, alLeer, opciones) {
    var o = $.extend({ enter: true }, opciones || {});
    $(selector).each(function () {
      var input = this;
      var ultima = 0;
      var enRafaga = 0;         // teclas seguidas de la rafaga actual
      var empezoVacio = false;  // la rafaga empezo con el campo vacio
      var timer = null;

      function reiniciar() {
        clearTimeout(timer);
        timer = null;
        enRafaga = 0;
        empezoVacio = false;
        ultima = 0;
      }

      function esLectura() {
        return enRafaga >= MINIMO && empezoVacio;
      }

      function disparar() {
        var codigo = $.trim(input.value);
        reiniciar();
        if (codigo) { alLeer(codigo, input); }
      }

      $(input).on("keydown.appLector", function (e) {
        var ahora = Date.now();
        if (e.key === "Enter") {
          if (o.enter) { e.preventDefault(); disparar(); }
          else { reiniciar(); }   // el modulo procesa su Enter
          return;
        }
        if (e.key === "Tab" && esLectura() && ahora - ultima <= SILENCIO_MS) {
          e.preventDefault();
          disparar();
          return;
        }
        // Teclas que otro manejador ya uso (ej. + y - del POS) no son parte de un codigo
        if (!e.key || e.key.length !== 1 || e.ctrlKey || e.altKey || e.metaKey || e.isDefaultPrevented()) { return; }
        if (ahora - ultima > ENTRE_TECLAS_MS) {
          enRafaga = 1;
          empezoVacio = input.value === "" || (input.selectionStart === 0 && input.selectionEnd === input.value.length);
        } else {
          enRafaga++;
        }
        ultima = ahora;
        clearTimeout(timer);
        timer = setTimeout(function () {
          if (esLectura()) { disparar(); } else { enRafaga = 0; }
        }, SILENCIO_MS);
      });
    });
  };

  var audio = null;
  window.appSonido = function (tipo) {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) { return; }
      audio = audio || new Ctx();
      var osc = audio.createOscillator();
      var vol = audio.createGain();
      var error = tipo === "error";
      osc.type = error ? "square" : "sine";
      osc.frequency.value = error ? 220 : 1046;
      vol.gain.value = error ? 0.08 : 0.12;
      osc.connect(vol);
      vol.connect(audio.destination);
      osc.start();
      osc.stop(audio.currentTime + (error ? 0.28 : 0.07));
    } catch (e) { /* sin audio: no es necesario */ }
  };
})(window, jQuery);
