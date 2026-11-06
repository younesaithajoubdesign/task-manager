<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A task's checklist: films to watch, articles to read, subjects to write
     * on. Ticked items keep their done_at, so the list doubles as a history.
     */
    public function up(): void
    {
        Schema::create('task_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('link', 2048)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'done_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_items');
    }
};
