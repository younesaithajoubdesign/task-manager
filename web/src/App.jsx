import { useState } from 'react'
import BrowseView from './BrowseView.jsx'
import FocusMode from './FocusMode.jsx'
import ManageView from './ManageView.jsx'
import MiniTimer from './MiniTimer.jsx'
import { useTimer } from './timerContext.js'
import TodayView from './TodayView.jsx'
import './App.css'

const VIEWS = {
  today: { label: 'Today', Component: TodayView },
  browse: { label: 'Browse', Component: BrowseView },
  // Admin screen: reached from Browse, no tab of its own.
  manage: { label: 'Manage categories', Component: ManageView, hidden: true },
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
  const { timer, mode } = useTimer()
  const focusOpen = timer !== null && mode === 'focus'

  return (
    <>
    {/* inert while focus mode covers the screen: no tabbing or N-capture behind it. */}
    <main
      className={`app ${view === 'today' ? '' : 'app--wide'} ${timer && mode === 'mini' ? 'app--with-mini' : ''}`}
      inert={focusOpen}
    >
      <header className="app__header">
        <nav className="tabs" aria-label="Views">
          {Object.entries(VIEWS)
            .filter(([, { hidden }]) => !hidden)
            .map(([key, { label }]) => (
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
      <Component key={view} navigate={setView} />
    </main>
    {focusOpen && <FocusMode />}
    {timer && mode === 'mini' && <MiniTimer />}
    </>
  )
}

export default App
