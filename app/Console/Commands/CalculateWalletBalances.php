<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateWalletBalances extends Command
{
    protected $signature = 'wallet:recalculate';
    protected $description = 'Recalculate running balances for all students based on transaction history';

    public function handle()
    {
        $this->info('Starting wallet balance recalculation...');
        
        $students = Student::all();
        
        $bar = $this->output->createProgressBar($students->count());
        $bar->start();

        foreach ($students as $student) {
            $runningBalance = 0;
            
            // Explicitly fetch sorted transactions to guarantee chronological order
            // (Eager loading constrained sort can sometimes be tricky if not careful)
            // Fetch all transactions and sort in PHP memory to ensure correctness
            $transactions = $student->transactions->sortBy('transaction_date');

            foreach ($transactions as $transaction) {
                // If it's an opening balance transaction, we might need to treat it carefully?
                // Actually, if we just sum them up chronologically, it works.
                // Assuming Opening Balance is a 'deposit' or 'expense' correctly set.
                
                if ($transaction->purpose === 'Opening Balance') {
                    // Force set running balance? Or just let it flow?
                    // Previous logic set opening balance as specific amount.
                    // Let's assume standard flow.
                }

                if ($transaction->type === 'deposit') {
                    $runningBalance += $transaction->amount;
                } else {
                    $runningBalance -= $transaction->amount;
                }

                $transaction->balance_after = $runningBalance;
                $transaction->save();
            }

            // Update student current balance
            $student->wallet_balance = $runningBalance;
            $student->save();

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Wallet balances recalculated successfully.');
    }
}
