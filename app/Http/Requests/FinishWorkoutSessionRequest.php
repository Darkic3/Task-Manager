<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinishWorkoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['completed', 'skipped'])],
            'session_note' => ['nullable', 'string', 'max:5000'],
            'pain_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
