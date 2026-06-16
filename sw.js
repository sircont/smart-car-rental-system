// Service worker - PWA offline support (minimal)
const CACHE = 'car-rental-v2';
self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(CACHE).then(function (cache) {
    return cache.addAll(['./index.php', './cars.php', './login.php'].filter(Boolean));
  }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
  e.respondWith(fetch(e.request).catch(function () {
    return caches.match(e.request).then(function (r) { return r || caches.match('./index.php'); });
  }));
});
