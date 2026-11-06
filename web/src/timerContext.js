import { createContext, useContext } from 'react'

export const TimerContext = createContext(null)

/** Timer state and actions; see TimerProvider. */
export function useTimer() {
  return useContext(TimerContext)
}
