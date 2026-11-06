import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.jsx'
import PasswordGate from './PasswordGate.jsx'
import TimerProvider from './TimerProvider.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <PasswordGate>
      <TimerProvider>
        <App />
      </TimerProvider>
    </PasswordGate>
  </StrictMode>,
)
