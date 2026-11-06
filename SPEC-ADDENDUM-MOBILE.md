# SPEC Addendum — Multi-device & offline reading

Extends `SPEC.md`. Sections continue its numbering. Read `SPEC.md` first.

---

## 10. New requirement

The app must be usable from **two devices**: a MacBook and a phone, with the
same data on both. It must remain **readable without a connection** (train,
school, poor signal).

### Explicitly out of scope

**Offline writing is not supported.** No local mutation queue, no IndexedDB
outbox, no sync engine, no conflict resolution. When the device is offline the
app is **read-only** and says so.

This is a deliberate decision. It keeps the architecture as it is today:
one server, one SQLite database, two browsers talking to it. There is nothing
to synchronise, so no sync bugs.

### What does NOT change

- **No schema change.** Auto-increment ids stay. No UUIDs, no soft deletes,
  no `synced_at`.
- **No native app.** No React Native, no Capacitor, no app store. A PWA
  installed to the home screen gives an icon and a full-screen launch, which
  is all that is needed.
- **The API contract** in §4 stays exactly as it is.

### Free side effect

Because the timer is server-authoritative (§3, rule 1), it already works
across devices: start a session on the Mac and the phone shows it running.
The "only one timer at a time" rule now spans devices, which is the correct
behaviour. Nothing to build.

---

## 11. Deployment

The app moves from `php artisan serve` on localhost to a small server
reachable from anywhere.

### Target setup

- A cheap VPS (Hetzner CX22 or equivalent, ~€4/month), Debian or Ubuntu
- **Caddy** as web server — it obtains and renews HTTPS certificates
  automatically, which nginx does not
- PHP-FPM 8.5
- **SQLite stays.** Single user, a few thousand rows a year. Do not migrate to
  MySQL; it would add a daemon to maintain for no gain.
- A domain or subdomain pointing at the server

Fly.io with a persistent volume is an acceptable alternative if Docker is
preferred, but a plain VPS is simpler to reason about and to debug.

### Build and serve

React is built (`npm run build`) and its `dist/` is served as static files by
Caddy. Laravel serves `/api/*` only. One origin, so the CORS configuration
from step 2 becomes unnecessary in production — keep it for local development.

### Backups

A daily cron copying `database.sqlite` to a dated file, keeping the last 30:

```
0 3 * * * cp /srv/app/api/database/database.sqlite \
  /srv/backups/db-$(date +\%F).sqlite
```

A backup is a file copy. That is the main reason SQLite was chosen.

### Before deploying — LAN test first

Before touching a server, test the mobile layout over the local network:

```bash
php artisan serve --host=0.0.0.0
```

Then open `http://<mac-local-ip>:8000` from the phone on the same WiFi.
This costs nothing and shows immediately how much work §12 really needs.

---

## 12. Mobile layout

**This is where most of the effort goes.** Usability is the project's first
priority, and the current layout is desktop-only.

### Breakpoint

Single breakpoint at **768px**. Below it, the mobile layout; above it, the
existing desktop layout unchanged. Do not build three layouts.

### Navigation

The sidebar does not work on a phone. Replace it below 768px with a
**bottom tab bar**, four items:

| Tab | Content |
|---|---|
| Today | The §5.2 view — the default tab |
| Browse | Drill-down: category list → tasks of that category |
| Add | Opens quick capture directly |
| Stats | Placeholder until §10 of SPEC.md is built |

"Browse" on mobile is a drill-down, not a sidebar: tapping a category pushes a
screen showing its sub-category chips and its tasks, with a back button. This
is the standard pattern on iOS and needs no explanation to the user.

### Touch rules

- Minimum touch target **44 × 44 px**, including the play button on each row
- No hover-only affordance — anything reachable only on hover is unreachable
- Bottom tab bar must sit above the iOS home indicator:
  `padding-bottom: env(safe-area-inset-bottom)`
- Quick capture must open the keyboard immediately (`autofocus`) and submit on
  the keyboard's "Go" key

### Timer on mobile

The §5.4 full-screen timer already suits a phone. Keep the large digits, and
make sure the four control buttons meet the 44px rule — in the mockup they are
smaller.

---

## 13. PWA and offline reading

### Installability

Use `vite-plugin-pwa`. The manifest needs:

```json
{
  "name": "Task Manager",
  "short_name": "Tasks",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#000000",
  "theme_color": "#000000",
  "icons": [192px, 512px, and a 512px maskable]
}
```

Add `<link rel="apple-touch-icon">` — iOS ignores the manifest icons.

### Caching strategy

- **App shell** (HTML, JS, CSS, fonts, icons): precache, cache-first
- **`GET /api/*`**: NetworkFirst with a cache fallback. Fresh data when online,
  last known data when offline.
- **All mutations** (POST, PATCH, DELETE): never cached, never queued. They
  fail when offline, and that is intended.

### Offline UI — the part that must not be sloppy

An app that silently pretends to work offline is worse than one that refuses.

- A persistent banner when offline: *"Offline — showing data from
  <time>. Changes are disabled."*
- Disable the quick capture field, the complete checkboxes, and the timer
  buttons while offline. Disabled and visibly so, not clickable-then-failing.
- On reconnection, refetch automatically and remove the banner.
- Detect with `navigator.onLine` plus the `online`/`offline` events, and treat
  a failed API call as offline too — `navigator.onLine` lies on captive portals.

### The timer is online-only

Starting, pausing or stopping a timer requires the server. This follows from
§3 rule 1 and is not a bug to work around. If a session is running when the
connection drops, the timer keeps displaying locally but the controls are
disabled until the connection returns; the server's `started_at` is still the
truth, so nothing is lost.

---

## 14. Security, now that it is public

§2 said security was low priority because the app was local. **That changes
the moment it is reachable from the internet.** It is still only task data,
but the bar moves from "none" to "basic".

- **HTTPS only.** Caddy does this automatically. No plain HTTP.
- **A real password**, bcrypt-hashed in `.env`, not a four-character one.
- **Rate limit the login route** — Laravel's `throttle:5,1` middleware.
  Without it, a public endpoint accepting unlimited password attempts is an
  open door.
- `APP_DEBUG=false` and `APP_ENV=production`. A Laravel stack trace in the
  browser exposes paths and configuration.
- Keep the token in `localStorage` as planned, but give it an expiry
  (90 days) and a logout route.

Still no user accounts, no roles, no password reset. One password, one user.

---

## 15. Build order for this addendum

Continues §7 of `SPEC.md`, which must be finished first.

11. **LAN test.** `--host=0.0.0.0`, open from the phone, note what breaks.
12. **Mobile layout** (§12). The biggest piece. Test on the real phone, not in
    Chrome's device emulator.
13. **Deployment** (§11) plus the security items of §14.
14. **PWA and offline reading** (§13).

Step 11 takes ten minutes and determines how long step 12 takes. Do it first.
