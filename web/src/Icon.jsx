// The app's icon set (Material Symbols Rounded, provided by the owner).
// Paths are inlined so there is no font or network dependency.
// Colour follows `currentColor`, so icons take the text colour of their parent
// (SPEC §6: no hard-coded colours in components).
const PATHS = {
  close:
    'M6.4,19L5,17.6L10.6,12L5,6.4L6.4,5L12,10.6L17.6,5L19,6.4L13.4,12L19,17.6L17.6,19L12,13.4L6.4,19Z',
  play:
    'M9.5,16.5l7-4.5l-7-4.5v9ZM12,22q-2.07,0-3.9-0.79q-1.82-0.79-3.17-2.14Q3.58,17.73 2.79,15.9T2,12Q2,9.92 2.79,8.1Q3.58,6.27 4.93,4.93Q6.28,3.57 8.1,2.79Q9.93,2 12,2t3.9,0.79t3.17,2.14q1.35,1.35 2.14,3.17Q22,9.92 22,12t-0.79,3.9t-2.14,3.17q-1.35,1.35-3.17,2.14T12,22Zm0-2q3.35,0 5.68-2.32T20,12T17.68,6.32T12,4T6.33,6.32T4,12t2.33,5.68T12,20Zm0-8Z',
  notifications:
    'M4,19V17H6V10Q6,7.93 7.25,6.31T10.5,4.2V3.5q0-0.63 0.44-1.06T12,2t1.06,0.44T13.5,3.5V4.2q2,0.5 3.25,2.11T18,10v7h2v2H4Zm8-7.5ZM12,22q-0.82,0-1.41-0.59T10,20h4q0,0.82-0.59,1.41T12,22ZM8,17h8V10q0-1.65-1.17-2.82T12,6T9.18,7.18T8,10v7Z',
  add:
    'M11,17h2V13h4V11H13V7H11v4H7v2h4v4Zm1,5q-2.07,0-3.9-0.79q-1.82-0.79-3.17-2.14Q3.58,17.73 2.79,15.9T2,12Q2,9.92 2.79,8.1Q3.58,6.27 4.93,4.93Q6.28,3.57 8.1,2.79Q9.93,2 12,2t3.9,0.79t3.17,2.14q1.35,1.35 2.14,3.17Q22,9.92 22,12t-0.79,3.9t-2.14,3.17q-1.35,1.35-3.17,2.14T12,22Zm0-2q3.35,0 5.68-2.32T20,12T17.68,6.32T12,4T6.33,6.32T4,12t2.33,5.68T12,20Zm0-8Z',
  delete:
    'M7,21q-0.82,0-1.41-0.59T5,19V6H4V4H9V3h6V4h5V6H19V19q0,0.82-0.59,1.41T17,21H7ZM17,6H7V19H17V6ZM9,17h2V8H9v9Zm4,0h2V8H13v9ZM7,6V19V6Z',
  stop:
    'M8,16h8V8H8v8Zm4,6q-2.07,0-3.9-0.79q-1.82-0.79-3.17-2.14Q3.58,17.73 2.79,15.9T2,12Q2,9.92 2.79,8.1Q3.58,6.27 4.93,4.93Q6.28,3.57 8.1,2.79Q9.93,2 12,2t3.9,0.79t3.17,2.14q1.35,1.35 2.14,3.17Q22,9.92 22,12t-0.79,3.9t-2.14,3.17q-1.35,1.35-3.17,2.14T12,22Zm0-2q3.35,0 5.68-2.32T20,12T17.68,6.32T12,4T6.33,6.32T4,12t2.33,5.68T12,20Zm0-8Z',
  // Derived from `stop`: same ring, two bars instead of the square.
  pause:
    'M9,16h2V8H9v8Zm4,0h2V8H13v8Zm-1,6q-2.07,0-3.9-0.79q-1.82-0.79-3.17-2.14Q3.58,17.73 2.79,15.9T2,12Q2,9.92 2.79,8.1Q3.58,6.27 4.93,4.93Q6.28,3.57 8.1,2.79Q9.93,2 12,2t3.9,0.79t3.17,2.14q1.35,1.35 2.14,3.17Q22,9.92 22,12t-0.79,3.9t-2.14,3.17q-1.35,1.35-3.17,2.14T12,22Zm0-2q3.35,0 5.68-2.32T20,12T17.68,6.32T12,4T6.33,6.32T4,12t2.33,5.68T12,20Zm0-8Z',
  replay:
    'M8.49,21.29q-1.64-0.71-2.85-1.93T3.71,16.51T3,13H5q0,2.92 2.04,4.96T12,20t4.96-2.04Q19,15.93 19,13q0-2.93-2.04-4.96T12,6H11.85L13.4,7.55L12,9L8,5l4-4l1.4,1.45L11.85,4H12q1.88,0 3.51,0.71q1.64,0.71 2.85,1.93t1.93,2.85T21,13t-0.71,3.51t-1.93,2.85t-2.85,1.93T12,22T8.49,21.29Z',
  minimize:
    'M6,21V18H3V16H8v5H6Zm10,0V16h5v2H18v3H16ZM3,8V6H6V3H8V8H3ZM16,8V3h2V6h3V8H16Z',
  check:
    'M10.6,16.6l7.05-7.05l-1.4-1.4L10.6,13.8L7.75,10.95l-1.4,1.4L10.6,16.6ZM12,22q-2.07,0-3.9-0.79q-1.82-0.79-3.17-2.14Q3.58,17.73 2.79,15.9T2,12Q2,9.92 2.79,8.1Q3.58,6.27 4.93,4.93Q6.28,3.57 8.1,2.79Q9.93,2 12,2t3.9,0.79t3.17,2.14q1.35,1.35 2.14,3.17Q22,9.92 22,12t-0.79,3.9t-2.14,3.17q-1.35,1.35-3.17,2.14T12,22Zm0-2q3.35,0 5.68-2.32T20,12T17.68,6.32T12,4T6.33,6.32T4,12t2.33,5.68T12,20Zm0-8Z',
}

/**
 * <Icon name="play" />            decorative (inside a labelled button)
 * <Icon name="delete" label="Delete task" />   standalone, announced
 */
export default function Icon({ name, size = 24, label, className }) {
  const d = PATHS[name]
  if (!d) throw new Error(`Unknown icon "${name}"`)

  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      width={size}
      height={size}
      fill="currentColor"
      className={className}
      role={label ? 'img' : undefined}
      aria-label={label}
      aria-hidden={label ? undefined : true}
      focusable="false"
    >
      <path d={d} />
    </svg>
  )
}
