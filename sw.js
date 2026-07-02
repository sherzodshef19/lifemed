const CACHE_NAME = 'lifemed-v2';
const MAX_CACHE_SIZE = 100;

const ASSETS = [
  '/',
  '/admin/login.php',
  '/assets/vendor/css/bootstrap.min.css',
  '/assets/vendor/js/bootstrap.bundle.min.js',
  '/assets/vendor/js/vue.global.prod.js',
  '/assets/vendor/js/axios.min.js',
  '/assets/vendor/css/fontawesome.min.css',
  '/assets/vendor/js/qrcode.min.js'
];

// Install
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('Pre-caching assets');
      return cache.addAll(ASSETS);
    })
  );
  self.skipWaiting();
});

// Activate — clean old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(keys
        .filter(key => key !== CACHE_NAME)
        .map(key => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

// Fetch — network-first, but skip API and POST requests
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // Never cache API endpoints — serve network only
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(fetch(event.request));
    return;
  }

  // For static assets — cache-first (they don't change)
  if (url.pathname.match(/\.(css|js|woff2?|ttf|png|jpg|gif|svg|ico)$/)) {
    event.respondWith(
      caches.match(event.request).then(cached => {
        if (cached) return cached;
        return fetch(event.request).then(res => {
          const resClone = res.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, resClone);
          });
          return res;
        });
      })
    );
    return;
  }

  // For HTML pages — network-first with cache fallback
  event.respondWith(
    fetch(event.request)
      .then(res => {
        const resClone = res.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, resClone);
          trimCache(CACHE_NAME, MAX_CACHE_SIZE);
        });
        return res;
      })
      .catch(() => caches.match(event.request))
  );
});

// Trim cache to max size (LRU — remove oldest entries)
function trimCache(name, maxItems) {
  caches.open(name).then(cache => {
    cache.keys().then(keys => {
      if (keys.length > maxItems) {
        cache.delete(keys[0]).then(() => trimCache(name, maxItems));
      }
    });
  });
}
