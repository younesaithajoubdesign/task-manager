<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskManagerSeeder extends Seeder
{
    public function run(): void
    {
        // Sidebar, in the order of your mockup.
        // ("Developpement" spelled in English, "Authers" read as "Others" —
        //  change the labels here if you meant something else.)
        $tree = [
            'Study'       => ['Projects', 'Exercises', 'Presentations'],
            'Development' => ['Laravel', 'React', 'Practice'],
            'CHI'         => ['General'],
            'Projects'    => ['General'],
            'Sport'       => ['General'],
            'Chess'       => ['General'],
            'Films'       => ['General'],
            'Others'      => ['General'],
        ];

        $palette = ['#ec4899', '#eab308', '#22c55e', '#06b6d4', '#f97316', '#3b82f6', '#8b5cf6'];

        $subs = [];
        $categoryPosition = 0;

        foreach ($tree as $categoryTitle => $subTitles) {
            $category = Category::create([
                'title'    => $categoryTitle,
                'position' => $categoryPosition++,
            ]);

            foreach (array_values($subTitles) as $i => $subTitle) {
                $subs["$categoryTitle/$subTitle"] = SubCategory::create([
                    'category_id' => $category->id,
                    'title'       => $subTitle,
                    'color'       => $palette[($categoryPosition + $i) % count($palette)],
                    'position'    => $i,
                ]);
            }
        }

        // A few real rows so the list is never empty on day one.
        Task::create([
            'sub_category_id' => $subs['Study/Presentations']->id,
            'title'           => 'Digital Marketing presentation',
            'type'            => 'open',
            'importance'      => 'high',
            'due_at'          => now()->addDays(10)->setTime(23, 59),
        ]);

        Task::create([
            'sub_category_id' => $subs['Study/Exercises']->id,
            'title'           => 'Logo design exercises',
            'type'            => 'timed',
            'planned_minutes' => 90,
            'importance'      => 'medium',
            'due_at'          => now()->addDays(13)->setTime(23, 59),
        ]);

        // Recurring: these are the ones that make the app worth opening daily.
        Task::create([
            'sub_category_id' => $subs['Others/General']->id,
            'title'           => 'French — 30 min',
            'type'            => 'timed',
            'planned_minutes' => 30,
            'importance'      => 'high',
            'repeat'          => 'daily',
        ]);

        Task::create([
            'sub_category_id' => $subs['Others/General']->id,
            'title'           => 'English — 30 min',
            'type'            => 'timed',
            'planned_minutes' => 30,
            'importance'      => 'high',
            'repeat'          => 'daily',
        ]);

        Task::create([
            'sub_category_id' => $subs['Development/Laravel']->id,
            'title'           => 'Laracasts episode',
            'type'            => 'timed',
            'planned_minutes' => 45,
            'importance'      => 'medium',
            'repeat'          => 'weekly',
            'repeat_days'     => [1, 3, 5],   // Mon, Wed, Fri
        ]);

        Task::create([
            'sub_category_id' => $subs['Sport/General']->id,
            'title'           => 'Workout',
            'type'            => 'timed',
            'planned_minutes' => 40,
            'importance'      => 'medium',
            'repeat'          => 'weekly',
            'repeat_days'     => [2, 4, 6],   // Tue, Thu, Sat
        ]);
    }
}
