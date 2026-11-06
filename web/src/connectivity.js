import { useSyncExternalStore } from 'react'

/**
 * Online or not (SPEC addendum §13). navigator.onLine alone lies on captive
 * portals, so API results count too: a network error, or an answer the
 * service worker served from its cache (flagged X-From-Cache), means offline;
 * a fresh answer means online again.
 *
 * `dataFrom` is when the oldest data on screen was fetched (the cached
 * response's Date header). `reconnects` goes up each time the connection
 * comes back, so screens can re-read the API.
 */
let state = { online: navigator.onLine, dataFrom: null, reconnects: 0 }
const listeners = new Set()
const PROBE_MS = 15000
let probeTimer = null

function update(next) {
  state = { ...state, ...next }
  listeners.forEach((listener) => listener())
  if (state.online) stopProbing()
  else startProbing()
}

function markOffline(fetchedAt = null) {
  const known = [state.online ? null : state.dataFrom, fetchedAt].filter(Number.isFinite)
  const dataFrom = known.length > 0 ? Math.min(...known) : null
  if (!state.online && dataFrom === state.dataFrom) return
  update({ online: false, dataFrom })
}

function markOnline() {
  if (state.online) return
  update({ online: true, dataFrom: null, reconnects: state.reconnects + 1 })
}

/** Called by api.js for every answer from /api. */
export function reportResponse(response) {
  if (response.headers.get('X-From-Cache')) {
    markOffline(Date.parse(response.headers.get('Date') ?? ''))
  } else {
    markOnline()
  }
}

/** Called by api.js when fetch itself fails (no network, server down). */
export function reportNetworkError() {
  markOffline()
}

/** One light request: is the server reachable right now? */
async function probe() {
  try {
    reportResponse(await fetch('/api/gate', { headers: { Accept: 'application/json' } }))
  } catch {
    reportNetworkError()
  }
}

function startProbing() {
  if (probeTimer === null) probeTimer = setInterval(probe, PROBE_MS)
}

function stopProbing() {
  clearInterval(probeTimer)
  probeTimer = null
}

window.addEventListener('offline', () => markOffline())
// "online" is only a hint: confirm with a real request before re-enabling anything.
window.addEventListener('online', probe)
if (!state.online) startProbing()

function subscribe(listener) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

export function useConnectivity() {
  return useSyncExternalStore(subscribe, () => state)
}

/** Shorthand for controls that need the server: disabled while offline. */
export function useOnline() {
  return useConnectivity().online
}
