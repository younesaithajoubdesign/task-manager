import LoadState from './LoadState.jsx'
import QuickCapture from './QuickCapture.jsx'
import TaskRows from './TaskRows.jsx'
import { createTask, deleteTask, updateTask } from './tasksApi.js'
import { useFetch } from './useFetch.js'
import { useTimer } from './timerContext.js'

function Section({ title, count, tone, children }) {
  return (
    <section className={`today__section ${tone ? `today__section--${tone}` : ''}`}>
      <h2 className="today__heading">
        {title}
        {count != null && <span className="today__count">{count}</span>}
      </h2>
      {children}
    </section>
  )
}

/**
 * SPEC §5.2 — the opening screen: Overdue, Due today, Habits.
 * Tasks captured here are due today, so they land on this screen.
 */
export default function TodayView() {
  const { version } = useTimer()
  const { data, state, reload } = useFetch('/today', version)

  if (state !== 'ready') return <LoadState state={state} />

  const afterWrite = (write) => async (...args) => {
    await write(...args)
    await reload()
  }
  const rows = (tasks) => (
    <TaskRows
      tasks={tasks}
      onSave={afterWrite(updateTask)}
      onDelete={afterWrite(deleteTask)}
    />
  )

  const { overdue, due_today: dueToday, habits } = data
  const nothingDue = overdue.length === 0 && dueToday.length === 0
  const capture = (
    <QuickCapture
      onCreate={afterWrite(createTask)}
      defaults={{ due_at: data.date }}
      placeholder="Add something for today…"
      autoFocus={nothingDue}
    />
  )

  const habitsDone = habits.filter((t) => t.is_done_today).length

  return (
    <>
      {/* Same element in both states, so the field keeps focus when the
          first task of the day replaces the empty state. */}
      <div className={nothingDue ? 'empty' : undefined}>
        {nothingDue && (
          <>
            <p className="empty__title">Nothing is due today.</p>
            <p className="empty__text">
              {habits.length > 0
                ? 'Only your habits below. Anything else on your mind? Type it here, press Enter.'
                : 'Anything on your mind? Type it here, press Enter.'}
            </p>
          </>
        )}
        {capture}
      </div>

      {overdue.length > 0 && (
        <Section title="Overdue" count={overdue.length} tone="danger">
          {rows(overdue)}
        </Section>
      )}

      {dueToday.length > 0 && (
        <Section title="Due today" count={dueToday.length}>
          {rows(dueToday)}
        </Section>
      )}

      {habits.length > 0 && (
        <Section title={`Habits · ${habitsDone}/${habits.length} done`}>
          {rows(habits)}
        </Section>
      )}
    </>
  )
}
