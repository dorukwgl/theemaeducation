const CACHE_NAME = 'ema-app-v1';
const urlsToCache = [
    '/',
    '/index.html',
    '/folder_689d65065f916_ubt_logo.jpg',
    '/app-release.apk'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => 
            Promise.all(
                cacheNames.filter(name => name !== CACHE_NAME)
                    .map(name => caches.delete(name))
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => response || fetch(event.request).catch(() => {
                // Fallback for offline page
                if (event.request.mode === 'navigate') {
                    return caches.match('/index.html');
                }
            }))
    );
});