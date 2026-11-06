import { useCallback, useEffect, useState } from 'react'
import { apiGet } from './api.js'

/**
 * GET `path` on mount and expose `reload()` for after a write.
 * Screens re-read from the server so ordering and grouping stay the API's job.
 */
export function useFetch(path) {
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
    [path],
  )

  useEffect(() => {
    const controller = new AbortController()
    fetchData(controller.signal)
    return () => controller.abort()
  }, [fetchData])

  const reload = useCallback(() => fetchData(), [fetchData])

  return { data, state, reload }
}
