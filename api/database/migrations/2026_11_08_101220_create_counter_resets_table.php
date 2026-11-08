<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The days-without counter (one unnamed bad habit): each row is a moment
     * it restarted from zero. The latest one is the current start; the first
     * one is when counting began.
     */
    public function up(): void
    {
        Schema::create('counter_resets', function (Blueprint $table) {
            $table->id();
            $table->timestamp('reset_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counter_resets');
    }
};
