// Service Worker para PWA - Cache e Notificações Push
const CACHE_NAME = 'pwa-cache-v3';
const RUNTIME_CACHE = 'pwa-runtime-v3';

const STATIC_CACHE_URLS = ['/', '/style.css', '/index.php', '/dashboard.php'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_CACHE_URLS).catch(() => {}))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE) return caches.delete(cacheName);
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.origin !== location.origin && !url.href.includes('/api/') && !url.href.includes('/checkout') && !url.href.includes('/obrigado')) return;
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response.status === 200) {
                    const r = response.clone();
                    caches.open(RUNTIME_CACHE).then((cache) => cache.put(event.request, r));
                }
                return response;
            })
            .catch(() => caches.match(event.request).then((cached) => cached || (event.request.destination === 'document' ? caches.match('/') : null) || new Response('Offline', { status: 503, statusText: 'Service Unavailable', headers: new Headers({ 'Content-Type': 'text/html' }) })))
    );
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        if (event.data) {
            try { data = event.data.json(); } catch (e) {
                const t = event.data.text();
                try { data = JSON.parse(t); } catch (e2) { data = { title: 'Notificação', body: t || 'Nova notificação' }; }
            }
        } else data = { title: 'Notificação', body: 'Nova notificação' };
    } catch (e) { data = { title: 'Notificação', body: event.data ? event.data.text() : 'Nova notificação' }; }
    const title = data.title || 'Notificação';
    const body = data.body || data.message || 'Nova notificação';
    let icon = data.icon || '/assets/pix.svg';
    let badge = data.badge || '/assets/pix.svg';
    const url = data.url || (data.data && data.data.url) || '/';
    const baseUrl = self.location.origin;
    if (icon && !icon.startsWith('http')) icon = baseUrl + (icon.startsWith('/') ? icon : '/' + icon);
    if (badge && !badge.startsWith('http')) badge = baseUrl + (badge.startsWith('/') ? badge : '/' + badge);
    event.waitUntil(
        self.registration.showNotification(title, {
            body, icon, badge, tag: data.tag || 'pwa-' + Date.now(),
            data: { url, timestamp: data.timestamp || Date.now(), ...(data.data || {}) },
            requireInteraction: data.requireInteraction || false,
            vibrate: data.vibrate || [200, 100, 200],
            silent: false,
            sound: data.sound || 'default'
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    let urlToOpen = event.notification.data?.url || '/';
    if (!urlToOpen.startsWith('http') && !urlToOpen.startsWith('/')) urlToOpen = '/' + urlToOpen;
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (let i = 0; i < clientList.length; i++) {
                if (clientList[i].url.startsWith(self.location.origin)) {
                    return clientList[i].focus().then(() => {
                        if (clientList[i].url !== urlToOpen) return clientList[i].navigate(urlToOpen);
                    });
                }
            }
            if (clients.openWindow) return clients.openWindow(urlToOpen);
        })
    );
});
