import { useEffect, useState } from 'react'

/**
 * Seconds to display for a timer: the server's tracked time plus the time
 * elapsed locally since it arrived (performance.now, immune to clock changes).
 * Ticks once a second while running.
 */
export function useElapsed(timer, syncedAt) {
  const [now, setNow] = useState(() => performance.now())

  useEffect(() => {
    if (!timer?.is_running) return undefined
    const id = setInterval(() => setNow(performance.now()), 1000)
    return () => clearInterval(id)
  }, [timer?.is_running])

  if (!timer) return 0
  const local = timer.is_running ? Math.max(0, (now - syncedAt) / 1000) : 0
  return Math.floor(timer.tracked_seconds_today + local)
}

export function formatDuration(totalSeconds) {
  const h = Math.floor(totalSeconds / 3600)
  const m = Math.floor((totalSeconds % 3600) / 60)
  const s = totalSeconds % 60
  const pad = (n) => String(n).padStart(2, '0')
  return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`
}
