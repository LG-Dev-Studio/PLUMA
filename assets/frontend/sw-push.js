/**
 * Nivel Cuatro W.3 — service worker mínimo de notificaciones push web.
 * Solo maneja `push`/`notificationclick`: sin `fetch`, sin caché — cero
 * interferencia con el resto del sitio (ADR 0007, "peso adicional en
 * frontend ≈ 0 salvo el service worker de W.3").
 */
self.addEventListener('push', function (event) {
    if (!event.data) {
        return;
    }

    var datos = {};
    try {
        datos = event.data.json();
    } catch (error) {
        return;
    }

    event.waitUntil(
        self.registration.showNotification(datos.titulo || '', {
            body: datos.cuerpo || '',
            data: { url: datos.url || null },
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var url = event.notification.data && event.notification.data.url;

    if (url) {
        event.waitUntil(clients.openWindow(url));
    }
});
