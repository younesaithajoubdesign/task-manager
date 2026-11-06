// Laravel runs on its own origin; CORS (api/config/cors.php) allows this dev server.
import { clearToken, getToken, LOCKED_EVENT } from './gateToken.js'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000'

/** Error carrying Laravel's 422 field errors: { field: ['message'] }. */
export class ApiError extends Error {
  constructor(status, body) {
    super(body?.message ?? `HTTP ${status}`)
    this.status = status
    this.errors = body?.errors ?? {}
    this.body = body
  }
}

async function request(method, path, { body, signal } = {}) {
  const res = await fetch(`${API_URL}/api${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      ...(body !== undefined && { 'Content-Type': 'application/json' }),
      ...(getToken() && { Authorization: `Bearer ${getToken()}` }),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
    signal,
  })

  if (res.status === 204) return null

  // Token missing or outdated (password changed): back to the password screen.
  if (res.status === 401) {
    clearToken()
    window.dispatchEvent(new Event(LOCKED_EVENT))
  }

  const data = await res.json().catch(() => null)
  if (!res.ok) throw new ApiError(res.status, data)
  return data
}

export const apiGet = (path, options) => request('GET', path, options)
export const apiPost = (path, body) => request('POST', path, { body })
export const apiPatch = (path, body) => request('PATCH', path, { body })
export const apiDelete = (path) => request('DELETE', path)
