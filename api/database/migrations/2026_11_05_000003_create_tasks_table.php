<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_category_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('link')->nullable();

            // 'timed'  -> a focused session of planned_minutes
            // 'open'   -> no duration decided in advance
            $table->string('type')->default('open');
            $table->unsignedInteger('planned_minutes')->nullable();

            $table->string('importance')->default('medium');  // low | medium | high
            $table->string('status')->default('todo');        // todo | doing | done

            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            // Recurrence. null = one-off task.
            // 'daily'  -> every day
            // 'weekly' -> on the days listed in repeat_days ([1..7], 1 = Monday)
            $table->string('repeat')->nullable();
            $table->json('repeat_days')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
