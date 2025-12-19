<?php

namespace App\Console\Commands;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Console\Command;

class DebugSheetHeaders extends Command
{
    protected $signature = 'debug:sheet-headers';
    protected $description = 'Print headers of the Students sheet';

    private $spreadsheetId = '1v4zWpetjmb8U38xeuCZjF34Qp572FOSk01KGZT47kJk';

    public function handle()
    {
        $client = new Client();
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false])); // Fix SSL
        $client->setAuthConfig(storage_path('app/private/account-481203-3f2ae4c6c6f6.json'));
        $client->addScope(Sheets::SPREADSHEETS_READONLY);
        $service = new Sheets($client);

        $spreadsheet = $service->spreadsheets->get($this->spreadsheetId);
        // Find Sheet by GID
        $sheetTitle = null;
        foreach ($spreadsheet->getSheets() as $sheet) {
            // Check for Transactions GID 177853088
            if ($sheet->getProperties()->getSheetId() == 177853088) {
                $sheetTitle = $sheet->getProperties()->getTitle();
                break;
            }
        }
        
        if (!$sheetTitle) {
            $this->error("Sheet with GID 177853088 not found.");
            return;
        }

        $this->info("Found Transactions Sheet: $sheetTitle");
        $range = "{$sheetTitle}!A1:Z3"; 
        $response = $service->spreadsheets_values->get($this->spreadsheetId, $range);
        $rows = $response->getValues();

        foreach ($rows as $i => $row) {
            $this->info("Row $i: " . json_encode($row));
        }
    }
}
