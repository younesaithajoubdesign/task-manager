import { useEffect, useRef, useState } from 'react'

const SHOW_DELAY_MS = 450

/** Only where a pointer can hover: phones never see it. */
const canHover = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches

/**
 * When to show a task's hover card: after a short delay on mouse hover,
 * hidden again on leave, click, scroll or a key. Never on touch screens.
 */
export function useTaskPeek() {
  const [anchor, setAnchor] = useState(null)
  const timer = useRef(null)

  const hide = () => {
    clearTimeout(timer.current)
    setAnchor(null)
  }

  const show = (event) => {
    if (!canHover()) return
    const row = event.currentTarget
    clearTimeout(timer.current)
    timer.current = setTimeout(() => setAnchor(row), SHOW_DELAY_MS)
  }

  useEffect(() => {
    if (!anchor) return undefined
    window.addEventListener('scroll', hide, true)
    window.addEventListener('keydown', hide)
    return () => {
      window.removeEventListener('scroll', hide, true)
      window.removeEventListener('keydown', hide)
    }
  }, [anchor])

  useEffect(() => () => clearTimeout(timer.current), [])

  return { anchor, hoverProps: { onMouseEnter: show, onMouseLeave: hide, onMouseDown: hide } }
}
