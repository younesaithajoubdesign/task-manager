import { useState } from 'react'
import QuickCapture from './QuickCapture.jsx'
import { createTask } from './tasksApi.js'

/**
 * The phone's "Add" tab (SPEC addendum §12): quick capture with the keyboard
 * already open. The server files the task where the last one went (§5.5);
 * each title added here is listed with its place so it can be checked.
 */
export default function AddView() {
  const [added, setAdded] = useState([])

  async function create(fields) {
    const task = await createTask(fields)
    setAdded((list) => [task, ...list].slice(0, 5))
  }

  return (
    <div className="add">
      <QuickCapture onCreate={create} placeholder="What needs doing?" autoFocus />
      {added.length > 0 ? (
        <ul className="add__list" aria-label="Just added">
          {added.map((task) => (
            <li key={task.id} className="add__item" style={{ '--sub-color': task.sub_category?.color }}>
              <span className="add__title">{task.title}</span>
              <span className="add__where">{task.sub_category?.title}</span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="status">Type a title and press Go. Edit details later from Today or Browse.</p>
      )}
    </div>
  )
}
