import { useState } from 'react'
import { useOnline } from './connectivity.js'
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
  const online = useOnline()
  const isCurrent = timer?.task.id === task.id

  return (
    <button
      type="button"
      className={`task__play ${isCurrent && timer.is_running ? 'task__play--on' : ''}`}
      onClick={() => (isCurrent ? expand() : start(task))}
      // Opening the running timer is local; starting one needs the server (§13).
      disabled={!isCurrent && !online}
      aria-label={isCurrent ? `Open timer for ${task.title}` : `Start timer for ${task.title}`}
      title={isCurrent ? 'Open timer' : 'Start timer'}
    >
      <Icon name="play" size={32} />
    </button>
  )
}

/** "5-day streak" for a daily habit, "3 in a row" for a weekly one; from 2 up. */
function describeStreak(task) {
  if (!task.is_recurring || !(task.streak >= 2)) return null
  return task.repeat === 'daily' ? `${task.streak}-day streak` : `${task.streak} in a row`
}

function HabitCheck({ task, onToggle }) {
  const [pending, setPending] = useState(false)
  const online = useOnline()
  const done = task.is_done_today

  async function toggle() {
    setPending(true)
    try {
      await onToggle(task)
    } finally {
      setPending(false)
    }
  }

  return (
    <button
      type="button"
      role="checkbox"
      aria-checked={done}
      aria-label={`${task.title}: ${done ? 'done today' : 'not done today'}`}
      className={`task__check ${done ? 'task__check--on' : ''}`}
      onClick={toggle}
      disabled={pending || !online}
    >
      {done && <Icon name="check" size={24} />}
    </button>
  )
}

function TaskRow({ task, onOpen, onToggleDone }) {
  const due = task.due_at ? describeDue(task.due_at) : null
  const meta = [due?.text ?? describeRepeat(task), describeStreak(task)]
    .filter(Boolean)
    .join(' · ')
  const canPlay = !(task.status === 'done' && !task.is_recurring)

  return (
    <div className={`task ${task.is_done_today ? 'task--done' : ''}`}>
      <span
        className="task__bar"
        style={{ '--sub-color': task.sub_category.color }}
      />
      {task.is_recurring && onToggleDone && (
        <HabitCheck task={task} onToggle={onToggleDone} />
      )}
      <button
        type="button"
        className="task__main"
        title={`${task.sub_category.title} — click to edit`}
        onClick={onOpen}
      >
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

/**
 * A list of task rows; clicking a row swaps it for the inline editor.
 * `onToggleDone(task)` ticks / unticks a habit for today.
 */
export default function TaskRows({ tasks, onSave, onDelete, onToggleDone }) {
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
            <TaskRow
              task={task}
              onOpen={() => setEditingId(task.id)}
              onToggleDone={onToggleDone}
            />
          )}
        </li>
      ))}
    </ul>
  )
}
