import { useState } from 'react'
import LoadState from './LoadState.jsx'
import QuickCapture from './QuickCapture.jsx'
import TaskRows from './TaskRows.jsx'
import { createTask, deleteTask, toggleDone, updateTask } from './tasksApi.js'
import { useFetch } from './useFetch.js'
import { useTimer } from './timerContext.js'

const ALL = 'all'
const STORAGE_KEY = 'browse.category'

// Remember the last category between visits (a per-browser convenience).
function readStoredCategory() {
  try {
    const value = localStorage.getItem(STORAGE_KEY)
    return value === null || value === ALL ? ALL : Number(value)
  } catch {
    return ALL
  }
}

function storeCategory(value) {
  try {
    localStorage.setItem(STORAGE_KEY, String(value))
  } catch {
    // Storage blocked: the choice just isn't remembered.
  }
}

function tasksPath(categoryId, subId, showDone) {
  const params = new URLSearchParams()
  if (categoryId !== ALL) params.set('category_id', categoryId)
  if (subId !== null) params.set('sub_category_id', subId)
  if (showDone) params.set('status', 'done')
  const query = params.toString()
  return query ? `/tasks?${query}` : '/tasks'
}

function Sidebar({ categories, selectedId, onSelect }) {
  const total = categories.reduce((sum, c) => sum + c.open_tasks_count, 0)
  const items = [{ id: ALL, title: 'All', open_tasks_count: total }, ...categories]

  return (
    <nav className="sidebar" aria-label="Categories">
      {items.map((category) => (
        <button
          key={category.id}
          type="button"
          className={`sidebar__item ${selectedId === category.id ? 'sidebar__item--on' : ''}`}
          aria-current={selectedId === category.id ? 'true' : undefined}
          onClick={() => onSelect(category.id)}
        >
          <span className="sidebar__title">{category.title}</span>
          {category.open_tasks_count > 0 && (
            <span className="sidebar__count">{category.open_tasks_count}</span>
          )}
        </button>
      ))}
    </nav>
  )
}

function Filters({ subCategories, subId, onSub, showDone, onDone }) {
  return (
    <div className="filters" role="toolbar" aria-label="Filters">
      {subCategories.length > 0 && (
        <>
          <button
            type="button"
            className={`chip ${subId === null ? 'chip--on' : ''}`}
            aria-pressed={subId === null}
            onClick={() => onSub(null)}
          >
            All
          </button>
          {subCategories.map((sub) => (
            <button
              key={sub.id}
              type="button"
              className={`chip chip--sub ${subId === sub.id ? 'chip--on' : ''}`}
              style={{ '--sub-color': sub.color }}
              aria-pressed={subId === sub.id}
              onClick={() => onSub(sub.id)}
            >
              {sub.title}
            </button>
          ))}
        </>
      )}
      <span className="filters__spacer" />
      <button
        type="button"
        className={`chip ${showDone ? 'chip--on' : ''}`}
        aria-pressed={showDone}
        onClick={() => onDone(!showDone)}
      >
        Done
      </button>
    </div>
  )
}

/** SPEC §5.3 — sidebar of categories, sub-category chips, task list. */
export default function BrowseView() {
  const [categoryId, setCategoryId] = useState(readStoredCategory)
  const [subId, setSubId] = useState(null)
  const [showDone, setShowDone] = useState(false)

  const { version, sync } = useTimer()
  const categoriesFetch = useFetch('/categories', version)
  const tasksFetch = useFetch(tasksPath(categoryId, subId, showDone), version)

  if (categoriesFetch.state !== 'ready') return <LoadState state={categoriesFetch.state} />

  const categories = categoriesFetch.data
  // A remembered category may have been deleted since: fall back to All.
  const category = categories.find((c) => c.id === categoryId) ?? null
  const selectedId = category ? category.id : ALL
  const sub = category?.sub_categories.find((s) => s.id === subId) ?? null

  function selectCategory(id) {
    setCategoryId(id)
    setSubId(null)
    storeCategory(id)
  }

  const afterWrite = (write) => async (...args) => {
    await write(...args)
    await Promise.all([tasksFetch.reload(), categoriesFetch.reload()])
  }

  // Quick capture lands in what is on screen: the selected sub-category,
  // else the category's first one; under All the server picks (last used).
  const captureSub = sub ?? category?.sub_categories[0] ?? null
  const placeholder = captureSub
    ? `Add to ${category.title} · ${captureSub.title}…`
    : 'Add a task…'

  const tasks = tasksFetch.data ?? []
  const where = sub ? sub.title : category ? category.title : 'any category'

  return (
    <div className="browse">
      <Sidebar categories={categories} selectedId={selectedId} onSelect={selectCategory} />

      <div className="browse__main">
        <h2 className="browse__title">{category ? category.title : 'All tasks'}</h2>

        <Filters
          subCategories={category?.sub_categories ?? []}
          subId={sub ? sub.id : null}
          onSub={setSubId}
          showDone={showDone}
          onDone={setShowDone}
        />

        <QuickCapture
          onCreate={afterWrite(createTask)}
          defaults={captureSub ? { sub_category_id: captureSub.id } : {}}
          placeholder={placeholder}
        />

        {tasksFetch.state !== 'ready' ? (
          <LoadState state={tasksFetch.state} />
        ) : tasks.length === 0 ? (
          <p className="status">
            {showDone
              ? `Nothing completed in ${where} yet.`
              : `No open tasks in ${where}. Press N to add one.`}
          </p>
        ) : (
          <TaskRows
            tasks={tasks}
            onSave={afterWrite(updateTask)}
            onDelete={afterWrite(deleteTask)}
            onToggleDone={afterWrite((task) => toggleDone(task).then(sync))}
          />
        )}
      </div>
    </div>
  )
}
