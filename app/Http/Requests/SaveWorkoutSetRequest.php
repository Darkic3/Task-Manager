<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWorkoutSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'workout_exercise_id' => ['required', 'integer', 'exists:workout_exercises,id'],
            'set_number' => ['required', 'integer', 'min:1', 'max:99'],
            'reps' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'rir' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'form_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'pain_level' => ['nullable', 'integer', 'min:0', 'max:10'],
            'completed' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
            'exercise_note' => ['nullable', 'string', 'max:2000'],
            'skip_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
