const CACHE_NAME = 'lifemed-v1';
const ASSETS = [
  '/',
  '/admin/login.php',
  '/assets/vendor/css/bootstrap.min.css',
  '/assets/vendor/js/bootstrap.bundle.min.js',
  '/assets/vendor/js/vue.global.js',
  '/assets/vendor/js/axios.min.js',
  '/assets/vendor/css/fontawesome.min.css'
];

// Install Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('Caching assets');
      return cache.addAll(ASSETS);
    })
  );
});

// Activate Service Worker
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(keys
        .filter(key => key !== CACHE_NAME)
        .map(key => caches.delete(key))
      );
    })
  );
});

// Fetch events
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then(res => {
        const resClone = res.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, resClone);
        });
        return res;
      })
      .catch(() => caches.match(event.request).then(res => res))
  );
});
