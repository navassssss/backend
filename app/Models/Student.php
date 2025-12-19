<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'class_id',
        'username',
        'roll_number',
        'photo',
        'joined_at',
        'total_points',
        'wallet_balance',
        'opening_balance',
        'last_processed_row',
    ];

    protected $casts = [
        'joined_at' => 'date',
        'wallet_balance' => 'decimal:2',
        'opening_balance' => 'decimal:2',
    ];

    protected $appends = ['stars', 'monthly_points'];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function achievements()
    {
        return $this->hasMany(Achievement::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->orderBy('transaction_date', 'desc');
    }

    public function pointsLogs()
    {
        return $this->hasMany(PointsLog::class);
    }

    /**
     * Computed Attributes
     */
    protected function stars(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) floor($this->total_points / 20)
        );
    }

    protected function monthlyPoints(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->pointsLogs()
                ->where('month', now()->month)
                ->where('year', now()->year)
                ->sum('points')
        );
    }

    /**
     * Helper Methods
     */
    public function addPoints(int $points)
    {
        $this->increment('total_points', $points);
    }
}
