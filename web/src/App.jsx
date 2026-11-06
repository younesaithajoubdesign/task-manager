import QuickCapture from './QuickCapture.jsx'
import TaskList from './TaskList.jsx'
import { useTasks } from './useTasks.js'
import './App.css'

function App() {
  const { tasks, state, createTask, updateTask, deleteTask } = useTasks()

  return (
    <main className="app">
      <h1 className="app__title">All tasks</h1>
      <QuickCapture onCreate={createTask} />
      <TaskList
        tasks={tasks}
        state={state}
        onSave={updateTask}
        onDelete={deleteTask}
      />
    </main>
  )
}

export default App
