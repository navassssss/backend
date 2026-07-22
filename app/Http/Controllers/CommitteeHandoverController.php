<?php

namespace App\Http\Controllers;

use App\Models\CommitteeHandover;
use App\Models\FeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommitteeHandoverController extends Controller
{
    /**
     * Display a listing of committee handovers.
     */
    public function index(Request $request)
    {
        $query = CommitteeHandover::with('handedOverBy');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            if ($startDate > $endDate) {
                $temp = $startDate;
                $startDate = $endDate;
                $endDate = $temp;
            }
            $query->whereBetween('handover_date', [$startDate, $endDate]);
        } elseif ($request->filled('start_date')) {
            $query->where('handover_date', '>=', $request->query('start_date'));
        } elseif ($request->filled('end_date')) {
            $query->where('handover_date', '<=', $request->query('end_date'));
        }

        if ($request->filled('payment_mode')) {
            $query->where('payment_mode', $request->query('payment_mode'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('recipient_name', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        $handovers = $query->orderBy('handover_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $handovers,
        ]);
    }

    /**
     * Get financial summary of cash collected vs handed over to committee.
     */
    public function summary(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Total fee collections query
        $feeQuery = FeePayment::query()
            ->where(function ($q) {
                $q->whereNull('remarks')
                  ->orWhere('remarks', 'not like', '%Pre-paid bulk import%');
            });

        // Total handovers query
        $handoverQuery = CommitteeHandover::query();

        if ($startDate && $endDate) {
            if ($startDate > $endDate) {
                $temp = $startDate;
                $startDate = $endDate;
                $endDate = $temp;
            }
            $feeQuery->whereBetween('payment_date', [$startDate, $endDate]);
            $handoverQuery->whereBetween('handover_date', [$startDate, $endDate]);
        }

        $totalCollected = (float) $feeQuery->sum('paid_amount');
        $totalHandedOver = (float) $handoverQuery->sum('amount');
        $balanceInHand = $totalCollected - $totalHandedOver;
        $totalHandoversCount = $handoverQuery->count();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_collected' => $totalCollected,
                'total_handed_over' => $totalHandedOver,
                'balance_in_hand' => $balanceInHand,
                'total_handovers_count' => $totalHandoversCount,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Store a new committee handover entry.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'handover_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'recipient_name' => 'required|string|max:255',
            'payment_mode' => 'required|in:cash,bank_transfer,cheque,upi',
            'reference_number' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $validated['handed_over_by'] = Auth::id();

        $handover = CommitteeHandover::create($validated);
        $handover->load('handedOverBy');

        return response()->json([
            'success' => true,
            'message' => 'Committee handover entry created successfully.',
            'data' => $handover,
        ], 201);
    }

    /**
     * Remove a handover entry.
     */
    public function destroy($id)
    {
        $handover = CommitteeHandover::findOrFail($id);
        $handover->delete();

        return response()->json([
            'success' => true,
            'message' => 'Handover entry deleted successfully.',
        ]);
    }
}
