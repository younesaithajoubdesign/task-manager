import { apiDelete, apiPatch, apiPost } from './api.js'

export const createTask = (attributes) =>
  apiPost('/tasks', attributes).then((body) => body.data)

export const updateTask = (id, attributes) =>
  apiPatch(`/tasks/${id}`, attributes).then((body) => body.data)

export const deleteTask = (id) => apiDelete(`/tasks/${id}`)
