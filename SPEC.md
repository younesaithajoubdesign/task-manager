# Personal Task Manager — Specification

Single-user web app for managing studies, habits and long-term goals.
Runs on a local machine only. Interface language: **English**.

---

## 1. Context

The owner is a design student with many parallel obligations (coursework,
projects, language learning, sport, long-term academic goals). The problem is
not a lack of tasks — it is the absence of a single place that answers
**"what do I do now?"** and **"did I actually work this week?"**.

The app is used daily, starting immediately. It must be useful on day one with
an empty database, and still useful after six months of real data.

### Priorities

| Concern | Level | Consequence |
|---|---|---|
| UX / usability | **Very high** | Every frequent action must be reachable in one or two interactions. Capture speed decides whether the app survives. |
| Scalability of the codebase | **High** | Clean separation API / UI. New task types or views must not require rewrites. |
| Visual polish | Low | Follow the design tokens in §6, nothing more. Do not spend effort on animation or decoration. |
| Security | Low | Local server, single user. One password gate, no user accounts, no roles, no CSRF ceremony. |

### Non-goals

No multi-user support, no sharing, no mobile app, no notifications by email,
no cloud sync, no team features. Do not add them "for later".

---

## 2. Stack

- **Backend**: Laravel 12, PHP 8.5, REST API returning JSON (`/api/*`)
- **Frontend**: React 19 + Vite, separate dev server
- **Database**: SQLite (`database/database.sqlite`)
- **Icons**: Google Material Symbols (Rounded)
- **Styling**: plain CSS or CSS Modules. No UI component library.

Two folders side by side:

```
task-manager/
├── api/     Laravel
└── web/     React + Vite
```

**Why SQLite**: single user, zero configuration, a backup is a file copy.
Laravel switches to MySQL later by changing `.env` only — do not write
SQLite-specific SQL.

**CORS**: `api/config/cors.php` must allow `http://localhost:5173`.

---

## 3. Data model

Already implemented. Five tables, five Eloquent models. Do not change the
schema without saying why.

```
categories        id, title, position, timestamps

sub_categories    id, category_id → categories, title,
                  color (hex, badge colour), position, timestamps

tasks             id, sub_category_id → sub_categories,
                  title, notes, link,
                  type ('timed' | 'open'), planned_minutes,
                  importance ('low' | 'medium' | 'high'),
                  status ('todo' | 'doing' | 'done'),
                  due_at, completed_at,
                  repeat (null | 'daily' | 'weekly'), repeat_days (json),
                  position, timestamps

task_completions  id, task_id → tasks, date, timestamps
                  UNIQUE (task_id, date)

time_entries      id, task_id → tasks,
                  started_at, ended_at, duration_seconds, timestamps
```

### Three rules that must never be broken

**1. The timer is server-authoritative.**
Elapsed time is always computed from `time_entries.started_at`, never held in
a JavaScript counter. A refresh, a closed tab or a reboot must not lose a
running session. The client may tick a local display for smoothness, but the
server value is the truth and the client re-syncs on load.

**2. Recurring tasks are never duplicated.**
A daily habit is *one* row in `tasks` with `repeat = 'daily'`, plus one row in
`task_completions` per day it is ticked. Never generate one task row per day.

**3. Badge colour comes from `sub_categories.color`.**
Never random, never per-task. The colour means "this belongs to Exercises".

### Model helpers (already written)

- `Task::isDoneOn(Carbon $date)` — handles both one-off and recurring
- `Task::occursOn(Carbon $date)` — does a recurring task fall on this day
- `Task::trackedSeconds(?Carbon $date)` — total tracked time
- `Task::isRecurring()`
- Scopes: `open()`, `overdue()`, `dueOn($date)`, `recurring()`
- `TimeEntry::elapsedSeconds()`, `TimeEntry::stop()`

Use them instead of rewriting the logic in controllers.

---

## 4. API contract

All routes under `/api`, all JSON. Use Form Requests for validation and API
Resources for output shape.

### Taxonomy

```
GET    /api/categories          categories with their sub-categories (sidebar)
POST   /api/categories
PATCH  /api/categories/{id}
DELETE /api/categories/{id}

POST   /api/sub-categories
PATCH  /api/sub-categories/{id}
DELETE /api/sub-categories/{id}
```

### Tasks

```
GET    /api/tasks?sub_category_id=&category_id=&status=
POST   /api/tasks
PATCH  /api/tasks/{task}
DELETE /api/tasks/{task}
POST   /api/tasks/{task}/complete
POST   /api/tasks/{task}/uncomplete
```

`GET /api/tasks` with no filter returns everything open — that is the "All"
button. Each task in the response carries its sub-category title and colour,
`tracked_seconds_today`, and `is_done_today`, so the list needs no extra calls.

### Today

```
GET    /api/today
```

Returns three groups in one call:

- `overdue` — open tasks with `due_at` in the past
- `due_today` — open tasks due today
- `habits` — recurring tasks that `occursOn(today)`, each with `is_done_today`

### Timer

```
POST   /api/tasks/{task}/timer/start
POST   /api/tasks/{task}/timer/pause
POST   /api/tasks/{task}/timer/resume
POST   /api/tasks/{task}/timer/stop
GET    /api/timer/running
```

Rules:

- **Only one timer runs at a time.** `start` closes any other open entry first.
- `start` sets the task to `status = 'doing'` and creates a `time_entry` with
  `ended_at = null`.
- `pause` calls `TimeEntry::stop()` on the running entry. `resume` opens a new
  one for the same task. The task stays `doing`.
- `stop` closes the entry and returns the task to `todo`. It does **not**
  complete the task.
