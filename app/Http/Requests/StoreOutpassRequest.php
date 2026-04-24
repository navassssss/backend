<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutpassRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isPrincipal() || $this->user()->hasPermission('manage_outpasses');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id'         => 'required|integer|exists:students,id',
            'reason'             => 'required|string|max:255',
            'notes'              => 'nullable|string|max:1000',
            'out_time'           => 'required|date',
            'expected_in_time'   => 'required|date|after:out_time',
        ];
    }
}
