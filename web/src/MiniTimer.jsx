import Icon from './Icon.jsx'
import { useTimer } from './timerContext.js'
import { formatDuration, useElapsed } from './useElapsed.js'

/** Collapsed timer: a bar at the bottom that keeps counting. */
export default function MiniTimer() {
  const { timer, syncedAt, pause, resume, expand } = useTimer()
  const elapsed = useElapsed(timer, syncedAt)
  const over = timer.planned_seconds !== null && elapsed >= timer.planned_seconds

  return (
    <div className="mini" role="status">
      <span className="mini__bar" style={{ '--sub-color': timer.task.sub_category.color }} />
      <button type="button" className="mini__open" onClick={expand} title="Open focus mode">
        <span className="mini__title">{timer.task.title}</span>
        <span className={`mini__digits ${over ? 'mini__digits--over' : ''}`}>
          {formatDuration(elapsed)}
        </span>
      </button>
      <button
        type="button"
        className="icon-button"
        onClick={timer.is_running ? pause : resume}
        aria-label={timer.is_running ? 'Pause' : 'Resume'}
      >
        <Icon name={timer.is_running ? 'pause' : 'play'} />
      </button>
    </div>
  )
}
