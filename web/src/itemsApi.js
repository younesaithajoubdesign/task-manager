import { apiDelete, apiPatch, apiPost } from './api.js'

/** A task's checklist (films, articles, subjects…). */
export const createItem = (taskId, attributes) =>
  apiPost(`/tasks/${taskId}/items`, attributes).then((body) => body.data)

export const updateItem = (id, attributes) =>
  apiPatch(`/items/${id}`, attributes).then((body) => body.data)

export const deleteItem = (id) => apiDelete(`/items/${id}`)
