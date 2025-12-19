<?php

namespace App\Console\Commands;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Console\Command;

class FindStudentTransactions extends Command
{
    protected $signature = 'debug:find-tx {adno}';
    protected $description = 'Find all transaction rows for a student';

    private $spreadsheetId = '1v4zWpetjmb8U38xeuCZjF34Qp572FOSk01KGZT47kJk';

    public function handle()
    {
        $adno = $this->argument('adno');
        $this->info("Searching for Transactions for $adno...");

        $client = new Client();
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
        $client->setAuthConfig(storage_path('app/private/account-481203-3f2ae4c6c6f6.json'));
        $client->addScope(Sheets::SPREADSHEETS_READONLY);
        $service = new Sheets($client);

        // Get Transactions Sheet
        $spreadsheet = $service->spreadsheets->get($this->spreadsheetId);
        $sheetTitle = null;
        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getSheetId() == 177853088) {
                $sheetTitle = $sheet->getProperties()->getTitle();
                break;
            }
        }
        
        $range = "{$sheetTitle}!A2:H"; 
        $response = $service->spreadsheets_values->get($this->spreadsheetId, $range);
        $rows = $response->getValues();

        $count = 0;
        $totalAmount = 0;

        foreach ($rows as $i => $row) {
             // Index 2 is NAME. "649 HUSAIN V" or similar.
            if (isset($row[2]) && str_contains($row[2], $adno)) {
                $amount = (float) str_replace(',', '', $row[4] ?? 0);
                $purpose = $row[3] ?? '';
                
                // Exclude DEPOSIT for expense check
                if ($purpose !== 'DEPOSIT') {
                     $totalAmount += $amount;
                     $this->info("Expense found: $amount ($purpose) at Row " . ($i+2));
                } else {
                     $this->info("Deposit found: $amount at Row " . ($i+2));
                }
                $count++;
            }
        }
        
        $this->info("Total Transactions Found: $count");
        $this->info("Total Expense Sum: $totalAmount");
    }
}
