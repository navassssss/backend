<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommitteeHandover extends Model
{
    use HasFactory;

    protected $fillable = [
        'handover_date',
        'amount',
        'recipient_name',
        'payment_mode',
        'reference_number',
        'handed_over_by',
        'remarks',
    ];

    protected $casts = [
        'handover_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * User who recorded the handover.
     */
    public function handedOverBy()
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }
}
