<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insert if not already present
        $existing = DB::table('permissions')->where('name', 'manage_announcements')->first();
        if (!$existing) {
            DB::table('permissions')->insert([
                'name'       => 'manage_announcements',
                'label'      => 'Manage Announcements',
                'module'     => 'Announcements',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'manage_announcements')->delete();
    }
};
