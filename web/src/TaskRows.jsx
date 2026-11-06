import { useState } from 'react'
import Icon from './Icon.jsx'
import TaskEditor from './TaskEditor.jsx'
import { useTimer } from './timerContext.js'

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const DAY_MS = 24 * 60 * 60 * 1000

function startOfDay(date) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate())
}

function plural(n, word) {
  return `${n} ${word}${n === 1 ? '' : 's'}`
}

/** "Due: 20 October · 10 days left", "Due: … · today" or "3 days overdue" (SPEC §5.3). */
function describeDue(iso) {
  const due = new Date(iso)
  const days = Math.round((startOfDay(due) - startOfDay(new Date())) / DAY_MS)

  if (days < 0) return { text: `${plural(-days, 'day')} overdue`, overdue: true }
  if (days === 0 && due < new Date()) {
    const time = due.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
    return { text: `Was due at ${time} today`, overdue: true }
  }

  const date = due.toLocaleDateString('en-GB', { day: 'numeric', month: 'long' })
  const left = days === 0 ? 'today' : `${plural(days, 'day')} left`
  return { text: `Due: ${date} · ${left}`, overdue: false }
}

function describeRepeat(task) {
  if (task.repeat === 'daily') return 'Every day'
  if (task.repeat === 'weekly') {
    return (task.repeat_days ?? []).map((d) => WEEKDAYS[d - 1]).join(' · ')
  }
  return null
}

function PlayButton({ task }) {
  const { timer, start, expand } = useTimer()
  const isCurrent = timer?.task.id === task.id

  return (
    <button
      type="button"
      className={`task__play ${isCurrent && timer.is_running ? 'task__play--on' : ''}`}
      onClick={() => (isCurrent ? expand() : start(task))}
      aria-label={isCurrent ? `Open timer for ${task.title}` : `Start timer for ${task.title}`}
      title={isCurrent ? 'Open timer' : 'Start timer'}
    >
      <Icon name="play" size={32} />
    </button>
  )
}

function TaskRow({ task, onOpen }) {
  const due = task.due_at ? describeDue(task.due_at) : null
  const meta = due?.text ?? describeRepeat(task)
  const canPlay = !(task.status === 'done' && !task.is_recurring)

  return (
    <div className={`task ${task.is_done_today ? 'task--done' : ''}`}>
      <button
        type="button"
        className="task__main"
        title={`${task.sub_category.title} — click to edit`}
        onClick={onOpen}
      >
      <span
        className="task__bar"
        style={{ '--sub-color': task.sub_category.color }}
      />
      {task.is_recurring && (
        // Shows today's state; ticking arrives with SPEC §7 step 7.
        <span
          className={`task__check ${task.is_done_today ? 'task__check--on' : ''}`}
          aria-label={task.is_done_today ? 'Done today' : 'Not done today'}
        >
          {task.is_done_today && <Icon name="check" size={24} />}
        </span>
      )}
      <span className="task__body">
        <span className="task__title">{task.title}</span>
        {meta && (
          <span className={`task__meta ${due?.overdue ? 'task__meta--overdue' : ''}`}>
            {meta}
          </span>
        )}
      </span>
      </button>
      {canPlay && <PlayButton task={task} />}
    </div>
  )
}

/** A list of task rows; clicking a row swaps it for the inline editor. */
export default function TaskRows({ tasks, onSave, onDelete }) {
  const [editingId, setEditingId] = useState(null)

  return (
    <ul className="task-list">
      {tasks.map((task) => (
        <li key={task.id}>
          {editingId === task.id ? (
            <TaskEditor
              task={task}
              onSave={onSave}
              onDelete={onDelete}
              onClose={() => setEditingId(null)}
            />
          ) : (
            <TaskRow task={task} onOpen={() => setEditingId(task.id)} />
          )}
        </li>
      ))}
    </ul>
  )
}
