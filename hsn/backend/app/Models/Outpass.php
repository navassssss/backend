<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outpass extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'reason',
        'notes',
        'out_time',
        'expected_in_time',
        'actual_in_time',
        'created_by',
    ];

    protected $casts = [
        'out_time' => 'datetime',
        'expected_in_time' => 'datetime',
        'actual_in_time' => 'datetime',
    ];

    protected $appends = ['status'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Computed Status
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->actual_in_time !== null) {
                    return 'returned';
                }

                if ($this->expected_in_time !== null && now()->isAfter($this->expected_in_time)) {
                    return 'overdue';
                }

                return 'outside';
            }
        )->shouldCache();
    }

    /**
     * Scopes
     */
    public function scopeOutside(Builder $query): Builder
    {
        return $query->whereNull('actual_in_time');
    }

    public function scopeReturned(Builder $query): Builder
    {
        return $query->whereNotNull('actual_in_time');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNull('actual_in_time')
                     ->where('expected_in_time', '<', now());
    }
}
