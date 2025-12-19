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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('class_rooms')->cascadeOnDelete();
            $table->date('date');
            $table->enum('session', ['morning', 'afternoon'])->default('morning');
            $table->foreignId('marked_by')->constrained('users'); // Teacher who marked it
            $table->timestamps();

            // Prevent duplicate attendance for same class, date, session
            $table->unique(['class_id', 'date', 'session']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'late', 'leave'])->default('present');
            $table->string('remarks')->nullable();
            $table->timestamps();

            // Index for faster queries
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendances');
    }
};
