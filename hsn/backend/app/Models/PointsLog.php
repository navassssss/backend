<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointsLog extends Model
{
    protected $fillable = [
        'student_id',
        'class_id',
        'achievement_id',
        'points',
        'source',
        'month',
        'year',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function achievement()
    {
        return $this->belongsTo(Achievement::class);
    }
}
