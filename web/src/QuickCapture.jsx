import { useEffect, useRef, useState } from 'react'
import Icon from './Icon.jsx'

function isTypingIn(element) {
  return (
    element instanceof HTMLElement &&
    (element.isContentEditable ||
      ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName))
  )
}

/**
 * SPEC §5.5 — one text field, reachable from anywhere with N or Ctrl/Cmd+K.
 * Enter creates the task and keeps focus, so several can be typed in a row.
 * `defaults` are sent with every title (e.g. Today sends today's due date).
 */
export default function QuickCapture({
  onCreate,
  defaults = {},
  placeholder = 'Add a task…',
  autoFocus = false,
}) {
  const inputRef = useRef(null)
  const [title, setTitle] = useState('')
  const [error, setError] = useState(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    function onKeyDown(event) {
      const commandK =
        (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k'
      const plainN =
        event.key.toLowerCase() === 'n' &&
        !event.metaKey &&
        !event.ctrlKey &&
        !event.altKey &&
        !isTypingIn(event.target)

      if (commandK || plainN) {
        event.preventDefault()
        inputRef.current?.focus()
      }
    }

    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [])

  async function submit(event) {
    event.preventDefault()
    const trimmed = title.trim()
    if (!trimmed || saving) return

    setSaving(true)
    setError(null)
    try {
      await onCreate({ ...defaults, title: trimmed })
      setTitle('')
    } catch (err) {
      setError(Object.values(err.errors ?? {})[0]?.[0] ?? err.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <form className="capture" onSubmit={submit}>
      <label className="capture__field">
        <Icon name="add" className="capture__icon" />
        <input
          ref={inputRef}
          className="capture__input"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          onKeyDown={(e) => e.key === 'Escape' && e.currentTarget.blur()}
          placeholder={placeholder}
          autoFocus={autoFocus}
          aria-label="New task title"
          maxLength={255}
          autoComplete="off"
        />
        <kbd className="capture__hint">N</kbd>
      </label>
      {error && <p className="capture__error">{error}</p>}
    </form>
  )
}
