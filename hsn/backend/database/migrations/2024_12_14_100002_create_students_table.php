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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('class_rooms')->nullOnDelete();
            
            $table->string('username')->unique();
            $table->string('roll_number')->nullable();
            $table->string('photo')->nullable();
            $table->date('joined_at')->default(now());
            
            $table->integer('total_points')->default(0);
            
            $table->timestamps();
            
            // Indexes for leaderboards
            $table->index(['total_points', 'id']);
            $table->index(['class_id', 'total_points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
