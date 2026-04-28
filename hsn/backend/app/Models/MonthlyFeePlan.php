<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyFeePlan extends Model
{
    protected $fillable = [
        'student_id',
        'month',
        'year',
        'payable_amount',
        'set_by',
        'reason',
    ];

    protected $casts = [
        'payable_amount' => 'decimal:2',
        'month' => 'integer',
        'year' => 'integer',
    ];

    /**
     * Get the student that owns the fee plan
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who set this fee plan
     */
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
