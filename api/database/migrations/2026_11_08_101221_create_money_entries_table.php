<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personal money: one row per income or expense, in centimes (no float
     * rounding), on the day it happened. The category is free text.
     */
    public function up(): void
    {
        Schema::create('money_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // income | expense
            $table->unsignedBigInteger('amount_cents');
            $table->string('title');
            $table->string('category', 60)->nullable();
            $table->date('occurred_on');
            $table->timestamps();

            $table->index(['occurred_on', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_entries');
    }
};
