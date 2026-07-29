/**
 * Nivel Cuatro W.3 — suscripción del lector a notificaciones push web.
 * Vanilla JS, sin dependencias: solo se encola en páginas que llevan el
 * shortcode `[pluma_suscripcion]` (`Pluma\Publicacion\WidgetSuscripcionPush`),
 * nunca en todo el sitio.
 */
(function () {
    function base64UrlADecimal(base64) {
        var relleno = '='.repeat((4 - (base64.length % 4)) % 4);
        var normalizado = (base64 + relleno).replace(/-/g, '+').replace(/_/g, '/');
        var binario = window.atob(normalizado);
        var bytes = new Uint8Array(binario.length);

        for (var i = 0; i < binario.length; ++i) {
            bytes[i] = binario.charCodeAt(i);
        }

        return bytes;
    }

    function suscribir(tipo, referenciaId, vertical) {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return Promise.reject(new Error('Push no soportado en este navegador.'));
        }

        var config = window.plumaSuscripcionPush;

        return navigator.serviceWorker
            .register(config.swUrl)
            .then(function (registro) {
                return fetch(config.restUrl + 'pluma/v1/suscripciones/clave-publica')
                    .then(function (respuesta) {
                        return respuesta.json();
                    })
                    .then(function (datos) {
                        return registro.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: base64UrlADecimal(datos.clavePublica),
                        });
                    });
            })
            .then(function (suscripcion) {
                var json = suscripcion.toJSON();

                return fetch(config.restUrl + 'pluma/v1/suscripciones/push', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tipo: tipo,
                        referenciaId: referenciaId || null,
                        vertical: vertical || null,
                        endpoint: json.endpoint,
                        claves: json.keys,
                    }),
                });
            });
    }

    window.PlumaSuscripcionPush = { suscribir: suscribir };

    document.addEventListener('DOMContentLoaded', function () {
        var botones = document.querySelectorAll('.pluma-suscripcion-push');

        botones.forEach(function (boton) {
            boton.addEventListener('click', function () {
                boton.disabled = true;

                suscribir(boton.dataset.tipo, boton.dataset.referenciaId, boton.dataset.vertical)
                    .then(function () {
                        boton.textContent = window.plumaSuscripcionPush.textoActivado;
                    })
                    .catch(function () {
                        boton.disabled = false;
                        boton.textContent = window.plumaSuscripcionPush.textoError;
                    });
            });
        });
    });
})();
