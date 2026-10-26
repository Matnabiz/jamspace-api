<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->unique(['user_id', 'date', 'start_time'], 'availability_user_date_start_unique');
            $table->index(['date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_slots');
    }
};
