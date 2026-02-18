import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/filament/admin/theme.css',
        'resources/js/filament/admin.js',
      ],
      refresh: true,
    }),
  ],

  build: {
    // GrapesJS is intentionally lazy-loaded as a large admin-only chunk.
    chunkSizeWarningLimit: 1200,
  },
  server: {
    // prevent Vite from choosing IPv6 ::1
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,

    // allow your .test domain to load module scripts from the dev server
    cors: true,

    // ensures the "hot" file uses this URL (not http://[::1]:5173)
    origin: 'http://127.0.0.1:5173',

    // fixes HMR websocket host when page is on ehc-jet-spatie.test
    hmr: {
      host: 'ehc-jet-spatie.test',
      protocol: 'ws',
      port: 5173,
    },
  },
})
