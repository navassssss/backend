<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cce_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->integer('level'); // 1-4
            $table->integer('week');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('tool_method')->nullable();
            $table->date('issued_date');
            $table->date('due_date');
            $table->integer('max_marks');
            $table->enum('submission_type', ['online', 'offline'])->default('offline');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            
            $table->index(['subject_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cce_works');
    }
};
