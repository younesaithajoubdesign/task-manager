import { useState } from 'react'
import LoadState from './LoadState.jsx'
import { useFetch } from './useFetch.js'

const RANGES = [7, 30, 90]

/** Local Y-m-d (the API counts days the same way). */
function isoDate(date) {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function rangeQuery(days) {
  const to = new Date()
  const from = new Date(to)
  from.setDate(to.getDate() - (days - 1))
  return `/stats?from=${isoDate(from)}&to=${isoDate(to)}`
}

/** 2h 05m, 45m, 0m — stats read in hours, not seconds. */
function formatHours(seconds) {
  if (seconds > 0 && seconds < 60) return '<1m'
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  return h > 0 ? `${h}h ${String(m).padStart(2, '0')}m` : `${m}m`
}

function dayLabel(date, options) {
  return new Date(`${date}T12:00:00`).toLocaleDateString('en-GB', options)
}

function Card({ title, aside, children }) {
  return (
    <section className="stats__card">
      <h2 className="stats__heading">
        {title}
        {aside && <span className="stats__aside">{aside}</span>}
      </h2>
      {children}
    </section>
  )
}

/**
 * Seconds per day: one bar each, scaled to the busiest day. Tapping a bar
 * shows its day and value (no hover on a phone), tapping it again hides it.
 */
function DayChart({ days }) {
  const max = Math.max(...days.map((d) => d.seconds), 1)
  const labelEach = days.length <= 14
  const [pickedDate, setPickedDate] = useState(null)
  const picked = days.find((d) => d.date === pickedDate) ?? null

  return (
    <>
      <div className={`bars ${days.length > 31 ? 'bars--dense' : ''}`}>
        {days.map((d) => (
          <div
            key={d.date}
            className={`bars__slot ${d.date === pickedDate ? 'bars__slot--picked' : ''}`}
            title={`${dayLabel(d.date, { weekday: 'short', day: 'numeric', month: 'short' })}: ${formatHours(d.seconds)}`}
            onClick={() => setPickedDate(d.date === pickedDate ? null : d.date)}
          >
            <div className="bars__bar" style={{ height: `${(d.seconds / max) * 100}%` }} />
          </div>
        ))}
      </div>
      <div className={`bars__labels ${labelEach && !picked ? 'bars__labels--each' : ''}`}>
        {picked
          ? <span className="bars__picked">{dayLabel(picked.date, { weekday: 'long', day: 'numeric', month: 'long' })}: {formatHours(picked.seconds)}</span>
          : labelEach
          ? days.map((d) => <span key={d.date}>{dayLabel(d.date, { weekday: 'short' })}</span>)
          : [days[0], days[days.length - 1]].map((d) => (
              <span key={d.date}>{dayLabel(d.date, { day: 'numeric', month: 'short' })}</span>
            ))}
      </div>
    </>
  )
}

/** Seconds per category: horizontal bars, busiest first. */
function CategoryChart({ categories }) {
  const tracked = categories.filter((c) => c.seconds > 0)
  const max = Math.max(...tracked.map((c) => c.seconds), 1)

  return (
    <ul className="meter-list">
      {tracked.map((c) => (
        <li key={c.id} className="meter">
          <span className="meter__label">{c.title}</span>
          <span className="meter__track">
            <span className="meter__fill" style={{ width: `${(c.seconds / max) * 100}%` }} />
          </span>
          <span className="meter__value">{formatHours(c.seconds)}</span>
        </li>
      ))}
    </ul>
  )
}

const DAY_STATE_LABEL = { done: 'done', missed: 'missed', open: 'not done yet', off: 'not scheduled' }

function HabitRow({ habit, dates }) {
  const days = habit.streak === 1 ? 'day' : 'days'

  return (
    <li className="habit-stat" style={{ '--sub-color': habit.color }}>
      <div className="habit-stat__head">
        <span className="habit-stat__title">{habit.title}</span>
        <span className="habit-stat__numbers">
          <strong>{habit.streak}</strong> {days} streak · {habit.completed_count}/{habit.scheduled_count} done
        </span>
      </div>
      <div className="dots" aria-hidden="true">
        {habit.days.map((state, i) => (
          <span
            key={dates[i]}
            className={`dots__dot dots__dot--${state}`}
            title={`${dayLabel(dates[i], { weekday: 'short', day: 'numeric', month: 'short' })}: ${DAY_STATE_LABEL[state]}`}
          />
        ))}
      </div>
    </li>
  )
}

/**
 * Stats (SPEC §4, built last): time tracked per day and per category,
 * and a streak per habit, over the last 7, 30 or 90 days.
 */
export default function StatsView() {
  const [range, setRange] = useState(30)
  const { data, state } = useFetch(rangeQuery(range))

  return (
    <div className="stats">
      <div className="filters" role="group" aria-label="Period">
        {RANGES.map((days) => (
          <button
            key={days}
            type="button"
            className={`chip ${range === days ? 'chip--on' : ''}`}
            aria-pressed={range === days}
            onClick={() => setRange(days)}
          >
            {days} days
          </button>
        ))}
      </div>

      {state !== 'ready' ? (
        <LoadState state={state} />
      ) : (
        <>
          <Card
            title="Time tracked"
            aside={`${formatHours(data.total_seconds)} · ${formatHours(Math.round(data.total_seconds / data.days.length))} a day`}
          >
            <DayChart days={data.days} />
          </Card>

          <Card title="By category">
            {data.total_seconds === 0 ? (
              <p className="status">Nothing tracked in this period. Start a timer on a task.</p>
            ) : (
              <CategoryChart categories={data.categories} />
            )}
          </Card>

          <Card title="Habits">
            {data.habits.length === 0 ? (
              <p className="status">No habits yet. Give a task a repeat to track it here.</p>
            ) : (
              <ul className="habit-stats">
                {data.habits.map((habit) => (
                  <HabitRow key={habit.id} habit={habit} dates={data.days.map((d) => d.date)} />
                ))}
              </ul>
            )}
          </Card>
        </>
      )}
    </div>
  )
}
