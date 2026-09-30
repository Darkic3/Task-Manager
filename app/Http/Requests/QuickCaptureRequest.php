<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:8000'],
            'kind' => ['nullable', 'string', Rule::in(\App\Models\Note::KINDS)],
            'notebook_id' => ['nullable', 'integer'],
            'occurred_at' => ['nullable', 'date'],
            'mentions' => ['nullable', 'array', 'max:30'],
            'mentions.*.type' => ['required', 'string', Rule::in(['subject', 'label', 'project', 'task', 'note'])],
            'mentions.*.id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => __('Write something first.'),
        ];
    }
}