<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;

/**
 * Timer rules from SPEC §4. The server is the only source of truth:
 * elapsed time always comes from time_entries.started_at.
 */
class TimerService
{
    /**
     * Start (or resume) timing a task. Only one timer runs at a time, so any
     * other open entry is closed first. Starting a task that is already
     * running is a no-op rather than a second entry.
     */
    public function start(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            $this->closeRunningEntries(exceptTaskId: $task->id);

            if (! $task->runningEntry()->exists()) {
                $task->timeEntries()->create(['started_at' => now()]);
            }

            $task->update(['status' => 'doing']);

            return $task;
        });
    }

    /**
     * Close the running entry. The task stays "doing".
     */
    public function pause(Task $task): Task
    {
        $task->runningEntry?->stop();

        return $task;
    }

    /**
     * Open a new entry for the same task (same rules as start).
     */
    public function resume(Task $task): Task
    {
        return $this->start($task);
    }

    /**
     * Close the entry and return the task to "todo". It does not complete it.
     */
    public function stop(Task $task): Task
    {
        $task->runningEntry?->stop();
        $task->update(['status' => 'todo']);

        return $task;
    }

    /**
     * Throw away today's tracked time for the task. A running timer starts
     * again from zero; a paused one stays paused at zero.
     */
    public function reset(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            $wasRunning = $task->runningEntry()->exists();

            $task->timeEntries()->whereDate('started_at', today()->toDateString())->delete();

            if ($wasRunning) {
                $task->timeEntries()->create(['started_at' => now()]);
            }

            return $task;
        });
    }

    /**
     * Complete a task, closing its running entry first.
     * One-off: status done. Recurring: one completion row for today,
     * status unchanged (SPEC §3 rule 2 — never a new task row).
     */
    public function complete(Task $task): Task
    {
        return DB::transaction(function () use ($task) {
            $task->runningEntry?->stop();

            if ($task->isRecurring()) {
                // whereDate, not firstOrCreate: the date cast stores "Y-m-d 00:00:00",
                // which a plain "Y-m-d" lookup would miss.
                if (! $task->completions()->whereDate('date', today()->toDateString())->exists()) {
                    $task->completions()->create(['date' => today()]);
                }
            } else {
                $task->update(['status' => 'done']);
            }

            return $task;
        });
    }

    /**
     * Undo a completion: reopen a one-off task, or untick a habit for today.
     */
    public function uncomplete(Task $task): Task
    {
        if ($task->isRecurring()) {
            $task->completions()->whereDate('date', today()->toDateString())->delete();
        } else {
            $task->update(['status' => 'todo']);
        }

        return $task;
    }

    /**
     * The task whose timer is running, if any.
     */
    public function running(): ?Task
    {
        return TimeEntry::query()
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first()
            ?->task;
    }

    private function closeRunningEntries(int $exceptTaskId): void
    {
        TimeEntry::query()
            ->whereNull('ended_at')
            ->where('task_id', '!=', $exceptTaskId)
            ->get()
            ->each(fn (TimeEntry $entry) => $entry->stop());
    }
}
