<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ReceiptBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    /**
     * Get the user who generated the receipt batch.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get the payments in this batch.
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(
            FeePayment::class,
            'receipt_batch_payment',
            'receipt_batch_id',
            'fee_payment_id'
        )->withTimestamps();
    }
}
