// Royal Nest Progressive Web App Service Worker
// Security Policy: Never cache private authenticated ERP/financial data, CSRF tokens, or user sessions.

const CACHE_NAME = 'royalnest-static-v1';
const STATIC_ASSETS = [
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Only process GET requests from same origin
    if (request.method !== 'GET' || url.origin !== location.origin) {
        return;
    }

    // STRICT NON-CACHING RULES:
    // Never cache document navigations, API endpoints, Inertia JSON, financial or operational routes
    const isNavigation = request.mode === 'navigate';
    const isPrivateDynamicRoute = 
        url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/reports') ||
        url.pathname.startsWith('/sales') ||
        url.pathname.startsWith('/employees') ||
        url.pathname.startsWith('/daily-costs') ||
        url.pathname.startsWith('/accounts') ||
        url.pathname.startsWith('/calculator') ||
        url.pathname.startsWith('/consumption') ||
        url.pathname.startsWith('/cash-reconciliation') ||
        url.pathname.startsWith('/suppliers') ||
        url.pathname.startsWith('/purchases') ||
        url.pathname.startsWith('/expenses') ||
        url.pathname.startsWith('/ledger') ||
        url.pathname.startsWith('/wastage') ||
        url.pathname.startsWith('/stock-counts') ||
        url.pathname.startsWith('/login') ||
        url.pathname.startsWith('/logout');

    if (isNavigation || isPrivateDynamicRoute || request.headers.get('x-inertia')) {
        // Direct network fetch with zero local caching
        event.respondWith(
            fetch(request).catch(() => {
                // If offline during navigation, let browser show offline message
                return new Response('Offline: Connection to Royal Nest ERP is unavailable.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain' },
                });
            })
        );
        return;
    }

    // Cache static immutable build assets (/build/assets/*) and static icons
    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseToCache);
                        });
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Default network-first for other static assets
    event.respondWith(fetch(request));
});
