import { useOnline } from './connectivity.js'

/** Shared loading / error messages for a screen backed by useFetch. */
export default function LoadState({ state }) {
  const online = useOnline()

  if (state === 'loading') return <p className="status">Loading…</p>
  if (!online) {
    return (
      <p className="status">
        Nothing saved for this screen yet: it is kept for offline reading once opened online.
      </p>
    )
  }
  return (
    <p className="status status--error">
      Could not reach the API. Is <code>php artisan serve</code> running?
    </p>
  )
}
