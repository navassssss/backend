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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duty_id')->nullable()->constrained('duties')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->text('instructions')->nullable();

            // schedule time for that task
            $table->date('scheduled_date');
            $table->time('scheduled_time')->nullable();

            // statuses for tracking progress
            $table->enum('status', ['pending', 'completed', 'missed'])
                ->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
