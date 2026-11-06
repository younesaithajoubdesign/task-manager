import { apiDelete, apiPatch, apiPost } from './api.js'

export const createCategory = (title) =>
  apiPost('/categories', { title }).then((body) => body.data)

export const updateCategory = (id, attributes) =>
  apiPatch(`/categories/${id}`, attributes).then((body) => body.data)

/** Without `force`, the API answers 409 when tasks would be deleted too. */
export const deleteCategory = (id, { force = false } = {}) =>
  apiDelete(`/categories/${id}${force ? '?force=1' : ''}`)

export const createSubCategory = (attributes) =>
  apiPost('/sub-categories', attributes).then((body) => body.data)

export const updateSubCategory = (id, attributes) =>
  apiPatch(`/sub-categories/${id}`, attributes).then((body) => body.data)

export const deleteSubCategory = (id, { force = false } = {}) =>
  apiDelete(`/sub-categories/${id}${force ? '?force=1' : ''}`)
