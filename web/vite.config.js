import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'
import { VitePWA } from 'vite-plugin-pwa'

// The app calls /api on its own origin, so a phone on the WiFi reaches
// Laravel through the dev (or preview) server and Laravel can stay on 127.0.0.1.
const proxy = { '/api': 'http://127.0.0.1:8000' }

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    // SPEC addendum §13: installable, and readable offline. Never writes offline.
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.svg', 'icons/apple-touch-icon.png'],
      manifest: {
        id: '/',
        name: 'Task Manager',
        short_name: 'Tasks',
        start_url: '/',
        scope: '/',
        display: 'standalone',
        background_color: '#000000',
        theme_color: '#000000',
        icons: [
          { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
          { src: '/icons/icon-512-maskable.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        // App shell: precached, served cache-first.
        globPatterns: ['**/*.{js,css,html,svg,png,woff2}'],
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//],
        cleanupOutdatedCaches: true,
        runtimeCaching: [
          {
            // GET /api/*: fresh data when online, the last known data offline.
            // POST / PATCH / DELETE match no route here: never cached, never queued.
            urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
            method: 'GET',
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api',
              networkTimeoutSeconds: 6,
              matchOptions: { ignoreVary: true },
              cacheableResponse: { statuses: [200] },
              plugins: [
                {
                  // Behind a proxy (Vite, Caddy) an unreachable Laravel is a 502/503/504,
                  // not a network error: treat it the same, so the cache answers.
                  fetchDidSucceed: async ({ response }) => {
                    if ([502, 503, 504].includes(response.status)) throw new Error(`Gateway ${response.status}`)
                    return response
                  },
                },
                {
                  // Only called when the answer comes from the cache: flag it, so
                  // the app knows it is offline even when navigator.onLine says
                  // otherwise (captive portals) and can say how old the data is.
                  cachedResponseWillBeUsed: async ({ cachedResponse }) => {
                    if (!cachedResponse) return cachedResponse
                    const headers = new Headers(cachedResponse.headers)
                    headers.set('X-From-Cache', '1')
                    const body = await cachedResponse.blob()
                    return new Response(body, {
                      status: cachedResponse.status,
                      statusText: cachedResponse.statusText,
                      headers,
                    })
                  },
                },
              ],
            },
          },
        ],
      },
    }),
  ],
  server: {
    // localhost only by default; `npm run dev -- --host` opens it to the LAN (phone test).
    host: 'localhost',
    port: 5173,
    strictPort: true,
    proxy,
  },
  // `npm run build && npm run preview`: the built app, with its service worker.
  preview: { host: 'localhost', port: 4173, strictPort: true, proxy },
})
