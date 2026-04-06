<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->enum('audience_type', ['teachers', 'students']); // who sees it
            $table->enum('target_type', ['all', 'class', 'specific']); // targeting strategy
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable(); // null = draft
            $table->timestamps();
        });

        // Pivot: specific users targeted (teachers or individual students)
        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });

        // Pivot: class-level targeting for student announcements
        Schema::create('announcement_class_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('class_rooms')->cascadeOnDelete();
        });

        // Attachments
        Schema::create('announcement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });

        // Read receipts (optional – tracks who has seen it)
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcement_attachments');
        Schema::dropIfExists('announcement_class_targets');
        Schema::dropIfExists('announcement_targets');
        Schema::dropIfExists('announcements');
    }
};
