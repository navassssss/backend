<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentTransactionController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        $transactions = $student->transactions()
            ->select('id', 'type', 'amount', 'purpose', 'description', 'balance_after', 'transaction_date')
            ->orderBy('transaction_date', 'desc')
            ->paginate(20);

        // Calculate totals for the entire history
        $stats = [
            'total_credits' => $student->transactions()->where('type', 'deposit')->sum('amount'),
            'total_debits' => $student->transactions()->where('type', 'expense')->sum('amount'),
        ];

        // Attach stats to the pagination response (using custom format or meta)
        // Since we return json($transactions), it's a LengthAwarePaginator.
        // We can merge it.
        $response = $transactions->toArray();
        $response['stats'] = $stats;

        return response()->json($response);
    }
}
