import { useState } from 'react'
import AddView from './AddView.jsx'
import BrowseView from './BrowseView.jsx'
import FocusMode from './FocusMode.jsx'
import ManageView from './ManageView.jsx'
import MiniTimer from './MiniTimer.jsx'
import StatsView from './StatsView.jsx'
import TabBar from './TabBar.jsx'
import { useTimer } from './timerContext.js'
import TodayView from './TodayView.jsx'
import { useIsMobile } from './useIsMobile.js'
import './App.css'

const VIEWS = {
  today: { label: 'Today', Component: TodayView },
  browse: { label: 'Browse', Component: BrowseView },
  stats: { label: 'Stats', Component: StatsView },
  // Admin screen: reached from Browse, no tab of its own.
  manage: { label: 'Manage categories', Component: ManageView, hidden: true },
  // Phone only: the "Add" tab of the bottom bar.
  add: { label: 'Add a task', Component: AddView, hidden: true },
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
  const isMobile = useIsMobile()
  // "Add" exists only on the phone: back on a wide screen, it is Today's capture.
  const current = view === 'add' && !isMobile ? 'today' : view
  const { Component, label } = VIEWS[current]
  const { timer, mode } = useTimer()
  const focusOpen = timer !== null && mode === 'focus'

  return (
    <>
    {/* inert while focus mode covers the screen: no tabbing or N-capture behind it. */}
    <main
      className={`app ${current === 'today' ? '' : 'app--wide'} ${timer && mode === 'mini' ? 'app--with-mini' : ''}`}
      inert={focusOpen}
    >
      <header className="app__header">
        {/* On the phone the bottom tab bar navigates; the header names the screen. */}
        <h1 className="app__screen-title">{label}</h1>
        <nav className="tabs" aria-label="Views">
          {Object.entries(VIEWS)
            .filter(([, { hidden }]) => !hidden)
            .map(([key, { label }]) => (
            <button
              key={key}
              type="button"
              className={`tabs__tab ${current === key ? 'tabs__tab--on' : ''}`}
              aria-current={current === key ? 'page' : undefined}
              onClick={() => setView(key)}
            >
              {label}
            </button>
          ))}
        </nav>
        {current === 'today' && <p className="app__date">{todayLabel()}</p>}
      </header>
      <Component key={current} navigate={setView} />
    </main>
    {isMobile && !focusOpen && <TabBar view={current} onSelect={setView} />}
    {focusOpen && <FocusMode />}
    {timer && mode === 'mini' && <MiniTimer />}
    </>
  )
}

export default App
