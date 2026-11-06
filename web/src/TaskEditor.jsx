import { useState } from 'react'
import { useOnline } from './connectivity.js'
import Icon from './Icon.jsx'
import { useFetch } from './useFetch.js'

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

/** ISO datetime → "YYYY-MM-DD" in local time, for <input type="date">. */
function toDateInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function formFromTask(task) {
  return {
    title: task.title,
    sub_category_id: task.sub_category_id,
    due_at: toDateInput(task.due_at),
    type: task.type,
    planned_minutes: task.planned_minutes ?? '',
    importance: task.importance,
    repeat: task.repeat ?? '',
    repeat_days: task.repeat_days ?? [],
    link: task.link ?? '',
    notes: task.notes ?? '',
  }
}

function formToPayload(form) {
  return {
    title: form.title,
    sub_category_id: Number(form.sub_category_id),
    due_at: form.due_at || null,
    type: form.type,
    planned_minutes:
      form.type === 'timed' && form.planned_minutes !== ''
        ? Number(form.planned_minutes)
        : null,
    importance: form.importance,
    repeat: form.repeat || null,
    repeat_days: form.repeat === 'weekly' ? form.repeat_days : null,
    link: form.link || null,
    notes: form.notes || null,
  }
}

/** Simple full edit form (SPEC §7, step 3). A panel/modal can replace it later. */
export default function TaskEditor({ task, onSave, onDelete, onClose }) {
  const [form, setForm] = useState(() => formFromTask(task))
  const { data: categories } = useFetch('/categories')
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const online = useOnline()

  const set = (field) => (event) =>
    setForm((f) => ({ ...f, [field]: event.target.value }))

  // Switching to weekly pre-selects today's weekday: one less click.
  function setRepeat(event) {
    const repeat = event.target.value
    setForm((f) => ({
      ...f,
      repeat,
      repeat_days:
        repeat === 'weekly' && f.repeat_days.length === 0
          ? [((new Date().getDay() + 6) % 7) + 1]
          : f.repeat_days,
    }))
  }

  function toggleDay(day) {
    setForm((f) => ({
      ...f,
      repeat_days: f.repeat_days.includes(day)
        ? f.repeat_days.filter((d) => d !== day)
        : [...f.repeat_days, day].sort(),
    }))
  }

  async function run(action) {
    setSaving(true)
    setErrors({})
    try {
      await action()
      onClose()
    } catch (err) {
      setErrors(err.errors ?? { title: [err.message] })
      setSaving(false)
    }
  }

  function submit(event) {
    event.preventDefault()
    run(() => onSave(task.id, formToPayload(form)))
  }

  function remove() {
    if (window.confirm(`Delete "${task.title}"?`)) run(() => onDelete(task.id))
  }

  const error = (field) =>
    errors[field] && <span className="field__error">{errors[field][0]}</span>

  return (
    <form
      className="editor"
      onSubmit={submit}
      onKeyDown={(e) => e.key === 'Escape' && onClose()}
    >
      {/* Readable offline, not editable (SPEC addendum §13). */}
      <fieldset className="offline-lock" disabled={!online}>
      <label className="field field--wide">
        <span className="field__label">Title</span>
        <input value={form.title} onChange={set('title')} autoFocus required />
        {error('title')}
      </label>

      <label className="field">
        <span className="field__label">Sub-category</span>
        <select value={form.sub_category_id} onChange={set('sub_category_id')}>
          {categories ? (
            categories.map((category) => (
              <optgroup key={category.id} label={category.title}>
                {category.sub_categories.map((sub) => (
                  <option key={sub.id} value={sub.id}>
                    {sub.title}
                  </option>
                ))}
              </optgroup>
            ))
          ) : (
            <option value={task.sub_category_id}>{task.sub_category.title}</option>
          )}
        </select>
        {error('sub_category_id')}
      </label>

      <label className="field">
        <span className="field__label">Due</span>
        <input type="date" value={form.due_at} onChange={set('due_at')} />
        {error('due_at')}
      </label>

      <label className="field">
        <span className="field__label">Importance</span>
        <select value={form.importance} onChange={set('importance')}>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
      </label>

      <label className="field">
        <span className="field__label">Type</span>
        <select value={form.type} onChange={set('type')}>
          <option value="open">Open-ended</option>
          <option value="timed">Timed</option>
        </select>
      </label>

      {form.type === 'timed' && (
        <label className="field">
          <span className="field__label">Planned minutes</span>
          <input
            type="number"
            min="1"
            max="1440"
            value={form.planned_minutes}
            onChange={set('planned_minutes')}
          />
          {error('planned_minutes')}
        </label>
      )}

      <label className="field">
        <span className="field__label">Repeat</span>
        <select value={form.repeat} onChange={setRepeat}>
          <option value="">Never</option>
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
        </select>
      </label>

      {form.repeat === 'weekly' && (
        <fieldset className="field field--wide days">
          <legend className="field__label">On</legend>
          {WEEKDAYS.map((label, i) => (
            <button
              key={label}
              type="button"
              className={`chip ${form.repeat_days.includes(i + 1) ? 'chip--on' : ''}`}
              aria-pressed={form.repeat_days.includes(i + 1)}
              onClick={() => toggleDay(i + 1)}
            >
              {label}
            </button>
          ))}
          {error('repeat_days')}
        </fieldset>
      )}

      <label className="field field--wide">
        <span className="field__label">Link</span>
        <input
          type="url"
          value={form.link}
          onChange={set('link')}
          placeholder="https://"
        />
        {error('link')}
      </label>

      <label className="field field--wide">
        <span className="field__label">Notes</span>
        <textarea rows="3" value={form.notes} onChange={set('notes')} />
        {error('notes')}
      </label>

      </fieldset>

      <div className="editor__actions">
        <button
          type="button"
          className="icon-button icon-button--danger"
          onClick={remove}
          disabled={saving || !online}
          aria-label="Delete task"
          title="Delete task"
        >
          <Icon name="delete" />
        </button>
        <span className="editor__spacer" />
        <button type="button" className="button" onClick={onClose}>
          Cancel
        </button>
        <button type="submit" className="button button--primary" disabled={saving || !online}>
          Save
        </button>
      </div>
    </form>
  )
}
