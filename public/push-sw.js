'use strict';

const fallback = {
    title: 'Catalog · Nova comunicação',
    body: 'Você possui uma nova mensagem segura. Entre na plataforma para consultar.',
    url: '/painel/comunicacoes',
    tag: 'catalog-secure-communication',
};

self.addEventListener('push', (event) => {
    let payload = fallback;

    try {
        payload = {...fallback, ...event.data.json()};
    } catch (_error) {
        payload = fallback;
    }

    const allowedPath = typeof payload.url === 'string'
        && (payload.url.startsWith('/painel/comunicacoes') || payload.url.startsWith('/plataforma/comunicacoes'));

    event.waitUntil(self.registration.showNotification(fallback.title, {
        body: fallback.body,
        icon: '/favicon.svg',
        badge: '/favicon.svg',
        tag: typeof payload.tag === 'string' ? payload.tag : fallback.tag,
        renotify: false,
        data: {url: allowedPath ? payload.url : fallback.url},
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || fallback.url, self.location.origin).href;

    event.waitUntil((async () => {
        const windows = await clients.matchAll({type: 'window', includeUncontrolled: true});
        for (const windowClient of windows) {
            if ('navigate' in windowClient) {
                await windowClient.navigate(target);
            }
            return windowClient.focus();
        }
        return clients.openWindow(target);
    })());
});
