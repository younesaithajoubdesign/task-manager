import { useEffect, useRef, useState } from 'react'

const LONG_PRESS_MS = 500
const MOVE_TOLERANCE_PX = 10

/**
 * When the finger lifts after a long press, the browser still sends a click,
 * which would land on whatever the editor put under the finger. Drop it.
 */
function swallowNextClick() {
  const swallow = (event) => {
    event.preventDefault()
    event.stopPropagation()
    window.removeEventListener('click', swallow, true)
  }
  window.addEventListener('click', swallow, true)
  setTimeout(() => window.removeEventListener('click', swallow, true), 1000)
}

/**
 * How a task card opens its editor, so a stray click or tap never does:
 * - mouse: double-click (a single click does nothing);
 * - touch: press and hold ~half a second; a quick tap or a scroll does nothing;
 * - keyboard: Enter or Space on the focused card.
 * Returns the handlers for the card's button, and `pressing` while a finger
 * is held down (for visual feedback).
 */
export function useOpenGesture(onOpen) {
  const [pressing, setPressing] = useState(false)
  const press = useRef(null) // { timer, x, y }

  const cancel = () => {
    if (press.current) clearTimeout(press.current.timer)
    press.current = null
    setPressing(false)
  }

  useEffect(() => () => press.current && clearTimeout(press.current.timer), [])

  const handlers = {
    onDoubleClick: (event) => {
      event.preventDefault()
      onOpen()
    },
    onClick: (event) => {
      // detail === 0: a keyboard "click" (Enter / Space), not the mouse.
      if (event.detail === 0) onOpen()
    },
    onPointerDown: (event) => {
      if (event.pointerType === 'mouse') return
      cancel()
      setPressing(true)
      press.current = {
        x: event.clientX,
        y: event.clientY,
        timer: setTimeout(() => {
          press.current = null
          setPressing(false)
          navigator.vibrate?.(15)
          swallowNextClick()
          onOpen()
        }, LONG_PRESS_MS),
      }
    },
    onPointerMove: (event) => {
      const start = press.current
      if (!start) return
      if (Math.hypot(event.clientX - start.x, event.clientY - start.y) > MOVE_TOLERANCE_PX) cancel()
    },
    onPointerUp: cancel,
    onPointerCancel: cancel, // the browser took over: a scroll
    onPointerLeave: cancel,
    // A long press would otherwise open the phone's menu (Android) or callout (iOS).
    onContextMenu: (event) => {
      if (press.current || event.nativeEvent.pointerType !== 'mouse') event.preventDefault()
    },
  }

  return { pressing, handlers }
}
