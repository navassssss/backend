<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Console\Command;

class CheckNewTransactions extends Command
{
    protected $signature = 'google:check-new-transactions {--import : Automatically import new transactions}';
    protected $description = 'Check if there are new transactions in Google Sheets';

    private $spreadsheetId = '1v4zWpetjmb8U38xeuCZjF34Qp572FOSk01KGZT47kJk';

    public function handle()
    {
        $this->info('Checking for new transactions in Google Sheet...');

        $client = new Client();
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
        $client->setAuthConfig(storage_path('app/private/account-481203-3f2ae4c6c6f6.json'));
        $client->addScope(Sheets::SPREADSHEETS_READONLY);
        $service = new Sheets($client);

        // Get sheet name
        $spreadsheet = $service->spreadsheets->get($this->spreadsheetId);
        $sheets = $spreadsheet->getSheets();
        
        $transactionsSheetName = null;
        foreach ($sheets as $sheet) {
            $props = $sheet->getProperties();
            $gid = $props->getSheetId();
            if ($gid == 177853088) {
                $transactionsSheetName = $props->getTitle();
                break;
            }
        }

        if (!$transactionsSheetName) {
            $this->error("Transactions Sheet not found.");
            return 1;
        }

        // Get all transactions from sheet
        $range = "{$transactionsSheetName}!A2:H";
        $response = $service->spreadsheets_values->get($this->spreadsheetId, $range);
        $rows = $response->getValues();

        if (empty($rows)) {
            $this->warn("No data found in Transactions sheet.");
            return 0;
        }

        $totalInSheet = count($rows);
        $this->info("Total transactions in Google Sheet: {$totalInSheet}");

        // Get total transactions in database
        $totalInDb = Transaction::count();
        $this->info("Total transactions in database: {$totalInDb}");

        // Check for new transactions by comparing reference IDs
        $sheetRefIds = [];
        foreach ($rows as $row) {
            if (isset($row[1])) { // Column B = #ID
                $sheetRefIds[] = $row[1];
            }
        }

        $dbRefIds = Transaction::pluck('reference_id')->toArray();
        $newRefIds = array_diff($sheetRefIds, $dbRefIds);
        $newCount = count($newRefIds);

        if ($newCount > 0) {
            $this->warn("⚠️  Found {$newCount} new transaction(s) in Google Sheet!");
            
            if ($this->option('import')) {
                $this->info("Importing new transactions...");
                $this->call('google:import-transactions');
                $this->info("✅ Import completed!");
            } else {
                $this->info("💡 Run with --import flag to automatically import new transactions:");
                $this->info("   php artisan google:check-new-transactions --import");
            }
        } else {
            $this->info("✅ No new transactions found. Database is up to date!");
        }

        return 0;
    }
}
