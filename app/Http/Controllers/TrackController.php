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
            'build' => (clone $user->routines())->active()->where('behavior_type', '!=', Routine::BEHAVIOR_AVOID)->where('tracking_mode', Routine::TRACKING_NONE)->count(),
            'measurable' => (clone $user->routines())->active()->where('tracking_mode', '!=', Routine::TRACKING_NONE)->count(),
            'avoid' => (clone $user->routines())->active()->where('behavior_type', Routine::BEHAVIOR_AVOID)->count(),
        ];
        $counts['all'] = $counts['build'] + $counts['measurable'] + $counts['avoid'];

        $buildCards = collect();
        $cards = collect();
        $kinds = collect();
        $avoidCards = collect();
        $avoidSummary = null;

        if (in_array($tab, ['all', 'build'])) {
            $buildRoutines = $user->routines()->active()
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
            $routines = $user->routines()->active()
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

            $cards = $routines->map(fn ($r) => $this->card($r, $logsByRoutine->get($r->id, collect()), $today, $from30))->values();
            $kinds = $user->routines()->active()
                ->where('tracking_mode', '!=', Routine::TRACKING_NONE)
                ->whereNotNull('value_kind')
                ->distinct()
                ->pluck('value_kind')
                ->sort()
                ->values();
        }

        if (in_array($tab, ['all', 'avoid'])) {
            $avoidRoutines = $user->routines()->active()
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

    private function card(Routine $routine, $logs, ?Carbon $today = null, ?Carbon $from30 = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $from30 = ($from30 ?? $today->copy()->subDays(29))->copy()->startOfDay();
        $unit = $routine->tracking_mode === Routine::TRACKING_SETS
            ? null
            : ($routine->value_unit ?: null);
        $isTime = $routine->isTimeValue();

        // Full 30-day calendar so gaps are visible (the old spark only kept
        // logged dates, so misses/trends were invisible).
        $dayKeys = [];
        $cursor = $from30->copy();
        while ($cursor->lte($today)) {
            $dayKeys[] = $cursor->toDateString();
            $cursor->addDay();
        }
        $createdDay = $routine->created_at ? $routine->created_at->copy()->startOfDay()->toDateString() : null;
        $occursOn = function (string $day) use ($routine, $createdDay): bool {
            if ($createdDay && $day < $createdDay) {
                return false;
            }
            try {
                return $routine->occursOn($day);
            } catch (\Throwable $e) {
                return true;
            }
        };

        if ($routine->tracking_mode === Routine::TRACKING_VALUE) {
            $byDate = $logs->whereNull('checklist_item_id')->groupBy(fn ($l) => $this->key($l));
            $series = [];
            foreach ($dayKeys as $d) {
                $v = $byDate->has($d) ? (float) $byDate[$d]->first()->value : null;
                $occurs = $occursOn($d);
                $isToday = $d === $today->toDateString();
                $state = $v !== null ? 'logged' : ($occurs ? ($isToday ? 'today' : 'missed') : 'off');
                $series[] = ['date' => $d, 'value' => $v, 'occurs' => $occurs, 'state' => $state];
            }
            $logged = array_values(array_filter($series, fn ($s) => $s['value'] !== null));
            $vals = array_column($logged, 'value');
            $latest = end($logged) ?: null;
            $prev = count($logged) > 1 ? $logged[count($logged) - 2] : null;
            $delta = ($latest && $prev) ? round($latest['value'] - $prev['value'], 2) : null;

            $avg = $vals ? round(array_sum($vals) / count($vals), 2) : null;
            $last7Vals = array_column(array_slice($logged, -7), 'value');
            $avg7 = $last7Vals ? round(array_sum($last7Vals) / count($last7Vals), 2) : null;
            $min = $vals ? min($vals) : null;
            $max = $vals ? max($vals) : null;
            $minDate = null;
            $maxDate = null;
            foreach ($logged as $p) {
                if ($min !== null && $p['value'] == $min && $minDate === null) {
                    $minDate = $p['date'];
                }
                if ($max !== null && $p['value'] == $max) {
                    $maxDate = $p['date'];
                }
            }
            // Trend: least-squares slope over the last ≤14 logged points.
            $trend = $this->trendSlope(array_column(array_slice($logged, -14), 'value'));
            // Scheduled adherence (today excluded until logged, like elsewhere).
            $schedDays = array_filter($series, fn ($s) => $s['occurs'] && $s['date'] !== $today->toDateString());
            // If today is logged, count it too.
            if (($latest['date'] ?? null) === $today->toDateString()) {
                $schedDays[] = ['date' => $today->toDateString()];
            }
            $schedCount = count($schedDays);
            $loggedCount = count($vals);
            $rate = $schedCount ? (int) round(min($loggedCount, $schedCount) / $schedCount * 100) : 0;

            // Chart domain with padding so small variations (e.g. 06:50 vs
            // 07:10 wake-ups) are actually visible — the old v/max scaling
            // rendered every bar at ~95-100% height.
            [$cMin, $cMax] = $this->chartDomain($vals, $isTime);

            return [
                'routine' => $routine,
                'mode' => 'value',
                'is_time' => $isTime,
                'latest' => $latest['value'] ?? null,
                'latest_date' => $latest['date'] ?? null,
                'delta' => $delta,
                'delta_prev_date' => $prev['date'] ?? null,
                'unit' => $unit,
                'series' => $series,
                'values' => $vals,
                'logged_count' => $loggedCount,
                'sched_count' => $schedCount,
                'rate' => $rate,
                'avg' => $avg,
                'avg7' => $avg7,
                'min' => $min,
                'min_date' => $minDate,
                'max' => $max,
                'max_date' => $maxDate,
                'trend' => $trend,
                'chart_min' => $cMin,
                'chart_max' => $cMax,
                // Back-compat for old partials/tests.
                'spark' => array_column(array_slice($logged, -14), 'value'),
                'spark_labels' => array_column(array_slice($logged, -14), 'date'),
            ];
        }

        // Sets mode: daily volume (sum of set values) — same metric as the
        // stats page (the old card used set counts, which disagreed).
        $stepLogs = $logs->whereNotNull('checklist_item_id');
        $byDate = $stepLogs->groupBy(fn ($l) => $this->key($l));
        $series = [];
        foreach ($dayKeys as $d) {
            $g = $byDate->has($d) ? $byDate[$d] : collect();
            $vol = $g->count() ? round($g->sum(fn ($l) => (float) $l->value), 2) : null;
            $occurs = $occursOn($d);
            $isToday = $d === $today->toDateString();
            $state = $vol !== null ? 'logged' : ($occurs ? ($isToday ? 'today' : 'missed') : 'off');
            $series[] = ['date' => $d, 'value' => $vol, 'sets' => $g->count() ?: null, 'occurs' => $occurs, 'state' => $state];
        }
        $logged = array_values(array_filter($series, fn ($s) => $s['value'] !== null));
        $vals = array_column($logged, 'value');
        $latestEntry = end($logged) ?: null;
        $latestDate = $latestEntry['date'] ?? null;
        $latestSets = $latestDate ? $byDate[$latestDate] : collect();
        $stepNames = $routine->checklistItems->keyBy('id');
        $perStep = $latestSets->groupBy('checklist_item_id')->map(fn ($g, $itemId) => [
            'name' => $stepNames->get($itemId)?->name ?? ('Step #' . $itemId),
            'sets' => $g->sortBy('set_no')->map(fn ($l) => (float) $l->value)->values()->all(),
        ])->values();
        $prevEntry = count($logged) > 1 ? $logged[count($logged) - 2] : null;
        $delta = ($latestEntry && $prevEntry) ? round($latestEntry['value'] - $prevEntry['value'], 2) : null;
        $avg = $vals ? round(array_sum($vals) / count($vals), 2) : null;
        $last7Vals = array_column(array_slice($logged, -7), 'value');
        $avg7 = $last7Vals ? round(array_sum($last7Vals) / count($last7Vals), 2) : null;
        $trend = $this->trendSlope(array_column(array_slice($logged, -14), 'value'));
        $schedCount = count(array_filter($series, fn ($s) => $s['occurs'] && $s['date'] !== $today->toDateString()))
            + ((($latestEntry['date'] ?? null) === $today->toDateString()) ? 1 : 0);
        $rate = $schedCount ? (int) round(min(count($vals), $schedCount) / $schedCount * 100) : 0;
        [$cMin, $cMax] = $this->chartDomain($vals, false);

        return [
            'routine' => $routine,
            'mode' => 'sets',
            'latest' => $latestEntry['value'] ?? null,
            'latest_date' => $latestDate,
            'latest_suffix' => 'total',
            'latest_sets_count' => $latestSets->count() ?: null,
            'delta' => $delta,
            'delta_prev_date' => $prevEntry['date'] ?? null,
            'unit' => null,
            'series' => $series,
            'values' => $vals,
            'logged_count' => count($vals),
            'sched_count' => $schedCount,
            'rate' => $rate,
            'avg' => $avg,
            'avg7' => $avg7,
            'min' => $vals ? min($vals) : null,
            'max' => $vals ? max($vals) : null,
            'trend' => $trend,
            'chart_min' => $cMin,
            'chart_max' => $cMax,
            'spark' => array_column(array_slice($logged, -14), 'value'),
            'spark_labels' => array_column(array_slice($logged, -14), 'date'),
            'per_step' => $perStep,
        ];
    }

    /**
     * Least-squares slope per logged point (null when <3 points or flat).
     */
    private function trendSlope(array $vals): ?float
    {
        $n = count($vals);
        if ($n < 3) {
            return null;
        }
        $xs = range(0, $n - 1);
        $xMean = array_sum($xs) / $n;
        $yMean = array_sum($vals) / $n;
        $num = 0.0;
        $den = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $num += ($xs[$i] - $xMean) * ($vals[$i] - $yMean);
            $den += ($xs[$i] - $xMean) ** 2;
        }
        if ($den == 0) {
            return null;
        }
        $slope = $num / $den;
        // Ignore noise smaller than 0.5% of the mean per step.
        if ($yMean != 0 && abs($slope) < abs($yMean) * 0.005) {
            return 0.0;
        }

        return round($slope, 3);
    }

    /**
     * Padded chart domain so near-constant series (e.g. wake-up times
     * 06:50–07:10) still show variation instead of a flat line at ~100%.
     */
    private function chartDomain(array $vals, bool $isTime): array
    {
        if (! $vals) {
            return [0, 1];
        }
        $min = min($vals);
        $max = max($vals);
        $span = $max - $min;
        if ($span <= 0) {
            $pad = $isTime ? 30 : (abs($max) > 0 ? max(abs($max) * 0.1, 1) : 1);
            // For clock times keep the domain inside the day.
            if ($isTime) {
                return [max(0, $min - $pad), min(1439, $max + $pad)];
            }

            return [$min - $pad, $max + $pad];
        }
        $pad = $isTime ? max(15, $span * 0.3) : max($span * 0.2, $span > 0 ? $span * 0.2 : 1);
        $lo = $min - $pad;
        $hi = $max + $pad;
        if ($isTime) {
            $lo = max(0, $lo);
            $hi = min(1439, $hi);
        } elseif ($min >= 0 && $lo < 0) {
            // Non-negative kinds (weight/reps/…) keep zero in view when close.
            $lo = min(0, $min - $span * 0.1);
        }

        return [$lo, $hi];
    }

    private function key($log): string
    {
        return $log->completed_date instanceof Carbon
            ? $log->completed_date->toDateString()
            : substr((string) $log->completed_date, 0, 10);
    }
}
