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
        Schema::create('duties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // responsibility = ongoing, rotational = rotating duty
            $table->enum('type', ['responsibility', 'rotational'])->default('responsibility');

            // frequency of reporting / tasks
            $table->enum('frequency', ['none', 'daily', 'weekly', 'monthly', 'custom'])
                ->default('none');

            // for custom patterns (optional, can use later)
            $table->json('custom_days')->nullable(); // e.g. ["mon","wed"] or [1,15]

            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duties');
    }
};
