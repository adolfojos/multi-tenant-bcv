const CACHE_NAME = 'mtb-v1';
const urlsToCache = [
  '/',
  '/index.php',
  '/public/css/adminlte.min.css',
  '/public/js/adminlte.min.js'
];

// Instalación del Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

// Estrategia de carga: Network First (Prioriza red, si falla usa caché)
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});