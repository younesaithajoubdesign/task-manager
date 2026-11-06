import { useCallback, useEffect, useMemo, useState } from 'react'
import { TimerContext } from './timerContext.js'
import { completeTask, getRunningTimer, timerAction } from './tasksApi.js'

const COLLAPSED_KEY = 'timer.collapsed'

function readCollapsed() {
  try {
    return localStorage.getItem(COLLAPSED_KEY) === '1'
  } catch {
    return false
  }
}

function storeCollapsed(collapsed) {
  try {
    localStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0')
  } catch {
    // Storage blocked: the app reopens in focus mode, which is fine.
  }
}

/**
 * Holds the current timer (SPEC §5.4). The server is the truth:
 * `timer.tracked_seconds_today` comes from time_entries.started_at, and the
 * display only adds the time elapsed locally since that value arrived.
 * On mount, and whenever the tab becomes visible again, it re-reads
 * GET /api/timer/running — that is what makes a refresh harmless.
 */
export default function TimerProvider({ children }) {
  const [timer, setTimer] = useState(null)
  const [syncedAt, setSyncedAt] = useState(0)
  const [mode, setMode] = useState('hidden') // hidden | focus | mini
  const [version, setVersion] = useState(0) // bumps after every write, lists re-read

  const apply = useCallback((next) => {
    setTimer(next)
    setSyncedAt(performance.now())
  }, [])

  const sync = useCallback(
    () =>
      getRunningTimer().then(
        (running) => {
          if (running) {
            apply(running)
            setMode((m) => (m === 'hidden' ? (readCollapsed() ? 'mini' : 'focus') : m))
          } else {
            // Nothing runs on the server: keep a paused timer on screen,
            // drop one that was running (it was stopped elsewhere).
            setTimer((current) => (current?.is_running ? null : current))
          }
        },
        () => {},
      ),
    [apply],
  )

  useEffect(() => {
    sync()
    const onVisible = () => document.visibilityState === 'visible' && sync()
    document.addEventListener('visibilitychange', onVisible)
    return () => document.removeEventListener('visibilitychange', onVisible)
  }, [sync])

  const run = useCallback(
    async (request, { nextMode } = {}) => {
      const next = await request()
      setVersion((v) => v + 1)
      if (next !== undefined) apply(next)
      if (nextMode) setMode(nextMode)
      return next
    },
    [apply],
  )

  const actions = useMemo(
    () => ({
      start: (task) => {
        storeCollapsed(false)
        return run(() => timerAction(task.id, 'start'), { nextMode: 'focus' })
      },
      pause: () => run(() => timerAction(timer.task.id, 'pause')),
      resume: () => run(() => timerAction(timer.task.id, 'resume')),
      reset: () => run(() => timerAction(timer.task.id, 'reset')),
      /** Close: stop the timer (time is kept) and leave focus mode. */
      close: async () => {
        await run(() => timerAction(timer.task.id, 'stop'), { nextMode: 'hidden' })
        setTimer(null)
      },
      complete: async () => {
        await run(async () => {
          await completeTask(timer.task.id)
        }, { nextMode: 'hidden' })
        setTimer(null)
      },
      collapse: () => {
        storeCollapsed(true)
        setMode('mini')
      },
      expand: () => {
        storeCollapsed(false)
        setMode('focus')
      },
    }),
    [run, timer],
  )

  const value = useMemo(
    () => ({ timer, syncedAt, mode, version, sync, ...actions }),
    [timer, syncedAt, mode, version, sync, actions],
  )

  return <TimerContext.Provider value={value}>{children}</TimerContext.Provider>
}
