// Same origin by default: the Vite dev server proxies /api to Laravel (vite.config.js),
// and in production the web server does the same. VITE_API_URL can still point
// elsewhere; CORS (api/config/cors.php) allows http://localhost:5173 for that.
import { reportNetworkError, reportResponse } from './connectivity.js'
import { clearToken, getToken, LOCKED_EVENT } from './gateToken.js'

const API_URL = import.meta.env.VITE_API_URL ?? ''

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
  let res
  try {
    res = await fetch(`${API_URL}/api${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body !== undefined && { 'Content-Type': 'application/json' }),
        ...(getToken() && { Authorization: `Bearer ${getToken()}` }),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal,
    })
  } catch (err) {
    // No answer at all: offline (or the server is down). Aborts are not.
    if (err.name !== 'AbortError') reportNetworkError()
    throw err
  }
  reportResponse(res)

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
