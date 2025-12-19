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
        Schema::create('points_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('class_rooms')->nullOnDelete();
            $table->foreignId('achievement_id')->nullable()->constrained()->nullOnDelete();
            
            $table->integer('points');
            $table->string('source')->default('achievement');
            
            $table->integer('month');
            $table->integer('year');
            
            $table->timestamps();
            
            $table->index(['student_id', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_logs');
    }
};
