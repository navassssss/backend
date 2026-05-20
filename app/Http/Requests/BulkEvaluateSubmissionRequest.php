<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CCESubmission;

class BulkEvaluateSubmissionRequest extends FormRequest
{
    public function authorize()
    {
        $submissionIds = $this->input('submission_ids', []);
        
        if (empty($submissionIds) || !is_array($submissionIds)) {
            return true; // Let validation rules handle it
        }

        $submissions = CCESubmission::with('work')->whereIn('id', $submissionIds)->get();

        if ($submissions->isEmpty()) {
            return true;
        }

        $workId = $submissions->first()->work_id;
        
        // Prevent IDOR: Check that all submissions belong to the same work
        if ($submissions->contains(fn($s) => $s->work_id !== $workId)) {
            return false;
        }

        // Authorize via the gate
        return $this->user()->can('evaluate', $submissions->first()->work);
    }

    public function rules()
    {
        return [
            'submission_ids'   => 'required|array|min:1',
            'submission_ids.*' => 'integer|exists:cce_submissions,id',
            'marks_obtained'   => 'required|numeric|min:0',
            'feedback'         => 'nullable|string|max:1000',
        ];
    }
}
