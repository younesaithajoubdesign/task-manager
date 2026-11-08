import { useEffect, useRef, useState } from 'react'
import { useOnline } from './connectivity.js'
import { deleteCounterReset, resetCounter } from './counterApi.js'
import { useFetch } from './useFetch.js'

const DAY_MS = 24 * 60 * 60 * 1000

/** Whole days since `iso`, kept current while the app stays open. */
function useDaysSince(iso) {
  const [now, setNow] = useState(() => Date.now())
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), 60 * 1000)
    return () => clearInterval(id)
  }, [])
  return iso ? Math.max(0, Math.floor((now - new Date(iso).getTime()) / DAY_MS)) : null
}

function formatMoment(iso) {
  return new Date(iso).toLocaleString('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/** <input type="datetime-local"> value for a moment, in local time. */
function localInputValue(date = new Date()) {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`

/**
 * The days-without counter: one bad habit, never named. The number stays in
 * the corner of every screen; clicking it opens the details, the restart
 * button and the history.
 */
export default function DaysCounter({ corner = 'right' }) {
  const { data: fetched, state } = useFetch('/counter')
  const [counter, setCounter] = useState(null)
  const [open, setOpen] = useState(false)
  const data = counter ?? fetched
  const days = useDaysSince(data?.started_at)
  const root = useRef(null)

  // Close on Escape or a click outside.
  useEffect(() => {
    if (!open) return undefined
    const onKey = (e) => e.key === 'Escape' && setOpen(false)
    const onDown = (e) => root.current && !root.current.contains(e.target) && setOpen(false)
    window.addEventListener('keydown', onKey)
    window.addEventListener('pointerdown', onDown)
    return () => {
      window.removeEventListener('keydown', onKey)
      window.removeEventListener('pointerdown', onDown)
    }
  }, [open])

  if (state === 'loading' && !data) return null

  const started = Boolean(data?.started_at)

  return (
    <div className={`counter counter--${corner} ${open ? 'counter--open' : ''}`} ref={root}>
      <button
        type="button"
        className="counter__badge"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        aria-label={started ? `${plural(days, 'day')} without` : 'Days-without counter: not started'}
        title={started ? `${plural(days, 'day')} without` : 'Start the days-without counter'}
      >
        <span className="counter__number">{started ? days : '–'}</span>
        <span className="counter__unit">{days === 1 ? 'day' : 'days'}</span>
      </button>
      {open && <CounterPanel data={data} days={days} onChange={setCounter} />}
    </div>
  )
}

function CounterPanel({ data, days, onChange }) {
  const online = useOnline()
  const [confirming, setConfirming] = useState(false)
  const [when, setWhen] = useState(() => localInputValue())
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const started = Boolean(data?.started_at)

  async function write(action) {
    setBusy(true)
    setError(null)
    try {
      onChange(await action())
      setConfirming(false)
    } catch (err) {
      setError(Object.values(err.errors ?? {})[0]?.[0] ?? 'Could not save. Try again.')
    } finally {
      setBusy(false)
    }
  }

  const restart = () => write(() => resetCounter(new Date(when).toISOString()))
  const remove = (reset) => {
    if (window.confirm(`Remove the restart of ${formatMoment(reset.reset_at)}? Its run joins the next one.`)) {
      write(() => deleteCounterReset(reset.id))
    }
  }

  const whenField = (
    <label className="field">
      <span className="field__label">When</span>
      <input
        type="datetime-local"
        value={when}
        max={localInputValue()}
        onChange={(e) => setWhen(e.target.value)}
      />
    </label>
  )

  return (
    <div className="counter__panel" role="dialog" aria-label="Days-without counter">
      {!started ? (
        <>
          <p className="counter__big">Start counting</p>
          <p className="status">From now, or from the last time it happened.</p>
          {whenField}
          <button type="button" className="button button--primary" onClick={restart} disabled={busy || !online}>
            Start
          </button>
        </>
      ) : (
        <>
          <p className="counter__big">{plural(days, 'day')}</p>
          <p className="status">Since {formatMoment(data.started_at)}</p>
          <p className="counter__facts">
            <span>Best: {plural(data.best_days, 'day')}</span>
            <span>Restarts: {data.resets_count}</span>
          </p>

          {confirming ? (
            <div className="counter__confirm">
              {whenField}
              <div className="counter__actions">
                <button type="button" className="button" onClick={() => setConfirming(false)}>
                  Cancel
                </button>
                <button type="button" className="button button--danger" onClick={restart} disabled={busy || !online}>
                  Restart at 0
                </button>
              </div>
            </div>
          ) : (
            <button
              type="button"
              className="button"
              onClick={() => {
                setWhen(localInputValue())
                setConfirming(true)
              }}
              disabled={!online}
            >
              It happened: restart
            </button>
          )}

          <details className="counter__history">
            <summary>History</summary>
            <ul>
              {data.resets.map((reset) => (
                <li key={reset.id}>
                  <span>
                    {formatMoment(reset.reset_at)}
                    <span className="counter__run">
                      {reset.run_days === null ? ' · started counting' : ` · ended a ${plural(reset.run_days, 'day')} run`}
                    </span>
                  </span>
                  <button
                    type="button"
                    className="counter__remove"
                    onClick={() => remove(reset)}
                    disabled={busy || !online}
                    aria-label={`Remove ${formatMoment(reset.reset_at)}`}
                    title="Remove (logged by mistake)"
                  >
                    ×
                  </button>
                </li>
              ))}
            </ul>
          </details>
        </>
      )}
      {error && <p className="status status--error">{error}</p>}
      {!online && <p className="status">Offline: changes are disabled.</p>}
    </div>
  )
}
