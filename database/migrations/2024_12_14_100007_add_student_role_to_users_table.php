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
        // SQLite doesn't support ALTER COLUMN, so we need to recreate for tests
        // For production MySQL/PostgreSQL, use: $table->enum('role', [...])-> change();
        
        // This is mainly for SQLite (tests)
        Schema::table('users', function (Blueprint $table) {
            // Drop the old column
            $table->dropColumn('role');
        });
        
        Schema::table('users', function (Blueprint $table) {
            // Add it back with student included
            $table->enum('role', ['teacher', 'manager', 'principal', 'admin', 'user', 'student'])->default('teacher')->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['teacher', 'manager', 'principal', 'admin', 'user'])->default('teacher')->after('phone');
        });
    }
};
