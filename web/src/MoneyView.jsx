import { useMemo, useRef, useState } from 'react'
import { useOnline } from './connectivity.js'
import Icon from './Icon.jsx'
import LoadState from './LoadState.jsx'
import { createMoneyEntry, deleteMoneyEntry, updateMoneyEntry } from './moneyApi.js'
import { useFetch } from './useFetch.js'
import { useOpenGesture } from './useOpenGesture.js'

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

/** Local Y-m-d (the API counts days the same way). */
function isoDate(date) {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function daysAgo(n) {
  const date = new Date()
  date.setDate(date.getDate() - n)
  return date
}

const PERIODS = [
  { key: '7', label: '7 days', from: () => daysAgo(6) },
  { key: '30', label: '30 days', from: () => daysAgo(29) },
  { key: 'month', label: 'This month', from: () => new Date(new Date().getFullYear(), new Date().getMonth(), 1) },
  { key: 'year', label: 'This year', from: () => new Date(new Date().getFullYear(), 0, 1) },
  { key: '12m', label: '12 months', from: () => new Date(new Date().getFullYear(), new Date().getMonth() - 11, 1) },
]

/** ?from=&to= for a period ending today. */
function periodQuery(period) {
  return `from=${isoDate(period.from())}&to=${isoDate(new Date())}`
}

/** 1 500 dh, 11,50 dh: Moroccan / French grouping and decimal comma. */
function formatDh(amount, { sign = false } = {}) {
  const text = Math.abs(amount).toLocaleString('fr-FR', {
    minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
    maximumFractionDigits: 2,
  })
  const prefix = amount < 0 ? '−' : sign && amount > 0 ? '+' : ''
  return `${prefix}${text} dh`
}

const percent = (ratio) => `${Math.round(ratio * 100)} %`

function dayLabel(date, options) {
  return new Date(`${date}T12:00:00`).toLocaleDateString('en-GB', options)
}

function monthLabel(key, options = { month: 'short' }) {
  return new Date(`${key}-15T12:00:00`).toLocaleDateString('en-GB', options)
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

function Figure({ label, value, tone }) {
  return (
    <div className="money-figure">
      <span className="money-figure__label">{label}</span>
      <span className={`money-figure__value ${tone ? `money-figure__value--${tone}` : ''}`}>{value}</span>
    </div>
  )
}

/* ---- Entry form -------------------------------------------------------- */

const emptyForm = () => ({ type: 'expense', amount: '', title: '', category: '', occurred_on: isoDate(new Date()) })

/**
 * Type in what came in or went out. After "Add", the type, category and date
 * stay, and the cursor goes back to the amount: several items of one
 * shopping trip go in one after the other.
 */
function EntryForm({ categories, onSaved, initial, onCancel, onDelete }) {
  const online = useOnline()
  const [form, setForm] = useState(initial ?? emptyForm())
  const [errors, setErrors] = useState({})
  const [busy, setBusy] = useState(false)
  const amountRef = useRef(null)
  const editing = Boolean(initial?.id)
  const set = (field) => (event) => setForm((f) => ({ ...f, [field]: event.target.value }))

  async function submit(event) {
    event.preventDefault()
    setBusy(true)
    setErrors({})
    try {
      const payload = { ...form, category: form.category.trim() || null }
      await (editing ? updateMoneyEntry(initial.id, payload) : createMoneyEntry(payload))
      if (!editing) {
        setForm((f) => ({ ...f, amount: '', title: '' }))
        amountRef.current?.focus()
      }
      onSaved()
    } catch (err) {
      setErrors(err.errors ?? { amount: [err.message] })
    } finally {
      setBusy(false)
    }
  }

  const error = (field) => errors[field] && <span className="field__error">{errors[field][0]}</span>

  return (
    <form className={`money-form ${editing ? 'money-form--edit' : ''}`} onSubmit={submit}>
      <fieldset className="offline-lock" disabled={!online}>
        <div className="money-form__type" role="group" aria-label="Type">
          {['expense', 'income'].map((type) => (
            <button
              key={type}
              type="button"
              className={`chip ${form.type === type ? 'chip--on' : ''} money-form__type--${type}`}
              aria-pressed={form.type === type}
              onClick={() => setForm((f) => ({ ...f, type }))}
            >
              {type === 'expense' ? 'Expense' : 'Income'}
            </button>
          ))}
        </div>
        <div className="money-form__fields">
          <label className="field money-form__amount">
            <span className="field__label">Amount (dh)</span>
            <input
              ref={amountRef}
              value={form.amount}
              onChange={set('amount')}
              inputMode="decimal"
              placeholder="25"
              autoComplete="off"
              required
            />
            {error('amount')}
          </label>
          <label className="field money-form__title">
            <span className="field__label">What</span>
            <input
              value={form.title}
              onChange={set('title')}
              placeholder={form.type === 'expense' ? 'Triangle cheese 16 pcs' : 'Last week'}
              maxLength={255}
              required
            />
            {error('title')}
          </label>
          <label className="field">
            <span className="field__label">Category</span>
            <input
              value={form.category}
              onChange={set('category')}
              list="money-categories"
              placeholder="Food"
              maxLength={60}
              autoComplete="off"
            />
            {error('category')}
          </label>
          <label className="field">
            <span className="field__label">Date</span>
            <input type="date" value={form.occurred_on} onChange={set('occurred_on')} required />
            {error('occurred_on')}
          </label>
        </div>
        <datalist id="money-categories">
          {categories.map((c) => (
            <option key={c} value={c} />
          ))}
        </datalist>
        <div className="money-form__actions">
          {editing && (
            <button type="button" className="icon-button icon-button--danger" onClick={onDelete} aria-label="Delete" title="Delete">
              <Icon name="delete" />
            </button>
          )}
          <span className="editor__spacer" />
          {editing && (
            <button type="button" className="button" onClick={onCancel}>
              Cancel
            </button>
          )}
          <button type="submit" className="button button--primary" disabled={busy}>
            {editing ? 'Save' : 'Add'}
          </button>
        </div>
      </fieldset>
    </form>
  )
}

/* ---- Entries list ------------------------------------------------------ */

function EntryRow({ entry, onOpen }) {
  const { pressing, handlers } = useOpenGesture(onOpen)
  return (
    <li className={`money-row ${pressing ? 'money-row--pressing' : ''}`}>
      <button type="button" className="money-row__main" {...handlers} aria-description="Double-click, or press and hold, to edit">
        <span className="money-row__title">{entry.title}</span>
        {entry.category && <span className="money-row__category">{entry.category}</span>}
      </button>
      <span className={`money-row__amount money-row__amount--${entry.type}`}>
        {formatDh(entry.type === 'income' ? entry.amount : -entry.amount, { sign: true })}
      </span>
    </li>
  )
}

function EntryList({ entries, categories, onChanged }) {
  const [editingId, setEditingId] = useState(null)

  const days = useMemo(() => {
    const byDay = new Map()
    for (const entry of entries) {
      if (!byDay.has(entry.occurred_on)) byDay.set(entry.occurred_on, [])
      byDay.get(entry.occurred_on).push(entry)
    }
    return [...byDay.entries()]
  }, [entries])

  async function remove(entry) {
    if (!window.confirm(`Delete "${entry.title}" (${formatDh(entry.amount)})?`)) return
    await deleteMoneyEntry(entry.id)
    setEditingId(null)
    onChanged()
  }

  if (entries.length === 0) return <p className="status">Nothing in this period yet.</p>

  return (
    <div className="money-days">
      {days.map(([date, dayEntries]) => {
        const net = dayEntries.reduce((sum, e) => sum + (e.type === 'income' ? e.amount : -e.amount), 0)
        return (
          <section key={date} className="money-day">
            <h3 className="money-day__head">
              <span>{dayLabel(date, { weekday: 'long', day: 'numeric', month: 'long' })}</span>
              <span className="stats__aside">{formatDh(net, { sign: true })}</span>
            </h3>
            <ul className="money-day__list">
              {dayEntries.map((entry) =>
                editingId === entry.id ? (
                  <li key={entry.id}>
                    <EntryForm
                      categories={categories}
                      initial={{ ...entry, amount: String(entry.amount), category: entry.category ?? '' }}
                      onSaved={() => {
                        setEditingId(null)
                        onChanged()
                      }}
                      onCancel={() => setEditingId(null)}
                      onDelete={() => remove(entry)}
                    />
                  </li>
                ) : (
                  <EntryRow key={entry.id} entry={entry} onOpen={() => setEditingId(entry.id)} />
                ),
              )}
            </ul>
          </section>
        )
      })}
    </div>
  )
}

/* ---- Charts ------------------------------------------------------------ */

/** Spending per day (or month): one bar each; tap a bar for its numbers. */
function SeriesChart({ series }) {
  const { unit, points } = series
  const max = Math.max(...points.map((p) => p.expense), 1)
  const [pickedKey, setPickedKey] = useState(null)
  const picked = points.find((p) => p.key === pickedKey) ?? null
  const label = (p, long = false) =>
    unit === 'day'
      ? dayLabel(p.key, long ? { weekday: 'long', day: 'numeric', month: 'long' } : { day: 'numeric', month: 'short' })
      : monthLabel(p.key, long ? { month: 'long', year: 'numeric' } : { month: 'short' })

  return (
    <>
      <div className={`bars ${points.length > 31 ? 'bars--dense' : ''}`}>
        {points.map((p) => (
          <div
            key={p.key}
            className={`bars__slot ${p.key === pickedKey ? 'bars__slot--picked' : ''}`}
            title={`${label(p)}: spent ${formatDh(p.expense)}${p.income ? `, received ${formatDh(p.income)}` : ''}`}
            onClick={() => setPickedKey(p.key === pickedKey ? null : p.key)}
          >
            <div className="bars__bar bars__bar--expense" style={{ height: `${(p.expense / max) * 100}%` }} />
          </div>
        ))}
      </div>
      <div className="bars__labels">
        {picked ? (
          <span className="bars__picked">
            {label(picked, true)}: spent {formatDh(picked.expense)}
            {picked.income > 0 && `, received ${formatDh(picked.income)}`}
          </span>
        ) : (
          [points[0], points[points.length - 1]].map((p) => <span key={p.key}>{label(p)}</span>)
        )}
      </div>
    </>
  )
}

/** Last 12 months: income and expense side by side. */
function MonthsChart({ months }) {
  const max = Math.max(...months.flatMap((m) => [m.income, m.expense]), 1)
  return (
    <>
      <div className="bars bars--pairs">
        {months.map((m) => (
          <div key={m.month} className="bars__pair" title={`${monthLabel(m.month, { month: 'long', year: 'numeric' })}: in ${formatDh(m.income)}, out ${formatDh(m.expense)}`}>
            <div className="bars__bar bars__bar--income" style={{ height: `${(m.income / max) * 100}%` }} />
            <div className="bars__bar bars__bar--expense" style={{ height: `${(m.expense / max) * 100}%` }} />
          </div>
        ))}
      </div>
      <div className="bars__labels bars__labels--each">
        {months.map((m) => (
          <span key={m.month}>{monthLabel(m.month).slice(0, 1)}</span>
        ))}
      </div>
      <p className="money-legend">
        <span className="money-legend__income">In</span>
        <span className="money-legend__expense">Out</span>
      </p>
    </>
  )
}

function Meters({ rows }) {
  const max = Math.max(...rows.map((r) => r.value), 1)
  return (
    <ul className="meter-list">
      {rows.map((r) => (
        <li key={r.label} className="meter meter--money">
          <span className="meter__label">{r.label}</span>
          <span className="meter__track">
            <span className="meter__fill" style={{ width: `${(r.value / max) * 100}%` }} />
          </span>
          <span className="meter__value">{r.text}</span>
        </li>
      ))}
    </ul>
  )
}

/* ---- The screen -------------------------------------------------------- */

/**
 * Personal money: what came in, what went out, and what it says. Amounts are
 * typed in as they happen; the statistics follow the chosen period.
 */
export default function MoneyView() {
  const [periodKey, setPeriodKey] = useState('30')
  const [version, setVersion] = useState(0)
  const period = PERIODS.find((p) => p.key === periodKey)
  const query = periodQuery(period)
  const stats = useFetch(`/money/stats?${query}`, version)
  const list = useFetch(`/money?${query}`, version)
  const refresh = () => setVersion((v) => v + 1)
  const categories = list.data ? (list.meta?.categories ?? []) : []

  const s = stats.data
  const w = s?.window

  return (
    <div className="stats money">
      {s && (
        <section className="money-top">
          <div className="money-balance">
            <span className="money-figure__label">Left</span>
            <span className={`money-balance__value ${s.balance < 0 ? 'money-figure__value--out' : ''}`}>{formatDh(s.balance)}</span>
            <span className="status">
              {s.runway_days !== null
                ? `About ${s.runway_days} days at your pace (${formatDh(s.pace_per_day)} a day over 30 days)`
                : s.balance > 0
                  ? 'No spending in the last 30 days.'
                  : 'Add an income to start.'}
            </span>
          </div>
          <div className="money-figures">
            <Figure label="Spent today" value={formatDh(s.calendar.today.expense)} />
            <Figure label="This week" value={formatDh(s.calendar.week.expense)} />
            <Figure label="This month" value={formatDh(s.calendar.month.expense)} />
            <Figure label="This year" value={formatDh(s.calendar.year.expense)} />
          </div>
        </section>
      )}

      <Card title="Add">
        <EntryForm categories={categories} onSaved={refresh} />
      </Card>

      <div className="filters" role="group" aria-label="Period">
        {PERIODS.map((p) => (
          <button
            key={p.key}
            type="button"
            className={`chip ${periodKey === p.key ? 'chip--on' : ''}`}
            aria-pressed={periodKey === p.key}
            onClick={() => setPeriodKey(p.key)}
          >
            {p.label}
          </button>
        ))}
      </div>

      {stats.state !== 'ready' || !s ? (
        <LoadState state={stats.state} />
      ) : (
        <>
          <Card title="In and out" aside={`${w.days} days`}>
            <div className="money-figures">
              <Figure label="Income" value={formatDh(w.income)} tone="in" />
              <Figure label="Spent" value={formatDh(w.expense)} tone="out" />
              <Figure label="Net" value={formatDh(w.net, { sign: true })} tone={w.net >= 0 ? 'in' : 'out'} />
              <Figure label="Kept" value={w.savings_rate === null ? '—' : percent(w.savings_rate)} />
            </div>
            <p className="status money-note">
              {w.expense_change === null
                ? `Nothing spent in the ${w.days} days before.`
                : `${w.expense_change <= 0 ? 'Down' : 'Up'} ${percent(Math.abs(w.expense_change))} on the ${w.days} days before (${formatDh(w.previous_expense)}).`}
            </p>
          </Card>

          <Card title="Average spending">
            <div className="money-figures">
              <Figure label="A day" value={formatDh(w.per_day)} />
              <Figure label="A week" value={formatDh(w.per_week)} />
              <Figure label="A month" value={formatDh(w.per_month)} />
              <Figure label="A year" value={formatDh(w.per_year)} />
            </div>
            <p className="status money-note">
              {w.no_spend_days} of {w.days} days without spending.
            </p>
          </Card>

          <Card title={s.series.unit === 'day' ? 'Spent per day' : 'Spent per month'}>
            <SeriesChart series={s.series} />
          </Card>

          <Card title="By category">
            {s.categories.length === 0 ? (
              <p className="status">No spending in this period.</p>
            ) : (
              <Meters
                rows={s.categories.map((c) => ({
                  label: c.category,
                  value: c.amount,
                  text: `${formatDh(c.amount)} · ${percent(c.share)}`,
                }))}
              />
            )}
          </Card>

          <Card title="By weekday" aside="average spent">
            <Meters rows={s.weekdays.map((d) => ({ label: WEEKDAYS[d.weekday - 1], value: d.average, text: formatDh(d.average) }))} />
          </Card>

          {s.top_expenses.length > 0 && (
            <Card title="Biggest expenses">
              <ul className="money-top-list">
                {s.top_expenses.map((e) => (
                  <li key={e.id}>
                    <span>
                      {e.title}
                      <span className="money-row__category">
                        {dayLabel(e.occurred_on, { day: 'numeric', month: 'short' })}
                        {e.category && ` · ${e.category}`}
                      </span>
                    </span>
                    <span className="money-row__amount">{formatDh(e.amount)}</span>
                  </li>
                ))}
              </ul>
            </Card>
          )}

          <Card title="Last 12 months">
            <MonthsChart months={s.months} />
          </Card>
        </>
      )}

      <Card title="Entries" aside="double-click or hold to edit">
        {list.state !== 'ready' ? (
          <LoadState state={list.state} />
        ) : (
          <EntryList entries={list.data} categories={categories} onChanged={refresh} />
        )}
      </Card>
    </div>
  )
}
