import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // localhost only by default; `npm run dev -- --host` opens it to the LAN (phone test).
    host: 'localhost',
    port: 5173,
    strictPort: true,
    // The app calls /api on its own origin, so a phone on the WiFi reaches
    // Laravel through here and Laravel itself can stay on 127.0.0.1.
    proxy: { '/api': 'http://127.0.0.1:8000' },
  },
})
