<?php

namespace App\Http\Controllers;

use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Services\Reports\AvoidanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TrackController extends Controller
{
    public const TABS = ['all', 'build', 'measurable', 'avoid'];

    /**
     * Track hub with separated habit types: build (plain ticks),
     * measurable (value/sets numbers) and avoid (clean vs slips).
     * All relations are preloaded in bulk (no N+1); model metric
     * methods reuse the loaded relations.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'kind' => 'nullable|string|max:20',
        ]);
        $tab = $data['tab'] ?? 'all';
        $kind = $data['kind'] ?? null;

        $today = now()->startOfDay();
        $from30 = $today->copy()->subDays(29)->startOfDay();
        $yearAgo = $today->copy()->subYear()->startOfDay();

        $counts = [
            'build' => (clone $user->routines())->where('behavior_type', '!=', Routine::BEHAVIOR_AVOID)->where('tracking_mode', Routine::TRACKING_NONE)->count(),
            'measurable' => (clone $user->routines())->where('tracking_mode', '!=', Routine::TRACKING_NONE)->count(),
            'avoid' => (clone $user->routines())->where('behavior_type', Routine::BEHAVIOR_AVOID)->count(),
        ];
        $counts['all'] = $counts['build'] + $counts['measurable'] + $counts['avoid'];

        $buildCards = collect();
        $cards = collect();
        $kinds = collect();
        $avoidCards = collect();
        $avoidSummary = null;

        if (in_array($tab, ['all', 'build'])) {
            $buildRoutines = $user->routines()
                ->where('behavior_type', '!=', Routine::BEHAVIOR_AVOID)
                ->where('tracking_mode', Routine::TRACKING_NONE)
                ->with('checklistItems')
                ->orderBy('title')
                ->get();

            if ($buildRoutines->isNotEmpty()) {
                $compRows = RoutineCompletion::where('user_id', $user->id)
                    ->whereIn('routine_id', $buildRoutines->pluck('id'))
                    ->whereBetween('completed_date', [$yearAgo->toDateString(), $today->toDateString()])
                    ->get()
                    ->groupBy('routine_id');
                foreach ($buildRoutines as $r) {
                    $r->setRelation('completions', $compRows->get($r->id, collect()));
                }
                $buildCards = $buildRoutines->map(fn ($r) => $this->buildCard($r, $today))->values();
            }
        }

        if (in_array($tab, ['all', 'measurable'])) {
            $routines = $user->routines()
                ->where('tracking_mode', '!=', Routine::TRACKING_NONE)
                ->when($kind, fn ($q) => $q->where('value_kind', $kind))
                ->with('checklistItems')
                ->orderBy('title')
                ->get();

            $logsByRoutine = $routines->isNotEmpty()
                ? \App\Models\RoutineLog::where('user_id', $user->id)
                    ->whereIn('routine_id', $routines->pluck('id'))
                    ->where('completed_date', '>=', $from30->toDateString())
                    ->orderBy('completed_date')
                    ->get()
                    ->groupBy('routine_id')
                : collect();

            $cards = $routines->map(fn ($r) => $this->card($r, $logsByRoutine->get($r->id, collect())))->values();
            $kinds = $user->routines()
                ->where('tracking_mode', '!=', Routine::TRACKING_NONE)
                ->whereNotNull('value_kind')
                ->distinct()
                ->pluck('value_kind')
                ->sort()
                ->values();
        }

        if (in_array($tab, ['all', 'avoid'])) {
            $avoidRoutines = $user->routines()
                ->where('behavior_type', Routine::BEHAVIOR_AVOID)
                ->with('checklistItems')
                ->orderBy('title')
                ->get();

            if ($avoidRoutines->isNotEmpty()) {
                $violRows = \App\Models\RoutineViolation::where('user_id', $user->id)
                    ->whereIn('routine_id', $avoidRoutines->pluck('id'))
                    ->whereBetween('occurred_date', [$from30->toDateString(), $today->toDateString()])
                    ->orderBy('occurred_at')
                    ->get()
                    ->groupBy('routine_id');
                $noteRows = \App\Models\RoutineNote::where('user_id', $user->id)
                    ->whereIn('routine_id', $avoidRoutines->pluck('id'))
                    ->whereBetween('occurred_at', [$from30->copy()->startOfDay(), $today->copy()->endOfDay()])
                    ->orderByDesc('occurred_at')
                    ->get()
                    ->groupBy('routine_id');
                foreach ($avoidRoutines as $r) {
                    $r->setRelation('violations', $violRows->get($r->id, collect()));
                    $r->setRelation('routineNotes', $noteRows->get($r->id, collect()));
                }
                $avoidCards = $avoidRoutines->map(fn ($r) => $this->avoidCard($r, $today, $from30))->values();
            }

            $avoidSummary = AvoidanceReportService::summarize($user->id, $from30->copy(), $today->copy());
        }

        return view('track.index', compact('cards', 'kinds', 'kind', 'tab', 'counts', 'buildCards', 'avoidCards', 'avoidSummary'));
    }

    /**
     * Plain build habit card: 30-day adherence, streaks, last-14-day strip.
     */
    private function buildCard(Routine $routine, Carbon $today): array
    {
        $adherence = $routine->adherence(30, $today);
        $streak = $routine->streakStats($today);
        $doneKeys = array_flip($routine->completionDateKeys($today->copy()->subDays(13), $today));

        $occSet = array_flip($routine->occurrenceDates($today->copy()->subDays(13), $today));
        $last14 = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $key = $day->toDateString();
            if ($day->isFuture() || $day->isSameDay(now())) {
                $state = $day->isSameDay(now()) ? (isset($doneKeys[$key]) ? 'done' : 'today') : 'future';
            } else {
                $state = isset($occSet[$key]) ? (isset($doneKeys[$key]) ? 'done' : 'missed') : 'na';
            }
            $last14[] = ['date' => $key, 'state' => $state];
        }

        return [
            'routine' => $routine,
            'rate' => $adherence['rate'],
            'done' => $adherence['completed'],
            'total' => $adherence['total'],
            'streak' => $streak['current'],
            'best' => $streak['best'],
            'steps' => $routine->checklistItems->count(),
            'last14' => $last14,
        ];
    }

    /**
     * Avoid habit card: clean metrics, 30-day slip spark, top
     * trigger/location, avg mood, peak hour, last entry.
     */
    private function avoidCard(Routine $routine, Carbon $today, Carbon $from30): array
    {
        $metrics = $routine->avoidMetrics($today);
        $violations = $routine->relationLoaded('violations') ? $routine->violations : collect();
        $notes = $routine->relationLoaded('routineNotes') ? $routine->routineNotes : collect();

        $perDay = [];
        $cursor = $from30->copy();
        while ($cursor->lte($today)) {
            $perDay[$cursor->toDateString()] = 0;
            $cursor->addDay();
        }
        foreach ($violations as $v) {
            $k = $v->occurred_date instanceof Carbon ? $v->occurred_date->toDateString() : Carbon::parse($v->occurred_date)->toDateString();
            if (array_key_exists($k, $perDay)) {
                $perDay[$k] += (int) ($v->quantity ?? 1);
            }
        }

        $topTrigger = $violations->filter(fn ($v) => $v->trigger)
            ->groupBy(fn ($v) => mb_strtolower(trim((string) $v->trigger)))
            ->map->count()->sortDesc()->take(1);
        $topLocation = $violations->filter(fn ($v) => $v->location)
            ->groupBy(fn ($v) => mb_strtolower(trim((string) $v->location)))
            ->map->count()->sortDesc()->take(1);
        $moodVals = $violations->pluck('mood')->filter(fn ($m) => $m !== null)->values();

        $byHour = array_fill(0, 24, 0);
        foreach ($violations as $v) {
            if ($v->occurred_at) {
                $byHour[(int) $v->occurred_at->format('G')]++;
            }
        }

        $lastSlip = $violations->sortByDesc('occurred_at')->first();
        $lastNote = $notes->sortByDesc('occurred_at')->first();
        $lastEntry = null;
        if ($lastSlip && (!$lastNote || $lastSlip->occurred_at->gt($lastNote->occurred_at))) {
            $lastEntry = ['type' => 'slip', 'at' => $lastSlip->occurred_at, 'x' => $lastSlip->trigger];
        } elseif ($lastNote) {
            $lastEntry = ['type' => $lastNote->kind, 'at' => $lastNote->occurred_at, 'x' => $lastNote->trigger];
        }

        $todayQty = $routine->violationQtyOn($today);

        return [
            'routine' => $routine,
            'clean_rate' => $metrics['rate'],
            'clean' => $metrics['clean'],
            'total' => $metrics['total'],
            'streak' => $metrics['current'],
            'best' => $metrics['best'],
            'last7' => $metrics['last7'] ?? [],
            'today_qty' => $todayQty,
            'violated_today' => $todayQty > 0,
            'per_day' => $perDay,
            'per_day_max' => max(1, max($perDay)),
            'slip_total' => (int) $violations->sum('quantity'),
            'cravings' => $notes->where('kind', 'craving')->count(),
            'top_trigger' => $topTrigger->keys()->first(),
            'top_trigger_n' => $topTrigger->first(),
            'top_location' => $topLocation->keys()->first(),
            'mood_avg' => $moodVals->isNotEmpty() ? round($moodVals->avg(), 1) : null,
            'peak_hour' => array_search(max($byHour), $byHour),
            'last_entry' => $lastEntry,
        ];
    }

    private function card(Routine $routine, $logs): array
    {
        $unit = $routine->tracking_mode === Routine::TRACKING_SETS
            ? null
            : ($routine->value_unit ?: null);

        if ($routine->tracking_mode === Routine::TRACKING_VALUE) {
            $byDate = $logs->whereNull('checklist_item_id')->groupBy(fn ($l) => $this->key($l));
            $dates = $byDate->keys()->sort()->values();
            $points = $dates->map(fn ($d) => ['date' => $d, 'value' => (float) $byDate[$d]->first()->value])->values();
            $latest = $points->last();
            $prev = $points->count() > 1 ? $points[$points->count() - 2] : null;

            return [
                'routine' => $routine,
                'is_time' => $routine->isTimeValue(),
                'latest' => $latest['value'] ?? null,
                'latest_date' => $latest['date'] ?? null,
                'delta' => ($latest && $prev) ? round($latest['value'] - $prev['value'], 2) : null,
                'unit' => $unit,
                'spark' => $points->take(-14)->pluck('value')->all(),
                'spark_labels' => $points->take(-14)->pluck('date')->all(),
            ];
        }

        // Sets mode: activity spark = sets logged per day; latest session detail.
        $stepLogs = $logs->whereNotNull('checklist_item_id');
        $byDate = $stepLogs->groupBy(fn ($l) => $this->key($l));
        $dates = $byDate->keys()->sort()->values();
        $latestDate = $dates->last();
        $latestSets = $latestDate ? $byDate[$latestDate] : collect();
        $stepNames = $routine->checklistItems->keyBy('id');
        $perStep = $latestSets->groupBy('checklist_item_id')->map(fn ($g, $itemId) => [
            'name' => $stepNames->get($itemId)?->name ?? ('Step #' . $itemId),
            'sets' => $g->sortBy('set_no')->map(fn ($l) => (float) $l->value)->values()->all(),
        ])->values();

        return [
            'routine' => $routine,
            'latest' => $latestSets->count() ?: null,
            'latest_date' => $latestDate,
            'latest_suffix' => 'sets',
            'delta' => null,
            'unit' => null,
            'spark' => $dates->take(-14)->map(fn ($d) => $byDate[$d]->count())->all(),
            'spark_labels' => $dates->take(-14)->all(),
            'per_step' => $perStep,
        ];
    }

    private function key($log): string
    {
        return $log->completed_date instanceof Carbon
            ? $log->completed_date->toDateString()
            : substr((string) $log->completed_date, 0, 10);
    }
}
