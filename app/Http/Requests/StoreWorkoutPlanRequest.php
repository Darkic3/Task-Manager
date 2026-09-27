<?php

namespace App\Http\Requests;

use App\Models\WorkoutPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'goal' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'active'])],
            'rules' => ['nullable', 'array', 'max:30'],
            'rules.*' => ['nullable', 'string', 'max:500'],
            'days' => ['required', 'array', 'size:7'],
            'days.*.weekday' => ['required', Rule::in(WorkoutPlan::WEEKDAYS)],
            'days.*.title' => ['required', 'string', 'max:120'],
            'days.*.type' => ['required', Rule::in(['training', 'rest', 'recovery'])],
            'days.*.notes' => ['nullable', 'string', 'max:2000'],
            'days.*.exercises' => ['nullable', 'array', 'max:40'],
            'days.*.exercises.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'days.*.exercises.*.section' => ['required', Rule::in(['warmup', 'main', 'accessory', 'cooldown'])],
            'days.*.exercises.*.target_sets' => ['nullable', 'integer', 'min:1', 'max:99'],
            'days.*.exercises.*.rep_min' => ['nullable', 'integer', 'min:1', 'max:999'],
            'days.*.exercises.*.rep_max' => ['nullable', 'integer', 'min:1', 'max:999'],
            'days.*.exercises.*.duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'days.*.exercises.*.target_weight' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'days.*.exercises.*.target_rir' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'days.*.exercises.*.rest_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'days.*.exercises.*.tempo' => ['nullable', 'string', 'max:40'],
            'days.*.exercises.*.side_mode' => ['nullable', Rule::in(['bilateral', 'per_side', 'alternating'])],
            'days.*.exercises.*.is_amrap' => ['nullable', 'boolean'],
            'days.*.exercises.*.is_circuit' => ['nullable', 'boolean'],
            'days.*.exercises.*.circuit_rounds' => ['nullable', 'integer', 'min:1', 'max:30'],
            'days.*.exercises.*.circuit_rest_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'days.*.exercises.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $seenWeekdays = [];
            foreach ((array) $this->input('days', []) as $dayIndex => $day) {
                $weekday = $day['weekday'] ?? null;
                if ($weekday && in_array($weekday, $seenWeekdays, true)) {
                    $validator->errors()->add("days.{$dayIndex}.weekday", 'Each weekday can appear only once.');
                }
                if ($weekday) {
                    $seenWeekdays[] = $weekday;
                }
                foreach ((array) ($day['exercises'] ?? []) as $exerciseIndex => $exercise) {
                    $min = $exercise['rep_min'] ?? null;
                    $max = $exercise['rep_max'] ?? null;
                    if ($min !== null && $max !== null && (int) $max < (int) $min) {
                        $validator->errors()->add("days.{$dayIndex}.exercises.{$exerciseIndex}.rep_max", 'Maximum reps must be greater than or equal to minimum reps.');
                    }
                }
            }
        });
    }

    public function payload(): array
    {
        $data = $this->validated();
        $data['rules'] = collect($data['rules'] ?? [])->map(fn ($rule) => trim((string) $rule))->filter()->values()->all();

        foreach ($data['days'] as &$day) {
            $day['exercises'] = array_values(array_map(function (array $exercise): array {
                foreach (['is_amrap', 'is_circuit'] as $boolean) {
                    $exercise[$boolean] = filter_var($exercise[$boolean] ?? false, FILTER_VALIDATE_BOOLEAN);
                }

                return $exercise;
            }, $day['exercises'] ?? []));
        }

        return $data;
    }
}
