import LoadState from './LoadState.jsx'
import QuickCapture from './QuickCapture.jsx'
import TaskRows from './TaskRows.jsx'
import { createTask, deleteTask, updateTask } from './tasksApi.js'
import { useFetch } from './useFetch.js'

/** Every open task. Becomes the Browse screen in step 6. */
export default function AllTasksView() {
  const { data: tasks, state, reload } = useFetch('/tasks')
  const afterWrite = (write) => async (...args) => {
    await write(...args)
    await reload()
  }

  return (
    <>
      <QuickCapture onCreate={afterWrite(createTask)} />
      {state !== 'ready' ? (
        <LoadState state={state} />
      ) : tasks.length === 0 ? (
        <p className="status">Nothing open. Press N to add a task.</p>
      ) : (
        <TaskRows
          tasks={tasks}
          onSave={afterWrite(updateTask)}
          onDelete={afterWrite(deleteTask)}
        />
      )}
    </>
  )
}
