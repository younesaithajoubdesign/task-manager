/**
 * The signal when a timed task reaches its planned duration: a chime, and a
 * system notification so it is seen even when the tab is in the background.
 * Notifications need a secure page (localhost, HTTPS): on http://<mac-ip>
 * from the phone only the chime plays.
 */

const canNotify = () => typeof window !== 'undefined' && 'Notification' in window && window.isSecureContext

/** Ask once, from a click (browsers ignore the request otherwise). */
export function askNotificationPermission() {
  if (canNotify() && Notification.permission === 'default') {
    Notification.requestPermission().catch(() => {})
  }
}

/** Three soft rising notes, about a second and a half. */
export function playChime() {
  try {
    const audio = new AudioContext()
    const notes = [660, 880, 1100]
    notes.forEach((frequency, i) => {
      const at = audio.currentTime + i * 0.35
      const osc = audio.createOscillator()
      const gain = audio.createGain()
      osc.type = 'sine'
      osc.frequency.value = frequency
      gain.gain.setValueAtTime(0.0001, at)
      gain.gain.exponentialRampToValueAtTime(0.25, at + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.6)
      osc.connect(gain).connect(audio.destination)
      osc.start(at)
      osc.stop(at + 0.6)
    })
    setTimeout(() => audio.close(), 2000)
  } catch {
    // No audio: the notification and the paused screen still say it.
  }
}

export function notifyTargetReached(task) {
  if (!canNotify() || Notification.permission !== 'granted') return
  try {
    const notification = new Notification(`Time's up: ${task.title}`, {
      body: `${task.planned_minutes} min reached. The timer is paused.`,
      icon: '/icons/icon-192.png',
      tag: `timer-${task.id}`,
      requireInteraction: true,
    })
    notification.onclick = () => {
      window.focus()
      notification.close()
    }
  } catch {
    // Some browsers only allow notifications from a service worker: the chime played.
  }
}
