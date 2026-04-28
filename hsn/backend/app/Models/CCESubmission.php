<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CCESubmission extends Model
{
    protected $table = 'cce_submissions';

    protected $fillable = [
        'work_id',
        'student_id',
        'submitted_at',
        'file_url',
        'marks_obtained',
        'feedback',
        'evaluated_by',
        'evaluated_at',
        'status'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'evaluated_at' => 'datetime',
        'marks_obtained' => 'decimal:2'
    ];

    public function work()
    {
        return $this->belongsTo(CCEWork::class, 'work_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
