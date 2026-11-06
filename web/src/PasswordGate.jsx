import { useEffect, useState } from 'react'
import { apiGet, apiPost } from './api.js'
import { clearToken, LOCKED_EVENT, setToken } from './gateToken.js'

/**
 * SPEC §5.1 — one centred password field and a "Go" button on black.
 * A curtain, not security: the app renders only once the API lets us in.
 */
export default function PasswordGate({ children }) {
  const [state, setState] = useState('checking') // checking | locked | open
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [sending, setSending] = useState(false)

  useEffect(() => {
    apiGet('/gate').then(
      ({ data }) => {
        if (!data.enabled || data.authorized) return setState('open')
        clearToken() // a stale token (password changed) is useless now
        setState('locked')
      },
      // API unreachable: show the app, whose screens explain the error.
      () => setState('open'),
    )

    const lock = () => setState('locked')
    window.addEventListener(LOCKED_EVENT, lock)
    return () => window.removeEventListener(LOCKED_EVENT, lock)
  }, [])

  async function submit(event) {
    event.preventDefault()
    if (!password || sending) return

    setSending(true)
    setError(null)
    try {
      const { data } = await apiPost('/gate', { password })
      setToken(data.token)
      setPassword('')
      setState('open')
    } catch (err) {
      setError(
        err.status === 429
          ? 'Too many tries. Wait a minute.'
          : (err.errors?.password?.[0] ?? 'Could not reach the server.'),
      )
    } finally {
      setSending(false)
    }
  }

  if (state === 'checking') return null
  // Rendering children only when open re-mounts the app after login,
  // so every screen and the timer re-read the API with the new token.
  if (state === 'open') return children

  return (
    <main className="gate">
      <form className="gate__form" onSubmit={submit}>
        <input
          type="password"
          className="gate__input"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          aria-label="Password"
          placeholder="Password"
          autoComplete="current-password"
          autoFocus
        />
        <button type="submit" className="button button--primary gate__go" disabled={sending}>
          Go
        </button>
      </form>
      {error && (
        <p className="gate__error" role="alert">
          {error}
        </p>
      )}
    </main>
  )
}
