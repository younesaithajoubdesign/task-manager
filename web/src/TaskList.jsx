import { useState } from 'react'
import TaskEditor from './TaskEditor.jsx'

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const DAY_MS = 24 * 60 * 60 * 1000

function startOfDay(date) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate())
}

function plural(n, word) {
  return `${n} ${word}${n === 1 ? '' : 's'}`
}

/** "Due: 20 October · 10 days left", "Due today" or "3 days overdue" (SPEC §5.3). */
function describeDue(iso) {
  const due = new Date(iso)
  const days = Math.round((startOfDay(due) - startOfDay(new Date())) / DAY_MS)

  if (days < 0) return { text: `${plural(-days, 'day')} overdue`, overdue: true }

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

function TaskRow({ task, onOpen }) {
  const due = task.due_at ? describeDue(task.due_at) : null
  const meta = due?.text ?? describeRepeat(task)

  return (
    <button
      type="button"
      className={`task ${task.is_done_today ? 'task--done' : ''}`}
      title={`${task.sub_category.title} — click to edit`}
      onClick={onOpen}
    >
      <span
        className="task__bar"
        style={{ '--sub-color': task.sub_category.color }}
      />
      <span className="task__body">
        <span className="task__title">{task.title}</span>
        {meta && (
          <span className={`task__meta ${due?.overdue ? 'task__meta--overdue' : ''}`}>
            {meta}
          </span>
        )}
      </span>
    </button>
  )
}

export default function TaskList({ tasks, state, onSave, onDelete }) {
  const [editingId, setEditingId] = useState(null)

  if (state === 'loading') return <p className="status">Loading tasks…</p>
  if (state === 'error') {
    return (
      <p className="status status--error">
        Could not reach the API. Is <code>php artisan serve</code> running?
      </p>
    )
  }
  if (tasks.length === 0) {
    return <p className="status">Nothing open. Press N to add a task.</p>
  }

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
