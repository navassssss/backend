<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanMonthlyFeeSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();

        // Truncate monthly fee plans
        DB::table('monthly_fee_plans')->truncate();
        
        // Also clear allocations, as they depend on monthly plans
        // If we don't clear these, we have orphan allocations
        DB::table('fee_payment_allocations')->truncate();

        Schema::enableForeignKeyConstraints();

        $this->command->info('Monthly fee plans and allocations cleared successfully.');
    }
}
