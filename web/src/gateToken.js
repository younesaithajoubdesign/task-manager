// The password-gate token (SPEC §5.1), kept in localStorage. Storage may be
// blocked (private mode): then the password is simply asked again next time.
const KEY = 'gate.token'

export function getToken() {
  try {
    return localStorage.getItem(KEY)
  } catch {
    return null
  }
}

export function setToken(token) {
  try {
    localStorage.setItem(KEY, token)
  } catch {
    // Not remembered; this tab still works until it is closed.
  }
}

export function clearToken() {
  try {
    localStorage.removeItem(KEY)
  } catch {
    // Nothing to clear.
  }
}

/** Fired when the API answers 401, so the app swaps to the password screen. */
export const LOCKED_EVENT = 'gate:locked'

/** Ask the server to revoke this browser's token, then forget it here. */
export async function logOut(revoke) {
  try {
    await revoke()
  } catch {
    // Offline or already invalid: forgetting the token locally still locks this browser.
  }
  clearToken()
  window.dispatchEvent(new Event(LOCKED_EVENT))
}
