<?php

namespace App\Services;

use App\Http\Requests\StoreExerciseRequest;
use App\Models\Exercise;
use App\Models\WorkoutImport;
use App\Models\WorkoutPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WorkoutImportService
{
    public const MAX_SOURCE_LENGTH = 30000;

    public const MAX_DAYS = 7;

    public const MAX_EXERCISES_PER_DAY = 40;

    public function __construct(
        private readonly AiProviderService $providers,
        private readonly WorkoutPlanService $plans,
    ) {}

    public function parseWithAi($user, string $source): array
    {
        $resolved = $this->providers->resolve($user);
        if (! $resolved) {
            throw new RuntimeException('Configure an AI provider before importing a workout.');
        }

        $system = <<<'PROMPT'
You convert workout plans into strict JSON. Return JSON only, never markdown.
Use exactly this shape: {"title":"","week_number":null,"goal":"","description":"","start_date":null,"rules":[],"days":[{"weekday":"saturday","title":"","type":"training|rest|recovery","notes":"","exercises":[{"name":"","section":"warmup|main|accessory|cooldown","target_sets":null,"rep_min":null,"rep_max":null,"duration_seconds":null,"target_weight":null,"target_rir":null,"rest_seconds":null,"tempo":"","side_mode":"bilateral|per_side|alternating","is_amrap":false,"is_circuit":false,"circuit_rounds":null,"circuit_rest_seconds":null,"notes":"","alternatives":[]}] }]}
Always return all seven weekdays in this order: saturday, sunday, monday, tuesday, wednesday, thursday, friday. Convert circuit rounds, AMRAP, seconds/minutes and per-side notation into fields. Put safety warnings in notes or rules. Excluded movements may be added to rules, not exercises. Never invent exercise performance logs.
PROMPT;
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Parse this workout plan:\n\n{$source}"],
        ];
        $content = $this->requestModel($resolved, $messages);

        return $this->normalizeStructure($this->decodeJson($content));
    }

    public function normalizeStructure(array $input): array
    {
        $daysByWeekday = collect((array) ($input['days'] ?? []))->keyBy(fn ($day) => strtolower((string) ($day['weekday'] ?? '')));
        $days = [];
        foreach (WorkoutPlan::WEEKDAYS as $weekday) {
            $day = (array) $daysByWeekday->get($weekday, []);
            $candidateType = $day['type'] ?? 'rest';
            $type = in_array($candidateType, ['training', 'rest', 'recovery'], true) ? $candidateType : 'rest';
            $exercises = array_slice((array) ($day['exercises'] ?? []), 0, self::MAX_EXERCISES_PER_DAY);
            $days[] = [
                'weekday' => $weekday,
                'title' => Str::limit(trim((string) ($day['title'] ?? ucfirst($type))), 120, ''),
                'type' => $type,
                'notes' => Str::limit(trim((string) ($day['notes'] ?? '')), 2000, ''),
                'exercises' => array_values(array_map(fn ($exercise) => $this->normalizeExercise((array) $exercise), $exercises)),
            ];
        }

        return [
            'title' => Str::limit(trim((string) ($input['title'] ?? 'Imported Workout Plan')), 160, ''),
            'week_number' => isset($input['week_number']) && is_numeric($input['week_number']) ? max(1, min(999, (int) $input['week_number'])) : null,
            'goal' => Str::limit(trim((string) ($input['goal'] ?? '')), 255, ''),
            'description' => Str::limit(trim((string) ($input['description'] ?? '')), 5000, ''),
            'start_date' => $this->safeDate($input['start_date'] ?? null),
            'rules' => collect((array) ($input['rules'] ?? []))->map(fn ($rule) => Str::limit(trim((string) $rule), 500, ''))->filter()->take(30)->values()->all(),
            'days' => $days,
        ];
    }

    public function preview(WorkoutImport $import, int $userId): array
    {
        $exercises = Exercise::forUser($userId)->get();

        return collect($import->structure['days'] ?? [])->map(function (array $day) use ($exercises): array {
            $day['exercises'] = collect($day['exercises'] ?? [])->map(function (array $exercise) use ($exercises): array {
                $match = $this->matchExercise($exercise['name'], $exercises);
                $exercise['matched_exercise_id'] = $match?->id;
                $exercise['matched_exercise_name'] = $match?->name;
                $exercise['match_status'] = $match ? 'matched' : 'new';

                return $exercise;
            })->all();

            return $day;
        })->all();
    }

    public function confirm(WorkoutImport $import, int $userId): WorkoutPlan
    {
        $days = $this->preview($import, $userId);
        $exerciseCache = Exercise::forUser($userId)->get()->keyBy('id');

        return DB::transaction(function () use ($import, $userId, $days, $exerciseCache): WorkoutPlan {
            $payload = $import->structure;
            $payload['days'] = collect($days)->map(function (array $day) use ($userId, $exerciseCache): array {
                $day['exercises'] = collect($day['exercises'] ?? [])->map(function (array $exercise) use ($userId, $exerciseCache): array {
                    $matchedId = $exercise['matched_exercise_id'] ?? null;
                    if (! $matchedId || ! $exerciseCache->has((int) $matchedId)) {
                        $normalizedName = StoreExerciseRequest::normalize($exercise['name']);
                        $existing = $exerciseCache->first(fn (Exercise $candidate) => $candidate->normalized_name === $normalizedName);
                        if (! $existing) {
                            $existing = Exercise::create([
                                'user_id' => $userId,
                                'name' => $exercise['name'],
                                'normalized_name' => $normalizedName,
                                'aliases' => [],
                                'category' => null,
                            ]);
                            $exerciseCache->put($existing->id, $existing);
                        }
                        $matchedId = $existing->id;
                    }

                    return collect($exercise)->except(['name', 'matched_exercise_id', 'matched_exercise_name', 'match_status'])->merge(['exercise_id' => $matchedId])->all();
                })->all();

                return $day;
            })->all();
            $payload['status'] = 'draft';

            $plan = $this->plans->save($payload, $userId);
            $import->update(['status' => WorkoutImport::CONFIRMED, 'workout_plan_id' => $plan->id]);

            return $plan;
        });
    }

    private function normalizeExercise(array $exercise): array
    {
        $boolean = fn ($value) => filter_var($value ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'name' => Str::limit(trim((string) ($exercise['name'] ?? 'Unknown movement')), 120, ''),
            'section' => in_array(($exercise['section'] ?? 'main'), ['warmup', 'main', 'accessory', 'cooldown'], true) ? $exercise['section'] : 'main',
            'target_sets' => $this->intOrNull($exercise['target_sets'] ?? null, 1, 99),
            'rep_min' => $this->intOrNull($exercise['rep_min'] ?? null, 1, 999),
            'rep_max' => $this->intOrNull($exercise['rep_max'] ?? null, 1, 999),
            'duration_seconds' => $this->intOrNull($exercise['duration_seconds'] ?? null, 1, 86400),
            'target_weight' => $this->numberOrNull($exercise['target_weight'] ?? null),
            'target_rir' => $this->numberOrNull($exercise['target_rir'] ?? null),
            'rest_seconds' => $this->intOrNull($exercise['rest_seconds'] ?? null, 0, 3600),
            'tempo' => Str::limit(trim((string) ($exercise['tempo'] ?? '')), 40, ''),
            'side_mode' => in_array(($exercise['side_mode'] ?? ''), ['bilateral', 'per_side', 'alternating'], true) ? $exercise['side_mode'] : null,
            'is_amrap' => $boolean($exercise['is_amrap'] ?? false),
            'is_circuit' => $boolean($exercise['is_circuit'] ?? false),
            'circuit_rounds' => $this->intOrNull($exercise['circuit_rounds'] ?? null, 1, 30),
            'circuit_rest_seconds' => $this->intOrNull($exercise['circuit_rest_seconds'] ?? null, 0, 3600),
            'alternatives' => [],
            'notes' => Str::limit(trim((string) ($exercise['notes'] ?? '')), 2000, ''),
        ];
    }

    private function matchExercise(string $name, $exercises): ?Exercise
    {
        $needle = StoreExerciseRequest::normalize($name);

        return $exercises->first(function (Exercise $exercise) use ($needle): bool {
            return $exercise->normalized_name === $needle
                || collect($exercise->aliases ?? [])->contains(fn ($alias) => StoreExerciseRequest::normalize($alias) === $needle);
        });
    }

    private function requestModel(array $resolved, array $messages): string
    {
        $cfg = $resolved['config'];
        if ($resolved['type'] === 'gemini') {
            $url = rtrim($cfg['base_url'], '/').'/'.$resolved['model'].':generateContent?key='.$resolved['key'];
            $response = $this->providers->postJson($url, ['contents' => [['role' => 'user', 'parts' => [['text' => $messages[0]['content'].'\n\n'.$messages[1]['content']]]]], 'generationConfig' => ['responseMimeType' => 'application/json']], [], 90);
            if ($response->failed()) {
                throw new RuntimeException($this->providers->formatErrorResponse($response));
            }

            return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        }
        if ($resolved['type'] === 'anthropic') {
            $response = $this->providers->postJson($cfg['base_url'], ['model' => $resolved['model'], 'max_tokens' => 6000, 'system' => $messages[0]['content'], 'messages' => [['role' => 'user', 'content' => $messages[1]['content']]]], ['x-api-key' => $resolved['key'], 'anthropic-version' => '2023-06-01'], 90);
            if ($response->failed()) {
                throw new RuntimeException($this->providers->formatErrorResponse($response));
            }

            return (string) data_get($response->json(), 'content.0.text', '');
        }

        $payload = $this->providers->openAiPayload($messages, $resolved['model'], false, $cfg['base_url']);
        $payload['response_format'] = ['type' => 'json_object'];
        $response = $this->providers->postJson($this->providers->endpointFor($cfg['base_url'], $resolved['type']), $payload, ['Authorization' => 'Bearer '.$resolved['key']], 90);
        if ($response->failed()) {
            throw new RuntimeException($this->providers->formatErrorResponse($response));
        }

        return (string) data_get($response->json(), 'choices.0.message.content', '');
    }

    private function decodeJson(string $content): array
    {
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $content));
        $decoded = json_decode($clean, true);
        if (! is_array($decoded)) {
            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');
            $decoded = $start !== false && $end !== false ? json_decode(substr($clean, $start, $end - $start + 1), true) : null;
        }
        if (! is_array($decoded)) {
            throw new RuntimeException('The AI returned an invalid workout structure.');
        }

        return $decoded;
    }

    private function intOrNull($value, int $min, int $max): ?int
    {
        return is_numeric($value) ? max($min, min($max, (int) $value)) : null;
    }

    private function numberOrNull($value): ?float
    {
        return is_numeric($value) ? max(0, min(10000, (float) $value)) : null;
    }

    private function safeDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
