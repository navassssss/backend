<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. 'manage_users'
            $table->string('label'); // e.g. 'Manage Users'
            $table->string('module')->nullable(); // e.g. 'Users'
            $table->timestamps();
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['permission_id', 'user_id']);
        });

        // Insert some default permissions
        $permissions = [
            ['name' => 'manage_teachers', 'label' => 'Manage Teachers', 'module' => 'Users'],
            ['name' => 'manage_students', 'label' => 'Manage Students', 'module' => 'Users'],
            ['name' => 'manage_fees', 'label' => 'Manage Fees', 'module' => 'Finance'],
            ['name' => 'manage_cce', 'label' => 'Manage CCE Works', 'module' => 'Academics'],
            ['name' => 'manage_duties', 'label' => 'Manage Duties', 'module' => 'Administration'],
            ['name' => 'manage_tasks', 'label' => 'Manage Tasks', 'module' => 'Administration'],
            ['name' => 'review_achievements', 'label' => 'Review Achievements', 'module' => 'Academics'],
        ];

        DB::table('permissions')->insert(array_map(function ($p) {
            return array_merge($p, ['created_at' => now(), 'updated_at' => now()]);
        }, $permissions));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permissions');
    }
};
