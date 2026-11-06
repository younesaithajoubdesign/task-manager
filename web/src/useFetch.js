import { useCallback, useEffect, useState } from 'react'
import { apiGet } from './api.js'

/**
 * GET `path` on mount and expose `reload()` for after a write.
 * A change of `refreshKey` (e.g. the timer's version) also re-reads.
 * Screens re-read from the server so ordering and grouping stay the API's job.
 */
export function useFetch(path, refreshKey = 0) {
  const [data, setData] = useState(null)
  const [state, setState] = useState('loading') // loading | ready | error

  const fetchData = useCallback(
    (signal) =>
      apiGet(path, { signal }).then(
        (body) => {
          setData(body.data)
          setState('ready')
        },
        (err) => {
          if (err.name !== 'AbortError') setState('error')
        },
      ),
    // refreshKey is a deliberate dependency: changing it re-runs the fetch.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [path, refreshKey],
  )

  useEffect(() => {
    const controller = new AbortController()
    fetchData(controller.signal)
    return () => controller.abort()
  }, [fetchData])

  const reload = useCallback(() => fetchData(), [fetchData])

  return { data, state, reload }
}
