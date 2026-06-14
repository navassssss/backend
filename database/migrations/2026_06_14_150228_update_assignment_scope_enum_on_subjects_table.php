<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE subjects MODIFY COLUMN assignment_scope ENUM('full_class', 'selected_students', 'department') DEFAULT 'full_class' NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE subjects MODIFY COLUMN assignment_scope ENUM('full_class', 'selected_students') DEFAULT 'full_class' NOT NULL");
        }
    }
};
