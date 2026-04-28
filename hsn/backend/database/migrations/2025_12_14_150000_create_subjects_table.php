<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->foreignId('class_id')->constrained('class_rooms')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users');
            $table->integer('final_max_marks')->default(30);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            
            $table->unique(['code', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
