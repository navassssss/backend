<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'class_id',
        'teacher_id',
        'final_max_marks',
        'is_locked',
        'assignment_scope',
    ];

    protected $casts = [
        'is_locked'        => 'boolean',
        'final_max_marks'  => 'integer',
        'assignment_scope' => 'string',
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

    /**
     * Students explicitly assigned (used when assignment_scope = selected_students).
     */
    public function assignedStudents()
    {
        return $this->belongsToMany(Student::class, 'subject_students', 'subject_id', 'student_id');
    }

    /**
     * Return the effective student IDs for this subject.
     * full_class → all students in the class.
     * selected_students → only pivot rows.
     */
    public function effectiveStudentIds(): \Illuminate\Support\Collection
    {
        if ($this->assignment_scope === 'selected_students') {
            return $this->assignedStudents()->pluck('students.id');
        }
        return Student::where('class_id', $this->class_id)->pluck('id');
    }
}
