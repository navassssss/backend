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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();

            // Related to a task (optional)
            $table->foreignId('task_id')->nullable()
                ->constrained('tasks')->nullOnDelete();

            // Related to a duty directly (optional)
            $table->foreignId('duty_id')->nullable()
                ->constrained('duties')->nullOnDelete();

            $table->text('description');
            $table->string('attachment')->nullable();

            // Review flow
            $table->enum('status', ['submitted', 'approved', 'rejected'])
                ->default('submitted');
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
