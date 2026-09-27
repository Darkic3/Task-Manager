<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'aliases' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:40'],
            'muscle_groups' => ['nullable', 'string', 'max:500'],
            'equipment' => ['nullable', 'string', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();

        foreach (['aliases', 'muscle_groups', 'equipment'] as $field) {
            $data[$field] = collect(preg_split('/[,\n]+/', (string) ($data[$field] ?? '')))
                ->map(fn (string $value) => trim($value))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $data['normalized_name'] = self::normalize($data['name']);

        return $data;
    }

    public static function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($value)));
    }
}
