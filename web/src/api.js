// Laravel runs on its own origin; CORS (api/config/cors.php) allows this dev server.
const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000'

export async function apiGet(path, { signal } = {}) {
  const res = await fetch(`${API_URL}/api${path}`, {
    headers: { Accept: 'application/json' },
    signal,
  })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}
