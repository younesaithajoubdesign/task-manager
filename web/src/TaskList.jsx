import { useEffect, useState } from 'react'
import { apiGet } from './api.js'

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

function TaskRow({ task }) {
  const due = task.due_at ? describeDue(task.due_at) : null
  const meta = due?.text ?? describeRepeat(task)

  return (
    <li
      className={`task ${task.is_done_today ? 'task--done' : ''}`}
      title={task.sub_category.title}
    >
      <span
        className="task__bar"
        style={{ '--sub-color': task.sub_category.color }}
      />
      <div className="task__body">
        <p className="task__title">{task.title}</p>
        {meta && (
          <p className={`task__meta ${due?.overdue ? 'task__meta--overdue' : ''}`}>
            {meta}
          </p>
        )}
      </div>
    </li>
  )
}

export default function TaskList() {
  const [tasks, setTasks] = useState([])
  const [state, setState] = useState('loading') // loading | ready | error

  useEffect(() => {
    const controller = new AbortController()

    apiGet('/tasks', { signal: controller.signal })
      .then(({ data }) => {
        setTasks(data)
        setState('ready')
      })
      .catch((err) => {
        if (err.name !== 'AbortError') setState('error')
      })

    return () => controller.abort()
  }, [])

  if (state === 'loading') return <p className="status">Loading tasks…</p>
  if (state === 'error') {
    return (
      <p className="status status--error">
        Could not reach the API. Is <code>php artisan serve</code> running?
      </p>
    )
  }
  if (tasks.length === 0) return <p className="status">Nothing open. Enjoy.</p>

  return (
    <ul className="task-list">
      {tasks.map((task) => (
        <TaskRow key={task.id} task={task} />
      ))}
    </ul>
  )
}
