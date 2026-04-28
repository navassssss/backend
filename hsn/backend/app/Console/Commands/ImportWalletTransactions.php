<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Transaction;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWalletTransactions extends Command
{
    protected $signature = 'wallet:import {--incremental : Only import new transactions}';
    protected $description = 'Import wallet transactions from Google Sheets with proper balance tracking';

    private $spreadsheetId = '1v4zWpetjmb8U38xeuCZjF34Qp572FOSk01KGZT47kJk';
    private $warnings = [];

    public function handle()
    {
        $this->info('Starting Wallet Transaction Import...');
        
        $client = new Client();
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
        $client->setAuthConfig(storage_path('app/private/account-481203-3f2ae4c6c6f6.json'));
        $client->addScope(Sheets::SPREADSHEETS_READONLY);
        $service = new Sheets($client);

        // Get sheet names
        $spreadsheet = $service->spreadsheets->get($this->spreadsheetId);
        $sheets = $spreadsheet->getSheets();
        
        $transactionsSheetName = null;
        $studentsSheetName = null;
        
        foreach ($sheets as $sheet) {
            $props = $sheet->getProperties();
            $gid = $props->getSheetId();
            
            if ($gid == 177853088) {
                $transactionsSheetName = $props->getTitle();
            } elseif ($gid == 574769998) {
                $studentsSheetName = $props->getTitle();
            }
        }

        if (!$transactionsSheetName || !$studentsSheetName) {
            $this->error("Required sheets not found.");
            return 1;
        }

        // Step 1: Import opening balances from Students sheet
        $this->info("Step 1: Processing opening balances from Students sheet...");
        $this->processOpeningBalances($service, $studentsSheetName);

        // Step 2: Import transactions
        $this->info("Step 2: Processing transactions...");
        $this->processTransactions($service, $transactionsSheetName);

        // Step 3: Verify final balances
        $this->info("Step 3: Verifying final balances...");
        $this->verifyFinalBalances($service, $studentsSheetName);

        // Show warnings if any
        if (count($this->warnings) > 0) {
            $this->warn("\n⚠️  Warnings:");
            foreach ($this->warnings as $warning) {
                $this->warn("  - {$warning}");
            }
        }

        $this->info("\n✅ Import completed successfully!");
        return 0;
    }

    private function processOpeningBalances($service, $sheetName)
    {
        // Read Students sheet: Column B = AD NO, Column H = LAST YEAR TOTAL (opening balance)
        $range = "{$sheetName}!A2:K";
        $response = $service->spreadsheets_values->get($this->spreadsheetId, $range);
        $rows = $response->getValues();

        if (empty($rows)) {
            $this->warn("No data in Students sheet");
            return;
        }

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            if (!isset($row[1]) || !isset($row[7])) { // B and H columns
                $bar->advance();
                continue;
            }

            $username = trim($row[1]); // Column B: AD NO
            $openingBalance = (float) str_replace(',', '', $row[7]); // Column H: LAST YEAR TOTAL

            $student = Student::where('username', $username)->first();
            if (!$student) {
                $student = Student::where('roll_number', $username)->first();
            }

            if ($student) {
                $student->opening_balance = $openingBalance;
                $student->wallet_balance = $openingBalance; // Start with opening balance
                if (!$this->option('incremental')) {
                    $student->last_processed_row = null; // Reset for full import
                }
                $student->save();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function processTransactions($service, $sheetName)
    {
        // Read Transactions sheet
        $range = "{$sheetName}!A2:H";
        $response = $service->spreadsheets_values->get($this->spreadsheetId, $range);
        $rows = $response->getValues();

        if (empty($rows)) {
            $this->warn("No transactions found");
            return;
        }

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because sheet starts at row 1 and we skip header

            if (!isset($row[2]) || !isset($row[4])) { // Name and Amount required
                $bar->advance();
                continue;
            }

            // Extract data
            $dateStr = $row[0] ?? now();
            $refId = $row[1] ?? "TXN-{$rowNumber}";
            $nameStr = $row[2]; // "529 SAYYED MUHAMMED..." or "AHS AHSAs"
            $purpose = $row[3] ?? 'General';
            $amount = (float) str_replace(',', '', $row[4]);
            $remarks = $row[7] ?? '';

            // Extract username (first part before space)
            $parts = explode(' ', trim($nameStr));
            $username = $parts[0];

            // Find student
            $student = Student::where('username', $username)->first();
            if (!$student) {
                $student = Student::where('roll_number', $username)->first();
            }

            if (!$student) {
                $bar->advance();
                continue;
            }

            // Check if incremental and already processed
            if ($this->option('incremental') && $student->last_processed_row && $rowNumber <= $student->last_processed_row) {
                $bar->advance();
                continue;
            }

            // Parse date
            try {
                $date = Carbon::parse($dateStr);
            } catch (\Exception $e) {
                $date = now();
            }

            // Determine transaction type
            $type = (strtoupper($purpose) === 'DEPOSIT') ? 'deposit' : 'expense';

            // Calculate new balance (running balance)
            $currentBalance = $student->wallet_balance;
            if ($type === 'deposit') {
                $newBalance = $currentBalance + $amount;
            } else {
                $newBalance = $currentBalance - $amount;
            }

            // Create/update transaction with calculated balance
            Transaction::updateOrCreate(
                ['reference_id' => $refId],
                [
                    'student_id' => $student->id,
                    'type' => $type,
                    'amount' => $amount,
                    'purpose' => $purpose,
                    'description' => $remarks ?: $purpose,
                    'balance_after' => $newBalance, // Use calculated running balance
                    'transaction_date' => $date,
                    'sheet_row_number' => $rowNumber,
                ]
            );

            // Update student balance and last processed row
            $student->wallet_balance = $newBalance;
            $student->last_processed_row = $rowNumber;
            $student->save();

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function verifyFinalBalances($service, $sheetName)
    {
        // Read final balances from Students sheet (Column K)
        $range = "{$sheetName}!A2:K";
        $response = $service->spreadsheets_values->get($this->spreadsheetId, $range);
        $rows = $response->getValues();

        $mismatches = 0;

        foreach ($rows as $row) {
            if (!isset($row[1]) || !isset($row[10])) { // B and K columns
                continue;
            }

            $username = trim($row[1]); // Column B: AD NO
            $expectedBalance = (float) str_replace(',', '', $row[10]); // Column K: CURRENT BALANCE

            $student = Student::where('username', $username)->first();
            if (!$student) {
                $student = Student::where('roll_number', $username)->first();
            }

            if ($student) {
                if (abs($student->wallet_balance - $expectedBalance) > 0.01) {
                    $this->warnings[] = "Final balance mismatch for {$username}: DB={$student->wallet_balance}, Sheet={$expectedBalance}";
                    $mismatches++;
                    
                    // Update to match sheet (sheet is source of truth)
                    $student->wallet_balance = $expectedBalance;
                    $student->save();
                }
            }
        }

        if ($mismatches > 0) {
            $this->warn("Found {$mismatches} balance mismatches. Updated to match Students sheet.");
        } else {
            $this->info("All balances verified successfully!");
        }
    }
}
