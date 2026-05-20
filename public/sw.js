self.addEventListener('push', function (event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    const payload = event.data ? event.data.json() : {};
    event.waitUntil(
        self.registration.showNotification(payload.title || 'Nova Notificação - DiariaSys', {
            body: payload.body || 'Você tem uma nova mensagem.',
            icon: '/logo.png',
            data: payload.url || '/',
            actions: payload.actions || []
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.data)
    );
});
