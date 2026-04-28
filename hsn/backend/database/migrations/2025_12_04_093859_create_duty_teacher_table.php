<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('duty_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duty_id')
                ->constrained('duties')
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('assigned_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('start_date')->nullable(); // for rotations if needed
            $table->date('end_date')->nullable();

            $table->unsignedInteger('order_index')->nullable(); // rotation order
            $table->unique(['duty_id', 'teacher_id']);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duty_teacher');
    }
};
