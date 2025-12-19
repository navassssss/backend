<?php

namespace App\Console\Commands;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Console\Command;

class FindStudentInSheet extends Command
{
    protected $signature = 'debug:find-student {adno}';
    protected $description = 'Find a student row in the Students sheet';

    private $spreadsheetId = '1v4zWpetjmb8U38xeuCZjF34Qp572FOSk01KGZT47kJk';

    public function handle()
    {
        $adno = $this->argument('adno');
        $this->info("Searching for Student $adno...");

        $client = new Client();
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
        $client->setAuthConfig(storage_path('app/private/account-481203-3f2ae4c6c6f6.json'));
        $client->addScope(Sheets::SPREADSHEETS_READONLY);
        $service = new Sheets($client);

        // Get Sheet Name
        $spreadsheet = $service->spreadsheets->get($this->spreadsheetId);
        $studentsSheetName = null;
        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getSheetId() == 574769998) {
                $studentsSheetName = $sheet->getProperties()->getTitle();
                break;
            }
        }

        $range = "{$studentsSheetName}!A1:Z1000"; // Scan first 1000 rows
        $response = $service->spreadsheets_values->get($this->spreadsheetId, $range);
        $rows = $response->getValues();

        foreach ($rows as $i => $row) {
             // Index 1 is AD NO
            if (isset($row[1]) && $row[1] == $adno) {
                $this->info("Found at Row $i: " . json_encode($row));
                return;
            }
        }
        
        $this->error("Student not found.");
    }
}
