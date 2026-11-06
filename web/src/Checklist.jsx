import { useState } from 'react'
import { useOnline } from './connectivity.js'
import Icon from './Icon.jsx'
import { createItem, deleteItem, updateItem } from './itemsApi.js'
import { useFetch } from './useFetch.js'
import { useTimer } from './timerContext.js'

function doneLabel(iso) {
  return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

/** The title, as a link to the resource when it has one (new tab, the timer keeps running). */
function ItemTitle({ item }) {
  if (!item.link) return <span className="checklist__title">{item.title}</span>
  return (
    <a className="checklist__title checklist__title--link" href={item.link} target="_blank" rel="noopener noreferrer">
      {item.title}
      <span aria-hidden="true"> ↗</span>
    </a>
  )
}

function Item({ item, onToggle, onDelete, editable, busy }) {
  return (
    <li className={`checklist__item ${item.is_done ? 'checklist__item--done' : ''}`}>
      <button
        type="button"
        role="checkbox"
        aria-checked={item.is_done}
        aria-label={`${item.title}: ${item.is_done ? 'done' : 'to do'}`}
        className={`task__check ${item.is_done ? 'task__check--on' : ''}`}
        onClick={() => onToggle(item)}
        disabled={busy}
      >
        {item.is_done && <Icon name="check" size={24} />}
      </button>
      <ItemTitle item={item} />
      {item.is_done && <span className="checklist__date">{doneLabel(item.done_at)}</span>}
      {editable && (
        <button
          type="button"
          className="icon-button icon-button--danger"
          onClick={() => onDelete(item)}
          disabled={busy}
          aria-label={`Delete ${item.title}`}
          title="Delete"
        >
          <Icon name="delete" />
        </button>
      )}
    </li>
  )
}

/**
 * A task's checklist: films to watch, articles to read, subjects to write on,
 * each with an optional link. In focus mode it is the "what now?" list;
 * in the editor (`editable`) items can also be deleted.
 * Ticked items fold into "Done", with their date, and can be unticked.
 */
export default function Checklist({ taskId, editable = false, title = 'List' }) {
  const { data, state, reload } = useFetch(`/tasks/${taskId}/items`)
  const { refreshLists } = useTimer()
  const online = useOnline()
  const [draft, setDraft] = useState({ title: '', link: '' })
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  async function write(action) {
    setBusy(true)
    setError(null)
    try {
      await action()
      await reload()
      refreshLists() // the row's "N left" count
      return true
    } catch (err) {
      setError(Object.values(err.errors ?? {})[0]?.[0] ?? 'Could not save. Try again.')
      return false
    } finally {
      setBusy(false)
    }
  }

  async function add(event) {
    event.preventDefault()
    if (!draft.title.trim()) return
    const saved = await write(() => createItem(taskId, { title: draft.title.trim(), link: draft.link }))
    if (saved) setDraft({ title: '', link: '' })
  }

  const toggle = (item) => write(() => updateItem(item.id, { done: !item.is_done }))
  const remove = (item) => {
    if (window.confirm(`Delete "${item.title}" from the list?`)) write(() => deleteItem(item.id))
  }

  const items = data ?? []
  const todo = items.filter((i) => !i.is_done)
  const done = items.filter((i) => i.is_done)
  const disabled = busy || !online
  const row = (item) => (
    <Item key={item.id} item={item} onToggle={toggle} onDelete={remove} editable={editable} busy={disabled} />
  )

  return (
    <section className="checklist" aria-label={title}>
      <h3 className="checklist__heading">
        {title}
        {todo.length > 0 && <span className="checklist__count">{todo.length} left</span>}
      </h3>

      {state === 'error' && items.length === 0 ? (
        <p className="status">{online ? 'Could not load the list.' : 'Not saved for offline reading yet.'}</p>
      ) : (
        <>
          {todo.length > 0 && <ul className="checklist__list">{todo.map(row)}</ul>}
          {/* In focus mode an empty list stays quiet: just the field to start one. */}
          {editable && state === 'ready' && items.length === 0 && (
            <p className="status">Nothing yet. Add what to watch, read or write on, with its link.</p>
          )}
          {done.length > 0 && (
            <details className="checklist__done">
              <summary>Done ({done.length})</summary>
              <ul className="checklist__list">{done.map(row)}</ul>
            </details>
          )}
        </>
      )}

      <form className="checklist__add" onSubmit={add}>
        <input
          value={draft.title}
          onChange={(e) => setDraft((d) => ({ ...d, title: e.target.value }))}
          placeholder={online ? 'Add an item…' : 'Offline — adding is disabled'}
          aria-label="New item"
          maxLength={255}
          disabled={!online}
          enterKeyHint="done"
        />
        <input
          value={draft.link}
          onChange={(e) => setDraft((d) => ({ ...d, link: e.target.value }))}
          placeholder="Link (optional)"
          aria-label="Link of the new item"
          inputMode="url"
          autoCapitalize="off"
          disabled={!online}
          enterKeyHint="done"
        />
        <button type="submit" className="button" disabled={disabled || !draft.title.trim()}>
          Add
        </button>
      </form>
      {error && <p className="status status--error">{error}</p>}
    </section>
  )
}
