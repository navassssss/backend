<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CCEWork;

class StoreCCEWorkRequest extends FormRequest
{
    public function authorize()
    {
        if ($this->user()->cannot('create', CCEWork::class)) {
            return false;
        }

        if ($this->user()->isTeacher() && $this->filled('subject_id')) {
            $subject = \App\Models\Subject::find($this->subject_id);
            if ($subject && $subject->teacher_id !== $this->user()->id) {
                return false;
            }
        }

        return true;
    }

    public function rules()
    {
        return [
            'subject_id'      => 'required|exists:subjects,id',
            'level'           => 'required|integer|min:1|max:4',
            'week'            => 'nullable|integer|min:1|max:52',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'tool_method'     => 'nullable|string|max:255',
            'issued_date'     => 'required|date',
            'due_date'        => 'required|date|after_or_equal:issued_date',
            'max_marks'       => 'required|integer|min:1|max:100',
            'submission_type' => 'required|in:online,offline',
        ];
    }

    public function messages()
    {
        return [
            'subject_id.required'  => 'Please select a subject.',
            'level.required'       => 'Please select a level.',
            'title.required'       => 'Please enter a work title.',
            'max_marks.required'   => 'Please enter the maximum marks.',
            'due_date.after_or_equal' => 'Due date must be on or after the issue date.',
        ];
    }
}
