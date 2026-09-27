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

    public function parseWithAi($user, string $source, ?string $anchorStartDate = null): array
    {
        $resolved = $this->providers->resolve($user);
        if (! $resolved) {
            throw new RuntimeException('Configure an AI provider before importing a workout.');
        }
        $anchor = $this->safeDate($anchorStartDate);
        if ($anchor) {
            $weekday = strtolower(Carbon::parse($anchor)->format('l'));
            $source .= "\n\nANCHOR: DAY 1 of this plan maps to {$anchor} ({$weekday}). Return days[] in execution order with index 0 = DAY 1. Keep every movement and detail.";
        }

        $system = <<<'PROMPT'
You convert workout plans into strict JSON. Return JSON only, never markdown.
Use exactly this shape: {"title":"","week_number":null,"goal":"","description":"","start_date":null,"rules":[],"days":[{"weekday":"saturday","title":"","type":"training|rest|recovery","notes":"","exercises":[{"name":"","section":"warmup|main|accessory|cooldown","target_sets":null,"rep_min":null,"rep_max":null,"duration_seconds":null,"target_weight":null,"target_rir":null,"rest_seconds":null,"tempo":"","side_mode":"bilateral|per_side|alternating","is_amrap":false,"is_circuit":false,"circuit_rounds":null,"circuit_rest_seconds":null,"notes":"","alternatives":[]}] }]}
Always return all seven weekdays in this order: saturday, sunday, monday, tuesday, wednesday, thursday, friday, unless a start_date anchor is given — then days[] must arrive in execution order with index 0 = DAY 1 mapped to the anchor date (even if it is a Sunday). Preserve every movement as its own exercise; never merge a day into one item. "4×6–8" means target_sets 4, rep_min 6, rep_max 8. "30–45s", "2min", "3min", "40–60s" mean duration_seconds. "7kg", "2kg", "9kg" mean target_weight. Main RIR goes to target_rir. Persian tempo cues like "3s پایین رفتن" go to tempo/notes. "/ پا" and "/ سمت" mean side_mode per_side. "1×MAX" means is_amrap true. "Circuit ×3" with rest between rounds means is_circuit true plus circuit_rounds and circuit_rest_seconds. "❌ movement" means an excluded rule, not an exercise. "جایگزین: X" goes to notes. Chest-pain STOP warnings go to notes and rules. Keep Persian and English exercise names exactly as written. Never invent exercise performance logs.
PROMPT;
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Parse this workout plan:\n\n{$source}"],
        ];
        $content = $this->requestModel($resolved, $messages);

        return $this->normalizeStructure($this->decodeJson($content), $anchor);
    }

    public function normalizeStructure(array $input, ?string $anchorStartDate = null): array
    {
        $anchor = $this->safeDate($anchorStartDate ?? ($input['start_date'] ?? null));
        $orderedDays = array_values((array) ($input['days'] ?? []));

        // Sequential mode: days arrive as DAY 1..DAY 7 in execution order
        // (chat tool). Map index 0 to the anchor date's weekday so
        // "starting today (Sunday)" keeps DAY 1 on Sunday.
        $hasSequentialCue = $anchor && $this->isSequentialPayload($orderedDays);
        if ($hasSequentialCue) {
            $days = $this->mapSequentialDays($orderedDays, $anchor);
        } else {
            $daysByWeekday = collect($orderedDays)->keyBy(function ($day): string {
                $day = (array) $day;

                return strtolower((string) ($day['weekday'] ?? ''));
            });
            $days = [];
            foreach (WorkoutPlan::WEEKDAYS as $weekday) {
                $day = (array) $daysByWeekday->get($weekday, []);
                $days[] = $this->normalizeDay($day, $weekday);
            }
        }

        $weekNumber = $this->latinDigits($input['week_number'] ?? null);

        return [
            'title' => Str::limit(trim((string) ($input['title'] ?? 'Imported Workout Plan')), 160, ''),
            'week_number' => isset($input['week_number']) && is_numeric($weekNumber) ? max(1, min(999, (int) $weekNumber)) : null,
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

        $days = collect($import->structure['days'] ?? [])->map(function (array $day) use ($exercises): array {
            $day['exercises'] = collect($day['exercises'] ?? [])->map(function (array $exercise) use ($exercises): array {
                $match = $this->matchExercise($exercise['name'], $exercises);
                $exercise['matched_exercise_id'] = $match?->id;
                $exercise['matched_exercise_name'] = $match?->name;
                $exercise['match_status'] = $match ? 'matched' : 'new';

                return $exercise;
            })->all();

            return $day;
        });

        // Show the week in execution order (DAY 1 first) when the import has
        // a start date, so a week starting Sunday does not open on Saturday.
        $startDate = $this->safeDate($import->structure['start_date'] ?? null);
        if ($startDate) {
            try {
                $order = array_flip(WorkoutPlan::WEEKDAYS);
                $startIdx = $order[strtolower(Carbon::parse($startDate)->format('l'))] ?? 0;
                $days = $days->sortBy(fn ($day) => (($order[$day['weekday']] ?? 0) - $startIdx + 7) % 7)->values();
            } catch (\Throwable) {
                // Keep stored order when the date cannot be parsed.
            }
        }

        return $days->all();
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
        $section = $exercise['section'] ?? 'main';
        $sideMode = $exercise['side_mode'] ?? null;

        return [
            'name' => Str::limit(trim((string) ($exercise['name'] ?? 'Unknown movement')), 120, ''),
            'section' => in_array($section, ['warmup', 'main', 'accessory', 'cooldown'], true) ? $section : 'main',
            'target_sets' => $this->intOrNull($exercise['target_sets'] ?? null, 1, 99),
            'rep_min' => $this->intOrNull($exercise['rep_min'] ?? null, 1, 999),
            'rep_max' => $this->intOrNull($exercise['rep_max'] ?? null, 1, 999),
            'duration_seconds' => $this->intOrNull($exercise['duration_seconds'] ?? null, 1, 86400),
            'target_weight' => $this->numberOrNull($exercise['target_weight'] ?? null),
            'target_rir' => $this->numberOrNull($exercise['target_rir'] ?? null),
            'rest_seconds' => $this->intOrNull($exercise['rest_seconds'] ?? null, 0, 3600),
            'tempo' => Str::limit(trim((string) ($exercise['tempo'] ?? '')), 40, ''),
            'side_mode' => in_array($sideMode, ['bilateral', 'per_side', 'alternating'], true) ? $sideMode : null,
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

    private function normalizeDay(array $day, string $weekday): array
    {
        $candidateType = $day['type'] ?? 'rest';
        $type = in_array($candidateType, ['training', 'rest', 'recovery'], true) ? $candidateType : 'rest';
        $exercises = array_slice((array) ($day['exercises'] ?? []), 0, self::MAX_EXERCISES_PER_DAY);

        return [
            'weekday' => $weekday,
            'title' => Str::limit(trim((string) ($day['title'] ?? ucfirst($type))), 120, ''),
            'type' => $type,
            'notes' => Str::limit(trim((string) ($day['notes'] ?? '')), 2000, ''),
            'exercises' => array_values(array_map(fn ($exercise) => $this->normalizeExercise((array) $exercise), $exercises)),
        ];
    }

    private function isSequentialPayload(array $days): bool
    {
        if (count($days) !== 7) {
            return false;
        }

        foreach ($days as $day) {
            $day = (array) $day;
            if (isset($day['day_order']) || isset($day['day_index']) || isset($day['order'])) {
                return true;
            }
        }

        $weekdays = collect($days)
            ->map(function ($day): string {
                $day = (array) $day;

                return strtolower((string) ($day['weekday'] ?? ''));
            })
            ->filter()
            ->values()
            ->all();

        // Already in canonical Saturday-first order: keep weekday mapping.
        if ($weekdays === WorkoutPlan::WEEKDAYS) {
            return false;
        }

        return true;
    }

    private function mapSequentialDays(array $days, string $anchor): array
    {
        $cursor = Carbon::parse($anchor)->startOfDay();
        $mapped = [];

        foreach (array_slice($days, 0, self::MAX_DAYS) as $day) {
            $day = (array) $day;
            $weekday = strtolower($cursor->format('l'));
            $mapped[] = $this->normalizeDay($day, $weekday);
            $cursor->addDay();
        }

        // Keep execution order (DAY 1 first) so storage, preview and the
        // builder all show the week the way the user will train it.
        return $mapped;
    }

    private function latinDigits($value)
    {
        if (! is_string($value)) {
            return $value;
        }
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace(array_merge($persian, $arabic), $latin, $value);
    }

    private function intOrNull($value, int $min, int $max): ?int
    {
        $value = $this->latinDigits($value);

        return is_numeric($value) ? max($min, min($max, (int) $value)) : null;
    }

    private function numberOrNull($value): ?float
    {
        $value = $this->latinDigits($value);

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
