<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportSheetTransactions extends Command
{
    protected $signature = 'google:import-transactions';
    protected $description = 'Import students wallet transactions from Google Sheets';

    private $spreadsheetId = '1v4zWpetjmb8U38xeuCZjF34Qp572FOSk01KGZT47kJk';
    // GID 177853088 corresponds to "Transactions" sheet (usually named 'Transactions' or similar, we need to find tab name or use GID if API supports it, Sheets API uses Sheet Name usually)
    // The user provided link: https://docs.google.com/spreadsheets/d/.../edit?gid=177853088
    // We will assume the sheet name is 'Transactions' or try to iterate.
    // However, user said "this is the transactions sheet ... it has values like this".
    // Let's assume the name is 'Transactions'. If not, we might need to list sheets.
    // Actually, getting by GID via API is tricky, usually range is 'SheetName!A:Z'.
    // Let's guess 'Transactions' or 'Sheet1'.
    
    // User also provided "Students" sheet with opening balances.
    
    // Let's try to fetch metadata first to find sheet names if needed, or just try 'Transactions'.
    
    public function handle()
    {
        $this->info('Starting Google Sheet Import...');

        $client = new Client();
        // Disable SSL verification for development environment (XAMPP issue)
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
        $client->setAuthConfig(storage_path('app/private/account-481203-3f2ae4c6c6f6.json'));
        $client->addScope(Sheets::SPREADSHEETS_READONLY);
        $service = new Sheets($client);

        // 1. Get Sheet Names by GID
        $spreadsheet = $service->spreadsheets->get($this->spreadsheetId);
        $sheets = $spreadsheet->getSheets();
        
        $transactionsSheetName = null;
        $studentsSheetName = null;
        
        foreach ($sheets as $sheet) {
            $props = $sheet->getProperties();
            $gid = $props->getSheetId();
            $title = $props->getTitle();
            
            if ($gid == 177853088) {
                $transactionsSheetName = $title;
            } elseif ($gid == 574769998) {
                $studentsSheetName = $title;
            }
        }
        
        if (!$transactionsSheetName) {
            $this->error("Transactions Sheet (GID 177853088) not found.");
            return;
        }

        // 1.5 Process Opening Balances (if Students sheet found)
        if ($studentsSheetName) {
            $this->info("Found Students Sheet: {$studentsSheetName}. Processing Opening Balances...");
            $this->processOpeningBalances($service, $studentsSheetName);
        }

        $this->info("Found Transactions Sheet: {$transactionsSheetName}");
        
        // 2. Process Transactions
        $range = "{$transactionsSheetName}!A2:H"; // Skip header
        $response = $service->spreadsheets_values->get($this->spreadsheetId, $range);
        $rows = $response->getValues();

        if (empty($rows)) {
            $this->warn("No data found in Transactions sheet.");
            return;
        }

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            // Mapping based on user input:
            // Col 0: Date (09-Apr-2025 09:49 AM)
            // Col 1: #ID (1003) -> Reference ID
            // Col 2: Name (AHS AHSAs or 529 SAYYED...) -> Extract AdNo
            // Col 3: Purpose (OTHERS)
            // Col 4: Amount (15000)
            // Col 5: Current Balance (35572)
            // Col 6: PRINT LABEL
            // Col 7: Remarks
            
            if (!isset($row[2]) || !isset($row[4])) {
                $bar->advance();
                continue;
            }

            $dateStr = $row[0] ?? now();
            $refId = $row[1] ?? uniqid();
            $nameStr = $row[2];
            $purpose = $row[3] ?? 'General';
            $amount = (float) str_replace(',', '', $row[4]); // Handle commas
            $balanceAfter = (float) str_replace(',', '', $row[5] ?? 0);
            
            try {
                $date = Carbon::parse($dateStr);
            } catch (\Exception $e) {
                $date = now();
            }

            // Extract ADNO from Name
            // Format: "529 SAYYED..." -> 529
            // "AHS AHSAs" -> AHS (maybe?)
            $parts = explode(' ', trim($nameStr));
            $adno = $parts[0];
            
            // Find Student
            $student = Student::where('username', $adno)->first();
            
            if (!$student) {
                // Try searching by roll number?
                 $student = Student::where('roll_number', $adno)->first();
            }

            $remarks = $row[7] ?? ''; // Index 7 is Remarks

            if ($student) {
                // Pass remarks to processTransaction
                $this->processTransaction($student, $amount, $purpose, $refId, $balanceAfter, $date, $remarks);
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Import completed.');
    }

    private function processOpeningBalances($service, $sheetName)
    {
        // Read Students sheet to get current balances
        // The "CURRENT BALANCE" column represents the FINAL balance including all transactions
        // We'll set this directly on the student record after all transactions are processed
        // Column mapping based on actual sheet:
        // A: (blank/index) | B: AD NO | C: NAME | D: FULL NAME | E: CLASS
        // F: LAST YEAR BALANCE | G: NEW ACCOUNT | H: LAST YEAR TOTAL
        // I: TOTAL DEPOSITS | J: TOTAL EXPENSES | K: CURRENT BALANCE
        $range = "{$sheetName}!A2:K"; // Skip header, read through column K
        $response = $service->spreadsheets_values->get($this->spreadsheetId, $range);
        $rows = $response->getValues();
        
        if (empty($rows)) {
            $this->warn("No data found in Students sheet.");
            return;
        }
        
        foreach ($rows as $row) {
            // Column B (index 1) = AD NO
            // Column K (index 10) = CURRENT BALANCE
            if (!isset($row[1]) || !isset($row[10])) {
                continue; // Skip if no AdNo or Current Balance
            }
            
            $adno = trim($row[1]); // Column B
            $currentBalance = (float) str_replace(',', '', $row[10]); // Column K
            
            // Find student
            $student = Student::where('username', $adno)->first();
            if (!$student) {
                $student = Student::where('roll_number', $adno)->first();
            }
            
            if (!$student) {
                continue;
            }
            
            // Set the wallet balance directly from the sheet
            // This is the authoritative source of truth
            $student->wallet_balance = $currentBalance;
            $student->save();
        }
    }

    private function processTransaction($student, $amount, $purpose, $refId, $balanceAfter, $date, $remarks = '')
    {
        // Determine type
        $type = ($purpose === 'DEPOSIT') ? 'deposit' : 'expense';
        
        // Use updateOrCreate to ensure we update remarks for existing transactions
        // Use description field for Remarks if available, otherwise Purpose
        $description = !empty($remarks) ? $remarks : $purpose;

        Transaction::updateOrCreate(
            ['reference_id' => $refId],
            [
                'student_id' => $student->id,
                'type' => $type,
                'amount' => $amount,
                'purpose' => $purpose,
                'description' => $description,
                'balance_after' => $balanceAfter,
                'transaction_date' => $date,
            ]
        );

        // Update Student Wallet Snapshot
    }
}
