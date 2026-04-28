<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClassRoom extends Model
{
    protected $fillable = [
        'name',
        'level',
        'section',
        'department',
        'total_points',
        'class_teacher_id',
        'is_hifz',
    ];

    protected $casts = [
        'is_hifz' => 'boolean',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    /** Exclude Hifz classes — use in all academic controllers. */
    public function scopeAcademic(Builder $query): Builder
    {
        return $query->where('is_hifz', false);
    }

    /** Only Hifz classes. */
    public function scopeHifz(Builder $query): Builder
    {
        return $query->where('is_hifz', true);
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function classTeacher()
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }
}
