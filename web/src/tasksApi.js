import { apiDelete, apiGet, apiPatch, apiPost } from './api.js'

export const createTask = (attributes) =>
  apiPost('/tasks', attributes).then((body) => body.data)

export const updateTask = (id, attributes) =>
  apiPatch(`/tasks/${id}`, attributes).then((body) => body.data)

export const deleteTask = (id) => apiDelete(`/tasks/${id}`)

export const completeTask = (id) =>
  apiPost(`/tasks/${id}/complete`).then((body) => body.data)

export const uncompleteTask = (id) =>
  apiPost(`/tasks/${id}/uncomplete`).then((body) => body.data)

/** Timer actions: start | pause | resume | stop | reset. Returns the timer state. */
export const timerAction = (id, action) =>
  apiPost(`/tasks/${id}/timer/${action}`).then((body) => body.data)

export const getRunningTimer = () =>
  apiGet('/timer/running').then((body) => body.data)

/** Tick or untick a habit for today (a one-off task: complete / reopen). */
export const toggleDone = (task) =>
  task.is_done_today ? uncompleteTask(task.id) : completeTask(task.id)
