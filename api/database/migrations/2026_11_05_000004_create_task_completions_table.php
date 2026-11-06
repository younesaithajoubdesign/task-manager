<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per day a recurring task was completed.
        // A recurring task is never duplicated: it stays a single row in `tasks`.
        Schema::create('task_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->unique(['task_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_completions');
    }
};
