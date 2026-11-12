/* Maps Prospector PWA — versão 20260817-40
 * Política: cache apenas da tela offline, manifest e ícones públicos.
 * APIs, sessão, HTML autenticado, pesquisas, créditos, histórico, exportações
 * e webhooks nunca são gravados no Cache Storage deste service worker.
 */
const PWA_VERSION = '20260817-40';
const CACHE_NAME = `mapsprospector-static-${PWA_VERSION}`;
const STATIC_ASSETS = [
  '/offline.html',
  '/manifest.webmanifest',
  '/assets/pwa/icon-180.png',
  '/assets/pwa/icon-192.png',
  '/assets/pwa/icon-512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith('mapsprospector-static-') && key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Navegação sempre consulta a rede. Se ela estiver indisponível, entrega
  // somente a tela offline; não armazena páginas potencialmente autenticadas.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // Apenas os ativos públicos próprios do PWA usam cache-first. Todo o
  // restante, inclusive app.js, help.js e qualquer /api/, passa pela rede.
  if (url.pathname.startsWith('/assets/pwa/')) {
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request))
    );
  }
});
