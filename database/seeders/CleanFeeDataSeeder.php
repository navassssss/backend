<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanFeeDataSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();

        DB::table('fee_payment_allocations')->truncate();
        DB::table('fee_payments')->truncate();
        DB::table('monthly_fee_plans')->truncate();

        Schema::enableForeignKeyConstraints();

        $this->command->info('Fee data cleared successfully.');
    }
}
