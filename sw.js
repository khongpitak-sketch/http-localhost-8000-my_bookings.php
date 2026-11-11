// sw.js - Service Worker สำหรับระบบขออนุญาตใช้รถยนต์ (v2.0) คณะวิทยาการจัดการ ม.นราธิวาสราชนครินทร์
const CACHE_VERSION = 'fms-pnu-car-pwa-v2.0.1';
const STATIC_CACHE = CACHE_VERSION + '-static';
const DYNAMIC_CACHE = CACHE_VERSION + '-dynamic';

const PRECACHE_ASSETS = [
    '/offline.html',
    '/manifest.json',
    '/assets/fms-logo.png',
    '/assets/js/table-sorter.js?v=2.0',
    '/assets/icons/icon.svg',
    '/assets/icons/icon-maskable.svg',
    '/assets/icons/apple-touch-icon.svg',
    '/assets/icons/safari-pinned-tab.svg',
    '/assets/icons/shortcut-book.svg',
    '/assets/icons/shortcut-status.svg',
    '/assets/icons/shortcut-calendar.svg',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
    '/assets/icons/icon-maskable-192.png',
    '/assets/icons/icon-maskable-512.png',
    '/assets/icons/apple-touch-icon.png'
];

// 1. Install Event - Precache core static & SVG icon assets
self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return Promise.allSettled(
                PRECACHE_ASSETS.map((url) =>
                    cache.add(url).catch(() => {})
                )
            );
        })
    );
});

// 2. Activate Event - Clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) => key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
                    .map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// 3. Fetch Event - Network-First for dynamic PHP pages, Stale-While-Revalidate for static assets
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only handle GET requests over http/https
    if (request.method !== 'GET' || !request.url.startsWith('http')) {
        return;
    }

    const url = new URL(request.url);

    // Never cache auth/session heartbeat or logout endpoints
    if (
        url.pathname.endsWith('heartbeat.php') ||
        url.pathname.endsWith('logout.php') ||
        url.pathname.endsWith('login.php')
    ) {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // Navigation / HTML / PHP pages -> Network-First with Offline Fallback
    const isNavigation =
        request.mode === 'navigate' ||
        (request.headers.get('accept') || '').includes('text/html') ||
        url.pathname.endsWith('.php') ||
        url.pathname === '/';

    if (isNavigation) {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(DYNAMIC_CACHE).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                })
                .catch(() => {
                    return caches.match(request).then((cachedResponse) => {
                        return cachedResponse || caches.match('/offline.html');
                    });
                })
        );
        return;
    }

    // Static assets (SVG, PNG, CSS, JS, Fonts) -> Cache-First with background update
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            const fetchPromise = fetch(request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(STATIC_CACHE).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                })
                .catch(() => cachedResponse);

            return cachedResponse || fetchPromise;
        })
    );
});
