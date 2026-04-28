<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    protected $fillable = [
        'student_id',
        'reported_by',
        'illness_name',
        'reported_at',
        'went_to_doctor',
        'notes',
        'recovered_at',
        'recovered_by',
        'sent_home_at',
        'sent_home_by',
    ];

    protected $casts = [
        'reported_at'    => 'datetime',
        'recovered_at'   => 'datetime',
        'sent_home_at'   => 'datetime',
        'went_to_doctor' => 'boolean',
    ];

    /* ── Relationships ── */

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function recoveredBy()
    {
        return $this->belongsTo(User::class, 'recovered_by');
    }

    public function sentHomeBy()
    {
        return $this->belongsTo(User::class, 'sent_home_by');
    }

    /* ── Status ── */

    public function getStatusAttribute(): string
    {
        if ($this->recovered_at) return 'recovered';
        if ($this->sent_home_at) return 'sent_home';
        return 'active';
    }

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->whereNull('recovered_at')->whereNull('sent_home_at');
    }

    public function scopeRecovered($query)
    {
        return $query->whereNotNull('recovered_at');
    }

    public function scopeSentHome($query)
    {
        return $query->whereNotNull('sent_home_at');
    }

    public function scopeResolved($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('recovered_at')->orWhereNotNull('sent_home_at');
        });
    }
}
