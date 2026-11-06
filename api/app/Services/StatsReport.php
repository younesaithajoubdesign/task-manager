<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Task;
use App\Models\TaskCompletion;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers behind GET /api/stats (SPEC §4): seconds tracked per day and
 * per category, and a completion streak per habit, for a window of days.
 *
 * Time counts on the day its entry started, like tracked_seconds_today,
 * and a running entry counts up to now.
 */
class StatsReport
{
    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     total_seconds: int,
     *     days: list<array{date: string, seconds: int}>,
     *     categories: list<array{id: int, title: string, seconds: int}>,
     *     habits: list<array<string, mixed>>
     * }
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $entries = $this->entries($from, $to);

        $secondsByDay = $entries->groupBy(fn (TimeEntry $entry) => $entry->started_at->toDateString())
            ->map(fn (Collection $day) => $day->sum(fn (TimeEntry $entry) => $entry->elapsedSeconds()));

        $secondsByCategory = $entries->groupBy('category_id')
            ->map(fn (Collection $category) => $category->sum(fn (TimeEntry $entry) => $entry->elapsedSeconds()));

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_seconds' => (int) $secondsByDay->sum(),
            'days' => collect($this->daysBetween($from, $to))
                ->map(fn (Carbon $day) => [
                    'date' => $day->toDateString(),
                    'seconds' => (int) $secondsByDay->get($day->toDateString(), 0),
                ])->all(),
            'categories' => $this->categories($secondsByCategory),
            'habits' => $this->habits($from, $to),
        ];
    }

    /**
     * Entries started inside the window, each with its task's category_id.
     *
     * @return Collection<int, TimeEntry>
     */
    private function entries(Carbon $from, Carbon $to): Collection
    {
        return TimeEntry::query()
            ->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
            ->join('sub_categories', 'sub_categories.id', '=', 'tasks.sub_category_id')
            ->where('time_entries.started_at', '>=', $from->copy()->startOfDay())
            ->where('time_entries.started_at', '<', $to->copy()->addDay()->startOfDay())
            ->select('time_entries.*', 'sub_categories.category_id')
            ->get();
    }

    /**
     * Every category in sidebar order, then sorted by time (most first).
     *
     * @param  Collection<int|string, int>  $secondsByCategory
     * @return list<array{id: int, title: string, seconds: int}>
     */
    private function categories(Collection $secondsByCategory): array
    {
        return Category::query()->orderBy('position')->orderBy('id')->get(['id', 'title'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'title' => $category->title,
                'seconds' => (int) $secondsByCategory->get($category->id, 0),
            ])
            ->sortByDesc('seconds')
            ->values()
            ->all();
    }

    /**
     * Each habit with the days it was ticked in the window, how many days
     * it was scheduled (up to today, from the day it was created), its
     * streak as of the window's last day (or today, if the window ends later),
     * and one state per day of the window for the chart:
     * done, missed, open (today, not ticked yet) or off (not scheduled).
     *
     * @return list<array<string, mixed>>
     */
    private function habits(Carbon $from, Carbon $to): array
    {
        $habits = Task::query()->recurring()->with('subCategory')->orderBy('position')->orderBy('id')->get();
        $asOf = $to->copy()->min(today());

        Task::attachStreaks($habits, $asOf);

        $completedDates = TaskCompletion::query()
            ->whereIn('task_id', $habits->modelKeys())
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')
            ->get(['task_id', 'date'])
            ->groupBy('task_id')
            ->map(fn (Collection $rows) => $rows->map(fn (TaskCompletion $row) => $row->date->toDateString())->values()->all());

        return $habits->map(function (Task $habit) use ($from, $to, $asOf, $completedDates) {
            $dates = $completedDates->get($habit->id, []);
            $firstDay = $from->copy()->max($habit->created_at->copy()->startOfDay());
            $scheduled = fn (Carbon $day) => $day->gte($firstDay) && $day->lte($asOf) && $habit->occursOn($day);

            return [
                'id' => $habit->id,
                'title' => $habit->title,
                'repeat' => $habit->repeat,
                'repeat_days' => $habit->repeat_days,
                'color' => $habit->subCategory->color,
                'streak' => $habit->streakCount,
                'completed_dates' => $dates,
                'completed_count' => count($dates),
                'scheduled_count' => collect($this->daysBetween($firstDay, $asOf))->filter($scheduled)->count(),
                'days' => collect($this->daysBetween($from, $to))
                    ->map(fn (Carbon $day) => match (true) {
                        in_array($day->toDateString(), $dates, true) => 'done',
                        ! $scheduled($day) => 'off',
                        $day->isToday() => 'open',
                        default => 'missed',
                    })->all(),
            ];
        })->all();
    }

    /**
     * @return list<Carbon>
     */
    private function daysBetween(Carbon $first, Carbon $last): array
    {
        $days = [];

        for ($day = $first->copy()->startOfDay(); $day->lte($last); $day->addDay()) {
            $days[] = $day->copy();
        }

        return $days;
    }
}
