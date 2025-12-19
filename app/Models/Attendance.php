<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'date',
        'session',
        'marked_by'
    ];

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function marker()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function records()
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
