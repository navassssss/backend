<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['deposit', 'expense']);
            $table->decimal('amount', 10, 2);
            $table->string('purpose'); // e.g., 'BUS FARE', 'FINE'
            $table->string('description')->nullable();
            $table->string('reference_id')->nullable(); // For Sheet syncing
            $table->decimal('balance_after', 10, 2); // Snapshot
            $table->timestamp('transaction_date');
            $table->timestamps();
            
            // Index for faster lookups
            $table->index(['student_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
