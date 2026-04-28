<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePaymentAllocation extends Model
{
    protected $fillable = [
        'fee_payment_id',
        'student_id',
        'month',
        'year',
        'allocated_amount',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'month' => 'integer',
        'year' => 'integer',
    ];

    /**
     * Get the payment that owns the allocation
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    /**
     * Get the student that owns the allocation
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
