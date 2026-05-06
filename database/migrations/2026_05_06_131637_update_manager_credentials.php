<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $manager = User::where('role', 'manager')->first();
        if ($manager) {
            $manager->email = 'hyderhudawi@gmail.com';
            $manager->password = Hash::make('admin@dhic');
            $manager->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed for data fix
    }
};
