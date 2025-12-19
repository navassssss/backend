<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CCEWork extends Model
{
    protected $table = 'cce_works';

    protected $fillable = [
        'subject_id',
        'level',
        'week',
        'title',
        'description',
        'tool_method',
        'issued_date',
        'due_date',
        'max_marks',
        'submission_type',
        'created_by'
    ];

    protected $casts = [
        'issued_date' => 'date',
        'due_date' => 'date',
        'level' => 'integer',
        'week' => 'integer',
        'max_marks' => 'integer'
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions()
    {
        return $this->hasMany(CCESubmission::class, 'work_id');
    }
}
