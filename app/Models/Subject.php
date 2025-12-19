<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'name',
        'code',
        'class_id',
        'teacher_id',
        'final_max_marks',
        'is_locked'
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'final_max_marks' => 'integer'
    ];

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function works()
    {
        return $this->hasMany(CCEWork::class);
    }
}
