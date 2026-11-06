import { useCallback, useEffect, useState } from 'react'
import { apiDelete, apiGet, apiPatch, apiPost } from './api.js'

/**
 * The task list and its mutations. After every write the list is re-read
 * from the server, so ordering and filtering stay the server's job.
 */
export function useTasks() {
  const [tasks, setTasks] = useState([])
  const [state, setState] = useState('loading') // loading | ready | error

  const fetchTasks = useCallback(
    (signal) =>
      apiGet('/tasks', { signal }).then(
        ({ data }) => {
          setTasks(data)
          setState('ready')
        },
        (err) => {
          if (err.name !== 'AbortError') setState('error')
        },
      ),
    [],
  )

  useEffect(() => {
    const controller = new AbortController()
    fetchTasks(controller.signal)
    return () => controller.abort()
  }, [fetchTasks])

  const reload = useCallback(() => fetchTasks(), [fetchTasks])

  const createTask = useCallback(
    async (attributes) => {
      const { data } = await apiPost('/tasks', attributes)
      await reload()
      return data
    },
    [reload],
  )

  const updateTask = useCallback(
    async (id, attributes) => {
      const { data } = await apiPatch(`/tasks/${id}`, attributes)
      await reload()
      return data
    },
    [reload],
  )

  const deleteTask = useCallback(
    async (id) => {
      await apiDelete(`/tasks/${id}`)
      await reload()
    },
    [reload],
  )

  return { tasks, state, createTask, updateTask, deleteTask }
}
