import { useEffect } from 'react'
import { useConnectivity } from './connectivity.js'

function describeTime(timestamp) {
  const date = new Date(timestamp)
  const time = date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
  if (date.toDateString() === new Date().toDateString()) return time
  return `${date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}, ${time}`
}

/**
 * SPEC addendum §13 — says plainly that the app is read-only while offline,
 * and how old the data on screen is. Gone by itself once back online.
 */
export default function OfflineBanner() {
  const { online, dataFrom } = useConnectivity()

  // Layout makes room for the banner (see --offline-banner in App.css).
  useEffect(() => {
    document.documentElement.classList.toggle('is-offline', !online)
  }, [online])

  if (online) return null

  return (
    <div className="offline" role="status">
      Offline — showing data from {dataFrom ? describeTime(dataFrom) : 'your last visit'}. Changes are
      disabled.
    </div>
  )
}
