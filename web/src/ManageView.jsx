import { useEffect, useRef, useState } from 'react'
import Icon from './Icon.jsx'
import LoadState from './LoadState.jsx'
import {
  createCategory,
  createSubCategory,
  deleteCategory,
  deleteSubCategory,
  updateCategory,
  updateSubCategory,
} from './categoriesApi.js'
import { useTimer } from './timerContext.js'
import { useFetch } from './useFetch.js'

function plural(n, word) {
  return `${n} ${word}${n === 1 ? '' : 's'}`
}

function errorText(err) {
  return Object.values(err.errors ?? {})[0]?.[0] ?? err.message
}

/** A title edited in place: saved on Enter or when leaving the field, Esc reverts. */
function InlineTitle({ value, onSave, label, className = '' }) {
  // Callers pass key={value}, so a new server value resets the draft.
  const [draft, setDraft] = useState(value)

  function commit() {
    const trimmed = draft.trim()
    if (!trimmed) setDraft(value)
    else if (trimmed !== value) onSave(trimmed)
  }

  return (
    <input
      className={`inline-title ${className}`}
      value={draft}
      aria-label={label}
      maxLength={60}
      onChange={(e) => setDraft(e.target.value)}
      onBlur={commit}
      onKeyDown={(e) => {
        if (e.key === 'Enter') e.currentTarget.blur()
        if (e.key === 'Escape') {
          setDraft(value)
          e.currentTarget.blur()
        }
      }}
    />
  )
}

/** Native colour picker; saves once the colour stops changing. */
function ColorPicker({ value, onSave, label }) {
  // Callers pass key={value}, so a new server value resets the draft.
  const [draft, setDraft] = useState(value)
  const timer = useRef(null)
  useEffect(() => () => clearTimeout(timer.current), [])

  function change(event) {
    const color = event.target.value
    setDraft(color)
    clearTimeout(timer.current)
    timer.current = setTimeout(() => color !== value && onSave(color), 400)
  }

  return (
    <label className="swatch" style={{ '--sub-color': draft }} title="Change colour">
      <input type="color" value={draft} onChange={change} aria-label={label} />
    </label>
  )
}

/** One-line "add" field: type, press Enter. */
function AddField({ placeholder, onAdd, label }) {
  const [title, setTitle] = useState('')

  async function submit(event) {
    event.preventDefault()
    const trimmed = title.trim()
    if (!trimmed) return
    await onAdd(trimmed)
    setTitle('')
  }

  return (
    <form className="add-field" onSubmit={submit}>
      <Icon name="add" className="add-field__icon" />
      <input
        value={title}
        onChange={(e) => setTitle(e.target.value)}
        placeholder={placeholder}
        aria-label={label}
        maxLength={60}
      />
    </form>
  )
}

function DeleteButton({ label, onClick }) {
  return (
    <button
      type="button"
      className="icon-button icon-button--danger"
      onClick={onClick}
      aria-label={label}
      title={label}
    >
      <Icon name="delete" />
    </button>
  )
}

function countLabel(item) {
  if (!item.tasks_count) return 'No tasks'
  return item.open_tasks_count === item.tasks_count
    ? plural(item.tasks_count, 'task')
    : `${plural(item.tasks_count, 'task')} · ${item.open_tasks_count} open`
}

/**
 * SPEC §5.6 — plain admin screen for categories and sub-categories.
 * Deleting something that still holds tasks asks first, naming how many go.
 */
export default function ManageView({ navigate }) {
  const { data: categories, state, reload } = useFetch('/categories')
  const { sync } = useTimer()
  const [error, setError] = useState(null)

  if (state !== 'ready') return <LoadState state={state} />

  async function write(action) {
    setError(null)
    try {
      await action()
    } catch (err) {
      setError(errorText(err))
    } finally {
      await reload()
    }
  }

  /**
   * Empty: delete at once. Holding tasks: confirm with the counts, then force.
   * A 409 (counts changed since the page loaded) asks with the server's numbers.
   */
  async function remove(kind, item, request) {
    const describe = (counts) =>
      `Delete ${kind} "${item.title}" and its ${plural(counts.tasks_count, 'task')}` +
      (counts.open_tasks_count ? ` (${counts.open_tasks_count} still open)` : '') +
      '? This cannot be undone.'

    await write(async () => {
      if (item.tasks_count > 0) {
        if (!window.confirm(describe(item))) return
        await request({ force: true })
      } else {
        try {
          await request({ force: false })
        } catch (err) {
          if (err.status !== 409) throw err
          if (!window.confirm(describe(err.body))) return
          await request({ force: true })
        }
      }
      await sync() // a running timer may have gone with the tasks
    })
  }

  return (
    <div className="manage">
      <div className="manage__header">
        <h2 className="browse__title">Manage categories</h2>
        <button type="button" className="button" onClick={() => navigate('browse')}>
          Done
        </button>
      </div>

      {error && <p className="status status--error">{error}</p>}

      <AddField
        label="New category"
        placeholder="New category…"
        onAdd={(title) => write(() => createCategory(title))}
      />

      {categories.map((category) => (
        <section key={category.id} className="manage__card">
          <div className="manage__row manage__row--category">
            <InlineTitle
              key={category.title}
              value={category.title}
              label={`Category name: ${category.title}`}
              className="inline-title--category"
              onSave={(title) => write(() => updateCategory(category.id, { title }))}
            />
            <span className="manage__count">{countLabel(category)}</span>
            <DeleteButton
              label={`Delete category ${category.title}`}
              onClick={() =>
                remove('category', category, (options) => deleteCategory(category.id, options))
              }
            />
          </div>

          <ul className="manage__subs">
            {category.sub_categories.map((sub) => (
              <li key={sub.id} className="manage__row">
                <ColorPicker
                  key={sub.color}
                  value={sub.color}
                  label={`Colour of ${sub.title}`}
                  onSave={(color) => write(() => updateSubCategory(sub.id, { color }))}
                />
                <InlineTitle
                  key={sub.title}
                  value={sub.title}
                  label={`Sub-category name: ${sub.title}`}
                  onSave={(title) => write(() => updateSubCategory(sub.id, { title }))}
                />
                <span className="manage__count">{countLabel(sub)}</span>
                <DeleteButton
                  label={`Delete sub-category ${sub.title}`}
                  onClick={() =>
                    remove('sub-category', sub, (options) => deleteSubCategory(sub.id, options))
                  }
                />
              </li>
            ))}
          </ul>

          <AddField
            label={`New sub-category in ${category.title}`}
            placeholder="Add a sub-category…"
            onAdd={(title) =>
              write(() => createSubCategory({ category_id: category.id, title }))
            }
          />
        </section>
      ))}
    </div>
  )
}
