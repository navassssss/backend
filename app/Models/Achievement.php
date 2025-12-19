<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = [
        'student_id',
        'achievement_category_id',
        'title',
        'description',
        'points',
        'status',
        'approved_by',
        'approved_at',
        'review_note',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function category()
    {
        return $this->belongsTo(AchievementCategory::class, 'achievement_category_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments()
    {
        return $this->hasMany(AchievementAttachment::class);
    }
}