- `complete` closes any running entry first, then:
  - one-off task → `status = 'done'`, `completed_at = now()`
  - recurring task → insert into `task_completions` for today, status unchanged
- `GET /api/timer/running` returns the running task with its elapsed seconds,
  or `null`. **The React app calls this on mount.** This is what makes a
  refresh harmless; without it the whole `time_entries` table is pointless.

### Stats (build last)

```
GET    /api/stats?from=&to=
```

Seconds tracked per day, per category, and a completion streak per habit.
Worthless until a month of data exists — do not build it early.

---

## 5. Screens

### 5.1 Password gate

One centred password field and a "Go" button on a black background.
Compare against a hash in `.env`, return a long-lived token kept in
`localStorage`. This is a curtain, not security — do not build user accounts.

### 5.2 Today — the real home screen

**This screen does not exist in the mockups and is the most important one.**
The app opens here, not on the category browser.

Three sections, in this order:

1. **Overdue** — most urgent, shown first, visually distinct
2. **Due today**
3. **Habits** — today's recurring tasks with a checkbox each

Every row: title, sub-category colour bar, due date, play button, and a link
icon when `link` is set. A habit row shows its streak if cheap to compute.

Empty state matters: when nothing is due, say so plainly and offer the quick
capture field.

### 5.3 Browse — the mockup screen

Left sidebar: main categories (Study, Development, CHI, Projects, Sport,
Chess, Films, Others). Selected item has a subtle raised background.

Main area: a filter row of the selected category's sub-categories, with an
"All" chip first and selected. Below it, the task list.

Task row, as in the mockup: coloured bar on the left edge, title, a second
line `Due: 20 October · 10 days left`, play button on the right.
Overdue rows show `3 days overdue` instead.

> Note on wording: the mockups say "Delay" and "days less". Correct English is
> **"Due"** and **"days left"**. Use the correct forms.

### 5.4 Timer (focus mode)

Full screen, black. A raised card holds the elapsed time in very large type
and four circular icon buttons: pause/resume, stop, complete, reset.
Close button top-right, collapse button below it.

For a `timed` task, when `planned_minutes` is reached the timer **keeps
counting** and the digits turn to the primary colour. It never stops by
itself — stopping someone who is still working is worse than letting the
number grow. A short sound at the target is welcome.

The display must survive a refresh: on mount, re-read `GET /api/timer/running`.

### 5.5 Quick capture

**The screen used most often.** A single text input, reachable from anywhere
with a keyboard shortcut (`N` or `Ctrl/Cmd + K`). Type a title, press Enter,
the task is created in the current sub-category with no further questions.
Everything else (due date, type, duration) is edited afterwards.

Do not build an eight-field form as the primary creation path.

### 5.6 Manage categories

Plain CRUD screen for categories and sub-categories, including the colour
picker. Low priority, but needed before the app holds real data.

---

## 6. Design tokens

Dark only. No light theme.

### Elevation by layers

Each stacked surface is 10% lighter than the one below:

```css
--layer-0: hsl(0 0% 0%);    /* body            */
--layer-1: hsl(0 0% 10%);   /* cards, sidebar  */
--layer-2: hsl(0 0% 20%);   /* buttons on cards */
--layer-3: hsl(0 0% 30%);   /* hover states    */
```

### Colour

```css
--text:            hsl(0 0% 100%);
--text-secondary:  hsl(0 0% 60%);
--text-disabled:   hsl(0 0% 40%);
--primary:         hsl(197 99% 50%);
```

### Rules

- **No borders.** Separation comes from elevation, like YouTube.
- **Everything is a multiple of 8px**: padding, gaps, border-radius.
  (4px allowed only for tight inline spacing.)
- Icons: Google Material Symbols Rounded.
- Keep a `tokens.css` with CSS custom properties. No hard-coded hex in
  components except sub-category colours, which come from the database.

---

## 7. Build order

Do not skip ahead. Each step must run before the next begins.

1. **Verify the database layer.** `php artisan migrate:fresh --seed` fills the
   tables; `Task::recurring()->pluck('title')` returns rows in tinker.
2. **`GET /api/tasks` + a React list that displays it.** Nothing else — no
   sidebar, no filters, no timer. This validates the whole chain
   Laravel → API → fetch → React. Everything after is repetition.
3. **Task CRUD + quick capture.**
4. **Today view.**
5. **Timer**, including `GET /api/timer/running` on mount.
6. **Sidebar, filters, browse screen.**
7. **Recurring tasks and habit ticking.**
8. **Category management.**
9. **Password gate.**
10. **Stats.**

The app should be genuinely usable from step 4.

---

## 8. Defaults chosen for open questions

These were undecided. Reasonable defaults are chosen so work is not blocked —
change them if wanted, but do not leave them unresolved.

- **`importance`** is stored but has no badge. It sets the sort order inside a
  list: high → medium → low, then by due date. If it never proves useful,
  drop the column before real data accumulates.
- **A finished timed session** keeps counting past its target in the primary
  colour. No auto-stop.
- **Completed one-off tasks** leave the list and are reachable through a
  "Done" filter. Completed habits stay in Today's habit list, ticked.
- **Sort order within a list**: overdue first, then by due date, then by
  importance, then by `position`.

---

## 9. Conventions

- Controllers stay thin. Business logic belongs in the models or in a small
  `TimerService`.
- Validation in Form Requests, output in API Resources — not inline arrays.
- No `dd()` or `console.log` left in committed code.
- Commit after each completed step of §7, with a message naming the step.
- When a decision is ambiguous, choose the option that needs fewer clicks in
  daily use, and say in one line what was chosen and why.
