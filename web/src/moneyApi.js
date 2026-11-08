import { apiDelete, apiPatch, apiPost } from './api.js'

/** Personal money: incomes and expenses. */
export const createMoneyEntry = (attributes) => apiPost('/money', attributes).then((body) => body.data)

export const updateMoneyEntry = (id, attributes) =>
  apiPatch(`/money/${id}`, attributes).then((body) => body.data)

export const deleteMoneyEntry = (id) => apiDelete(`/money/${id}`)
