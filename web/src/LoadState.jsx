/** Shared loading / error messages for a screen backed by useFetch. */
export default function LoadState({ state }) {
  if (state === 'loading') return <p className="status">Loading…</p>
  return (
    <p className="status status--error">
      Could not reach the API. Is <code>php artisan serve</code> running?
    </p>
  )
}
