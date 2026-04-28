const CACHE_NAME = 'multipos-cache-v1';
const STATIC_ASSETS = [
    '/multi-tenant-bcv/public/css/adminlte.css',
    '/multi-tenant-bcv/public/css/custom.css',
    '/multi-tenant-bcv/public/js/adminlte.js',
    '/multi-tenant-bcv/public/js/pos.js',
    // Añade aquí tus fuentes e iconos locales
];

// Instalación y Caché Inicial
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

// Limpieza de Caché antigua
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Interceptando Peticiones
self.addEventListener('fetch', (event) => {
    const isApiCall = event.request.url.includes('.php');

    if (isApiCall) {
        // Network-First para archivos PHP (Datos en tiempo real)
        event.respondWith(
            fetch(event.request).catch(() => caches.match(event.request))
        );
    } else {
        // Cache-First para recursos estáticos
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                return cachedResponse || fetch(event.request);
            })
        );
    }
});