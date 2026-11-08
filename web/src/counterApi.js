import { apiDelete, apiPost } from './api.js'

/** The days-without counter. Each call returns the whole counter again. */
export const resetCounter = (resetAt) =>
  apiPost('/counter/resets', resetAt ? { reset_at: resetAt } : {}).then((body) => body.data)

export const deleteCounterReset = (id) => apiDelete(`/counter/resets/${id}`).then((body) => body.data)
