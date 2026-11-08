import { flushSync } from 'react-dom'
import Icon from './Icon.jsx'

const TABS = [
  { key: 'today', label: 'Today' },
  { key: 'browse', label: 'Browse', also: ['manage'] },
  { key: 'add', label: 'Add', icon: 'add' },
  { key: 'money', label: 'Money' },
  { key: 'stats', label: 'Stats' },
]

/** Phone navigation (SPEC addendum §12): five tabs at the bottom, above the home indicator. */
export default function TabBar({ view, onSelect }) {
  return (
    <nav className="tabbar" aria-label="Views">
      {TABS.map(({ key, label, icon, also = [] }) => {
        const on = view === key || also.includes(view)
        return (
          <button
            key={key}
            type="button"
            className={`tabbar__tab ${on ? 'tabbar__tab--on' : ''} ${icon ? 'tabbar__tab--add' : ''}`}
            aria-current={on ? 'page' : undefined}
            // flushSync mounts the new screen inside the tap itself: iOS only
            // opens the keyboard for a focus() made during a user gesture,
            // which is what lets "Add" show the keyboard straight away.
            onClick={() => flushSync(() => onSelect(key))}
          >
            {icon && <Icon name={icon} />}
            <span>{label}</span>
          </button>
        )
      })}
    </nav>
  )
}
