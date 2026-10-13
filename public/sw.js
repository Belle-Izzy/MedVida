const CACHE = 'medvida-shell-v8';
const SHELL = ['/', '/app.css', '/app.js', '/manifest.webmanifest', '/favicon.svg', '/logo-medvida.png', '/icon-eye-open.png', '/icon-eye-closed.png'];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys()
        .then(keys => Promise.all(keys.filter(key => key.startsWith('medvida-shell-') && key !== CACHE).map(key => caches.delete(key))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) return;

    event.respondWith(fetch(event.request).then(response => {
        if (response.ok && SHELL.includes(url.pathname)) {
            const copy = response.clone();
            event.waitUntil(caches.open(CACHE).then(cache => cache.put(event.request, copy)));
        }
        return response;
    }).catch(async () => {
        const cached = await caches.match(event.request);
        if (cached) return cached;
        if (event.request.mode === 'navigate') return caches.match('/');
        return Response.error();
    }));
});

self.addEventListener('push', event => {
    let data = {};
    try { data = event.data?.json() || {}; } catch (_) {}
    const destination = ['home', 'consultas', 'pending'].includes(data.destination) ? data.destination : 'home';
    event.waitUntil(self.registration.showNotification(data.title || 'MedVida', {
        body: data.body || 'Há uma atualização no seu MedVida.',
        icon: '/logo-medvida.png',
        badge: '/favicon.svg',
        tag: data.tag || 'medvida-update',
        data: { destination },
    }));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const destination = event.notification.data?.destination || 'home';
    const url = new URL(`/?notificacao=${encodeURIComponent(destination)}`, self.location.origin);
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const existing = windows.find(client => new URL(client.url).origin === self.location.origin);
        if (existing) {
            await existing.navigate(url.href);
            return existing.focus();
        }
        return self.clients.openWindow(url.href);
    })());
});
