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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');

            // Dynamic category
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('issue_categories')
                ->nullOnDelete();

            // Priority & status
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'resolved', 'forwarded'])->default('open');

            // Visibility: 'public' (all staff) or 'restricted' (creator + assignee + principal)
            $table->enum('visibility', ['public', 'restricted'])->default('public');

            // Who created the issue
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            // Who is currently responsible (may be null until forwarded)
            $table->foreignId('responsible_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Optional links to duty / teacher / task
            $table->foreignId('duty_id')
                ->nullable()
                ->constrained('duties')
                ->nullOnDelete();

            $table->foreignId('related_teacher_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tasks')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
