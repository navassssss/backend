<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'student_id',
        'type',
        'amount',
        'purpose',
        'description',
        'reference_id',
        'balance_after',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
