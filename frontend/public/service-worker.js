self.addEventListener('install', event => {
  event.waitUntil(caches.open('iotdp-v1').then(cache => cache.addAll([
    '/',
  ])));
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  // Do not intercept cross-origin requests or API calls; let the browser handle CORS
  if (url.origin !== self.location.origin || url.pathname.startsWith('/api/')) {
    return; // no respondWith => request passes through
  }

  event.respondWith(
    caches.match(event.request).then(resp => resp || fetch(event.request))
  );
});
