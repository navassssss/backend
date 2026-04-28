<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $guarded = [];

    public function duty()
    {
        return $this->belongsTo(Duty::class);
    }

    // Task.php
    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
