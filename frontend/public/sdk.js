/**
 * Sinapsa Connect SDK · v0.1
 *
 * Embed este script en la app de tu cliente SaaS:
 *
 *   <script src="https://app.sinapsa.app/sdk.js"></script>
 *
 * Tu backend debe llamar a `POST /api/v1/connect-sessions` con su sk_live_ y
 * pasar el `session_token` resultante al frontend. NUNCA expongas el sk_live_
 * en el browser.
 *
 *   Sinapsa.connect({
 *     sessionToken: "eyJ...",
 *     onSuccess: (channel) => console.log("Canal conectado", channel),
 *     onError:   (err)     => console.error(err),
 *     onCancel:  ()        => console.log("Cancelado"),
 *   });
 */
(function () {
  "use strict";

  // El SDK abre la Hosted Connect Page del MISMO origen donde se sirve este script.
  // Eso permite que el SaaS de Sinapsa lo despliegue donde quiera sin que el
  // cliente tenga que reconfigurar.
  var SCRIPT = document.currentScript;
  var ORIGIN = SCRIPT
    ? new URL(SCRIPT.src, window.location.href).origin
    : window.location.origin;

  function openCenteredPopup(url, name, w, h) {
    var dual = window.screenLeft !== undefined ? window.screenLeft : screen.left;
    var dualY = window.screenTop !== undefined ? window.screenTop : screen.top;
    var width = window.innerWidth || document.documentElement.clientWidth || screen.width;
    var height = window.innerHeight || document.documentElement.clientHeight || screen.height;
    var left = (width - w) / 2 + dual;
    var top = (height - h) / 2 + dualY;
    var features =
      "scrollbars=yes,resizable=yes,width=" +
      w +
      ",height=" +
      h +
      ",top=" +
      top +
      ",left=" +
      left;
    var popup = window.open(url, name, features);
    if (popup && popup.focus) popup.focus();
    return popup;
  }

  function connect(opts) {
    opts = opts || {};
    if (!opts.sessionToken) {
      throw new Error("Sinapsa.connect: sessionToken is required");
    }

    var url = ORIGIN + "/connect?token=" + encodeURIComponent(opts.sessionToken);
    var popup = openCenteredPopup(url, "sinapsa-connect", 580, 720);
    if (!popup) {
      // Bloqueado por el browser → fallback redirect en mismo tab
      window.location.assign(url);
      return;
    }

    var resolved = false;
    var settle = function (kind, payload) {
      if (resolved) return;
      resolved = true;
      window.removeEventListener("message", onMessage);
      clearInterval(closedPoll);
      if (kind === "connected" && opts.onSuccess) opts.onSuccess(payload);
      else if (kind === "error" && opts.onError) opts.onError(payload);
      else if (kind === "cancelled" && opts.onCancel) opts.onCancel();
    };

    function onMessage(ev) {
      // En producción restringir ev.origin === ORIGIN
      if (!ev.data || typeof ev.data.type !== "string") return;
      if (ev.data.type === "sinapsa:connected") {
        settle("connected", ev.data.data);
      } else if (ev.data.type === "sinapsa:cancelled") {
        settle("cancelled");
      } else if (ev.data.type === "sinapsa:error") {
        settle("error", ev.data.data || { message: "Unknown error" });
      }
    }

    window.addEventListener("message", onMessage);

    // Si el usuario cierra el popup sin terminar, lo tratamos como cancelled.
    var closedPoll = setInterval(function () {
      try {
        if (popup.closed) settle("cancelled");
      } catch (e) {
        // noop
      }
    }, 500);
  }

  // Web Component opcional: <sinapsa-connect-button session-token="..." label="...">
  if (window.customElements && !window.customElements.get("sinapsa-connect-button")) {
    var ConnectButton = function () {
      return Reflect.construct(HTMLElement, [], ConnectButton);
    };
    ConnectButton.prototype = Object.create(HTMLElement.prototype);
    ConnectButton.prototype.constructor = ConnectButton;
    Object.setPrototypeOf(ConnectButton, HTMLElement);
    ConnectButton.prototype.connectedCallback = function () {
      var self = this;
      var label = self.getAttribute("label") || "Conectar WhatsApp";
      self.innerHTML =
        '<button type="button" style="padding:10px 18px;border-radius:9999px;background:#0b0b0c;color:#fff;border:0;font-weight:600;cursor:pointer;font-family:system-ui,-apple-system,sans-serif">' +
        label +
        "</button>";
      self.querySelector("button").addEventListener("click", function () {
        var token = self.getAttribute("session-token");
        if (!token) {
          console.error("sinapsa-connect-button: session-token attribute missing");
          return;
        }
        connect({
          sessionToken: token,
          onSuccess: function (ch) {
            self.dispatchEvent(new CustomEvent("sinapsa:connected", { detail: ch, bubbles: true }));
          },
          onError: function (err) {
            self.dispatchEvent(new CustomEvent("sinapsa:error", { detail: err, bubbles: true }));
          },
          onCancel: function () {
            self.dispatchEvent(new CustomEvent("sinapsa:cancelled", { bubbles: true }));
          },
        });
      });
    };
    window.customElements.define("sinapsa-connect-button", ConnectButton);
  }

  window.Sinapsa = window.Sinapsa || {};
  window.Sinapsa.connect = connect;
  window.Sinapsa.version = "0.1.0";
})();
