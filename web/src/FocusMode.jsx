import { useEffect, useRef } from 'react'
import Checklist from './Checklist.jsx'
import { useOnline } from './connectivity.js'
import Icon from './Icon.jsx'
import { useTimer } from './timerContext.js'
import { formatDuration, useElapsed } from './useElapsed.js'

/** A short, soft beep when the planned duration is reached. */
function beep() {
  try {
    const audio = new AudioContext()
    const osc = audio.createOscillator()
    const gain = audio.createGain()
    osc.frequency.value = 880
    gain.gain.setValueAtTime(0.15, audio.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + 0.6)
    osc.connect(gain).connect(audio.destination)
    osc.start()
    osc.stop(audio.currentTime + 0.6)
    osc.onended = () => audio.close()
  } catch {
    // No audio available: the colour change still signals the target.
  }
}

function RoundButton({ icon, label, onClick, primary, disabled = false }) {
  return (
    <button
      type="button"
      className={`round ${primary ? 'round--primary' : ''}`}
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      title={label}
    >
      <Icon name={icon} size={32} />
    </button>
  )
}

/**
 * SPEC §5.4 — full screen, black. Past the planned duration the timer keeps
 * counting and the digits turn primary; it never stops by itself.
 */
export default function FocusMode() {
  const { timer, syncedAt, pause, resume, close, complete, reset, collapse } = useTimer()
  const elapsed = useElapsed(timer, syncedAt)
  // The timer is online-only (§13): offline the digits keep ticking from the
  // server's started_at, but every control that needs the server is disabled.
  const offline = !useOnline()
  const planned = timer.planned_seconds
  const overTarget = planned !== null && elapsed >= planned

  // Beep once when the target is crossed while watching, not on reload.
  const wasOver = useRef(overTarget)
  useEffect(() => {
    if (overTarget && !wasOver.current && timer.is_running) beep()
    wasOver.current = overTarget
  }, [overTarget, timer.is_running])

  useEffect(() => {
    function onKeyDown(event) {
      if (event.key === 'Escape') collapse()
      if (event.key === ' ' && event.target === document.body && !offline) {
        event.preventDefault()
        if (timer.is_running) pause()
        else resume()
      }
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [collapse, pause, resume, timer.is_running, offline])

  function confirmReset() {
    if (window.confirm("Reset today's time for this task to zero?")) reset()
  }

  const { task } = timer

  return (
    <div className="focus" role="dialog" aria-modal="true" aria-label={`Timer: ${task.title}`}>
      <div className="focus__corner">
        <RoundButton icon="close" label="Close (stops the timer)" onClick={close} disabled={offline} />
        <RoundButton icon="minimize" label="Collapse (keeps running)" onClick={collapse} />
      </div>

      <div className="focus__card">
        <p className="focus__task">
          <span className="focus__dot" style={{ '--sub-color': task.sub_category.color }} />
          {task.title}
        </p>

        <p
          className={`focus__digits ${overTarget ? 'focus__digits--over' : ''} ${timer.is_running ? '' : 'focus__digits--paused'}`}
          aria-live="off"
        >
          {formatDuration(elapsed)}
        </p>

        <p className="focus__target">
          {!timer.is_running
            ? planned !== null
              ? `Paused · target ${task.planned_minutes} min`
              : 'Paused'
            : planned === null
              ? 'Tracked today'
              : overTarget
                ? `Target of ${task.planned_minutes} min reached — still counting`
                : `Target ${task.planned_minutes} min`}
        </p>

        <div className="focus__buttons">
          {timer.is_running ? (
            <RoundButton icon="pause" label="Pause" onClick={pause} disabled={offline} />
          ) : (
            <RoundButton icon="play" label="Resume" onClick={resume} disabled={offline} />
          )}
          <RoundButton icon="stop" label="Stop" onClick={close} disabled={offline} />
          <RoundButton icon="check" label="Complete" onClick={complete} primary disabled={offline} />
          <RoundButton icon="replay" label="Reset to zero" onClick={confirmReset} disabled={offline} />
        </div>
      </div>

      {/* What to watch, read or write on in this session, with its links. */}
      <div className="focus__list">
        <Checklist taskId={task.id} title="For this session" />
      </div>
    </div>
  )
}
