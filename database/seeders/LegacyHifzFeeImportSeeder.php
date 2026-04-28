<?php

namespace Database\Seeders;

/**
 * Imports legacy fee balances for Hifz students from monthly_hifz.csv.
 *
 * CSV format (same as monthly.csv):
 *   AD.NO & NAME,CLASS,MONTHLY,REMAINING
 *   H228 MUHAMMED HASAN PS,HIFZ,1500,18000
 *
 * All logic (backwards distribution, is_hifz detection, summary output)
 * is inherited from LegacyFeeImportSeeder.
 *
 * Run: php artisan db:seed --class=LegacyHifzFeeImportSeeder
 */
class LegacyHifzFeeImportSeeder extends LegacyFeeImportSeeder
{
    protected string $csvFile = 'monthly_hifz.csv';
}
