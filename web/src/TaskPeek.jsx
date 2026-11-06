import { useLayoutEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const IMPORTANCE = { high: 'High', medium: 'Medium', low: 'Low' }
const STATUS = { todo: 'To do', doing: 'In progress', done: 'Done' }
const GAP = 6

function formatDue(iso) {
  const due = new Date(iso)
  const date = due.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })
  const time = due.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
  return time === '00:00' ? date : `${date}, ${time}`
}

function formatMinutes(minutes) {
  if (minutes < 60) return `${minutes} min`
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return m ? `${h} h ${m} min` : `${h} h`
}

function formatRepeat(task) {
  if (task.repeat === 'daily') return 'Every day'
  if (task.repeat === 'weekly') return (task.repeat_days ?? []).map((d) => WEEKDAYS[d - 1]).join(', ')
  return null
}

/** The rows of the card, in reading order; empty fields are left out. */
function details(task) {
  return [
    ['Category', task.sub_category?.title],
    ['Due', task.due_at && formatDue(task.due_at)],
    ['Repeats', formatRepeat(task)],
    ['Planned', task.type === 'timed' && task.planned_minutes ? formatMinutes(task.planned_minutes) : task.type === 'open' ? 'Open-ended' : null],
    ['Tracked today', task.tracked_seconds_today > 0 ? formatMinutes(Math.max(1, Math.round(task.tracked_seconds_today / 60))) : null],
    ['Importance', IMPORTANCE[task.importance]],
    ['Status', task.is_recurring ? (task.is_done_today ? 'Done today' : 'Not done today') : STATUS[task.status]],
    ['List', task.open_items_count > 0 ? `${task.open_items_count} left` : null],
  ].filter(([, value]) => value)
}

/**
 * Hover card on a task row: due date, planned time, notes… at a glance.
 * Placed under the row (above it near the bottom of the window), in a portal
 * so the row's overflow: hidden does not cut it. Shown by useTaskPeek.
 */
export default function TaskPeek({ task, anchor }) {
  const card = useRef(null)
  const [position, setPosition] = useState(null)

  useLayoutEffect(() => {
    if (!anchor || !card.current) return
    const row = anchor.getBoundingClientRect()
    const { offsetWidth: width, offsetHeight: height } = card.current
    const fitsBelow = row.bottom + GAP + height <= window.innerHeight - GAP
    setPosition({
      top: fitsBelow ? row.bottom + GAP : Math.max(GAP, row.top - GAP - height),
      left: Math.min(Math.max(GAP, row.left), window.innerWidth - width - GAP),
    })
  }, [anchor, task])

  if (!anchor) return null

  return createPortal(
    <div
      ref={card}
      className="task-peek"
      role="tooltip"
      style={position ?? { visibility: 'hidden', top: 0, left: 0 }}
    >
      <p className="task-peek__title">{task.title}</p>
      <dl className="task-peek__details">
        {details(task).map(([label, value]) => (
          <div key={label} className="task-peek__row">
            <dt>{label}</dt>
            <dd>{value}</dd>
          </div>
        ))}
      </dl>
      {task.notes && <p className="task-peek__notes">{task.notes}</p>}
      {task.link && <p className="task-peek__link">{task.link}</p>}
      <p className="task-peek__hint">Double-click to edit</p>
    </div>,
    document.body,
  )
}
