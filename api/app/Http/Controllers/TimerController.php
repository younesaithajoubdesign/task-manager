<?php

namespace App\Http\Controllers;

use App\Http\Resources\TimerResource;
use App\Models\Task;
use App\Services\TimerService;
use Illuminate\Http\JsonResponse;

class TimerController extends Controller
{
    public function __construct(private TimerService $timer) {}

    /**
     * The running timer, or null. The React app calls this on mount so a
     * refresh, a closed tab or a reboot never loses a running session.
     */
    public function running(): JsonResponse|TimerResource
    {
        $task = $this->timer->running();

        return $task ? $this->present($task) : response()->json(['data' => null]);
    }

    public function start(Task $task): TimerResource
    {
        return $this->present($this->timer->start($task));
    }

    public function pause(Task $task): TimerResource
    {
        return $this->present($this->timer->pause($task));
    }

    public function resume(Task $task): TimerResource
    {
        return $this->present($this->timer->resume($task));
    }

    public function stop(Task $task): TimerResource
    {
        return $this->present($this->timer->stop($task));
    }

    public function reset(Task $task): TimerResource
    {
        return $this->present($this->timer->reset($task));
    }

    private function present(Task $task): TimerResource
    {
        return TimerResource::make($task->refresh()->load([...Task::listRelations(), 'runningEntry']));
    }
}
