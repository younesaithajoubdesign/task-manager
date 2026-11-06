import { useState } from 'react'
import BrowseView from './BrowseView.jsx'
import TodayView from './TodayView.jsx'
import './App.css'

const VIEWS = {
  today: { label: 'Today', Component: TodayView },
  browse: { label: 'Browse', Component: BrowseView },
}

function todayLabel() {
  return new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
}

function App() {
  // The app always opens on Today (SPEC §5.2).
  const [view, setView] = useState('today')
  const { Component } = VIEWS[view]

  return (
    <main className={`app ${view === 'browse' ? 'app--wide' : ''}`}>
      <header className="app__header">
        <nav className="tabs" aria-label="Views">
          {Object.entries(VIEWS).map(([key, { label }]) => (
            <button
              key={key}
              type="button"
              className={`tabs__tab ${view === key ? 'tabs__tab--on' : ''}`}
              aria-current={view === key ? 'page' : undefined}
              onClick={() => setView(key)}
            >
              {label}
            </button>
          ))}
        </nav>
        {view === 'today' && <p className="app__date">{todayLabel()}</p>}
      </header>
      <Component key={view} />
    </main>
  )
}

export default App
