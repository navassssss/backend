<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Duty extends Model
{
    protected $guarded = [];

    protected $casts = [
        'custom_days' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'duty_teacher', 'duty_id', 'teacher_id')
            ->withPivot(['assigned_by', 'start_date', 'end_date', 'order_index'])
            ->withTimestamps();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
