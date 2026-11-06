import { useSyncExternalStore } from 'react'

/** The single breakpoint (SPEC addendum §12): below 768px is the phone layout. */
export const MOBILE_QUERY = '(max-width: 767.98px)'

function subscribe(onChange) {
  const media = window.matchMedia(MOBILE_QUERY)
  media.addEventListener('change', onChange)
  return () => media.removeEventListener('change', onChange)
}

const getSnapshot = () => window.matchMedia(MOBILE_QUERY).matches

/**
 * For structure only (which screen, which navigation). Sizes and spacing
 * stay in CSS, under the same @media query.
 */
export function useIsMobile() {
  return useSyncExternalStore(subscribe, getSnapshot)
}
