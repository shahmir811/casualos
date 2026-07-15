// Service worker for the Casualite customer portal PWA.
// Deliberately does NOT cache portal.show/portal.dashboard HTML — order
// status must always be fetched fresh, caching it would show stale data.
// Only the static shell (icons, offline fallback) is cached.

const CACHE_VERSION = 'casualite-portal-v1';
const STATIC_ASSETS = [
    '/offline.html',
    '/images/pwa/icon-192.png',
    '/images/pwa/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Navigations (the portal page itself): always go to the network first
    // so order status is never stale; fall back to the offline page only if
    // the network is truly unreachable.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // Static shell assets: cache-first.
    if (STATIC_ASSETS.some((asset) => request.url.endsWith(asset))) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request))
        );
        return;
    }

    // Everything else (manifest, API calls, etc.) passes straight through.
});

self.addEventListener('push', (event) => {
    if (! event.data) {
        return;
    }

    const payload = event.data.json();

    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body,
            icon: payload.icon || '/images/pwa/icon-192.png',
            badge: payload.badge || '/images/pwa/icon-192.png',
            data: payload.data || {},
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data && event.notification.data.url;
    if (! targetUrl) {
        return;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url.startsWith(targetUrl.split('#')[0]) && 'focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            return self.clients.openWindow(targetUrl);
        })
    );
});
