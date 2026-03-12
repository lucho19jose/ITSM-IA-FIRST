/// <reference lib="webworker" />

import { precacheAndRoute } from 'workbox-precaching'
import { registerRoute, NavigationRoute } from 'workbox-routing'
import { NetworkFirst, CacheFirst, StaleWhileRevalidate } from 'workbox-strategies'
import { ExpirationPlugin } from 'workbox-expiration'

declare const self: ServiceWorkerGlobalScope

// ---------------------------------------------------------------------------
// 1. Precaching — vite-plugin-pwa InjectManifest replaces this at build time
// ---------------------------------------------------------------------------
precacheAndRoute(self.__WB_MANIFEST)

// ---------------------------------------------------------------------------
// 2. Skip waiting & claim clients so updates activate immediately
// ---------------------------------------------------------------------------
self.skipWaiting()
self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim())
})

// ---------------------------------------------------------------------------
// 3. Cache strategies
// ---------------------------------------------------------------------------

// API calls — NetworkFirst (prefer fresh data, fall back to cache)
registerRoute(
  ({ url }) => url.pathname.startsWith('/api/'),
  new NetworkFirst({
    cacheName: 'api-cache',
    networkTimeoutSeconds: 5,
    plugins: [
      new ExpirationPlugin({
        maxEntries: 50,
        maxAgeSeconds: 5 * 60, // 5 minutes
      }),
    ],
  }),
)

// Static assets (JS, CSS, images) — CacheFirst (immutable after build)
registerRoute(
  ({ request }) =>
    request.destination === 'script' ||
    request.destination === 'style' ||
    request.destination === 'image',
  new CacheFirst({
    cacheName: 'static-assets',
    plugins: [
      new ExpirationPlugin({
        maxEntries: 60,
        maxAgeSeconds: 30 * 24 * 60 * 60, // 30 days
      }),
    ],
  }),
)

// Google Fonts & CDN resources — StaleWhileRevalidate
registerRoute(
  ({ url }) =>
    url.origin === 'https://fonts.googleapis.com' ||
    url.origin === 'https://fonts.gstatic.com' ||
    url.origin === 'https://cdn.jsdelivr.net',
  new StaleWhileRevalidate({
    cacheName: 'cdn-cache',
    plugins: [
      new ExpirationPlugin({
        maxEntries: 30,
        maxAgeSeconds: 365 * 24 * 60 * 60, // 365 days
      }),
    ],
  }),
)

// ---------------------------------------------------------------------------
// 4. Offline fallback for navigation requests
// ---------------------------------------------------------------------------
const OFFLINE_HTML = `
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sin conexion - AutoService</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:system-ui,-apple-system,sans-serif;display:flex;
         align-items:center;justify-content:center;min-height:100vh;
         background:#f5f5f5;color:#333;text-align:center;padding:1rem}
    .card{background:#fff;border-radius:12px;padding:2.5rem;
          max-width:420px;box-shadow:0 2px 12px rgba(0,0,0,.1)}
    h1{font-size:1.5rem;margin-bottom:.75rem}
    p{color:#666;line-height:1.6;margin-bottom:1.25rem}
    button{background:#1976d2;color:#fff;border:none;border-radius:6px;
           padding:.625rem 1.5rem;font-size:1rem;cursor:pointer}
    button:hover{background:#1565c0}
  </style>
</head>
<body>
  <div class="card">
    <h1>Sin conexion</h1>
    <p>No se pudo conectar al servidor. Verifica tu conexion a internet e intenta de nuevo.</p>
    <button onclick="location.reload()">Reintentar</button>
  </div>
</body>
</html>
`

const offlineFallback = new NavigationRoute(
  async ({ event }) => {
    try {
      return await new NetworkFirst({
        cacheName: 'navigation-cache',
        networkTimeoutSeconds: 3,
      }).handle(event as any)
    } catch {
      return new Response(OFFLINE_HTML, {
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
      })
    }
  },
)

registerRoute(offlineFallback)
