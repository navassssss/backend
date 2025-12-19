<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cce_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained('cce_works')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->string('file_url')->nullable();
            $table->decimal('marks_obtained', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users');
            $table->timestamp('evaluated_at')->nullable();
            $table->enum('status', ['pending', 'submitted', 'evaluated'])->default('pending');
            $table->timestamps();
            
            $table->unique(['work_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cce_submissions');
    }
};
