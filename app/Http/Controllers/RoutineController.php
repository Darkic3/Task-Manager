<?php

namespace App\Http\Controllers;

use App\Models\Routine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoutineController extends Controller
{
    private const WEEK_DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    public function index()
    {
        $user = Auth::user();
        $today = now()->startOfDay();
        // One shared order everywhere (sortKey): period slot → manual drag
        // order → exact time → title — so My Day mirrors this page exactly.
        // Routines WITHOUT their own schedule but WITH timed steps (e.g.
        // Cobra Pose: morning/noon/night steps) are "exploded" into one row
        // per timed step, so each step can be sorted/prioritized in its own
        // time-of-day group against the other routines.
        $routines = $user->routines()->with(['checklistItems' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])->get();

        // Batch: one completions query for [today-1y .. today]; ring math is pure PHP.
        $from = $today->copy()->subYear()->startOfDay();
        $rows = $routines->isNotEmpty()
            ? \App\Models\RoutineCompletion::where('user_id', $user->id)
                ->whereIn('routine_id', $routines->pluck('id'))
                ->whereBetween('completed_date', [$from->toDateString(), $today->toDateString()])
                ->get()
                ->groupBy('routine_id')
            : collect();

        $vRows = $routines->isNotEmpty()
            ? \App\Models\RoutineViolation::where('user_id', $user->id)
                ->whereIn('routine_id', $routines->pluck('id'))
                ->whereBetween('occurred_date', [$from->toDateString(), $today->toDateString()])
                ->get()
                ->groupBy('routine_id')
            : collect();

        foreach ($routines as $routine) {
            $routine->setRelation('completions', $rows->get($routine->id, collect()));
            $routine->setRelation('violations', $vRows->get($routine->id, collect()));
            if ($routine->isAvoid()) {
                $m = $routine->avoidMetrics($today);
                $routine->ringRate = $m['rate'];
                $routine->ringStreak = $m['current'];
                $routine->ringLast7 = $m['last7'];
            } else {
                $m = $this->habitMetricsFromLoaded($routine, $today);
                $routine->ringRate = $m['rate'];
                $routine->ringStreak = $m['streak'];
                $routine->ringLast7 = $m['last7'];
            }
        }

        $weekly = $this->weeklyConsistencyFromLoaded($routines, $today);

        // Build display rows: explode unscheduled routines with timed steps
        // into one row per timed step (e.g. Cobra Pose → Morning/Noon/Night).
        $displayItems = $this->buildDisplayItems($routines);

        return view('routines.index', compact('routines', 'weekly', 'displayItems'));
    }

    /**
     * Explode routines without their own schedule but with timed steps into
     * one sortable row per timed step. Every row carries:
     * kind (routine|step), period key, manual order and a sort key so the
     * view can group by time-of-day and order inside each group.
     */
    private function buildDisplayItems($routines): array
    {
        $items = [];

        foreach ($routines as $routine) {
            $steps = $routine->relationLoaded('checklistItems')
                ? $routine->checklistItems
                : $routine->checklistItems()->orderBy('sort_order')->orderBy('id')->get();

            $timed = $steps->filter(fn ($s) => $s->hasSchedule())->values();
            $untimedCount = $steps->count() - $timed->count();

            // Explode only when the routine itself is unscheduled but its
            // steps carry times — otherwise a single routine row is enough
            // (planner already floats it to its active step via sortKey).
            if (! $routine->hasSchedule() && $timed->isNotEmpty()) {
                foreach ($timed as $step) {
                    $period = $step->time_period ?: $this->hourToPeriodKey((string) $step->scheduled_time);
                    $items[] = [
                        'kind' => 'step',
                        'period' => $period,
                        'routine' => $routine,
                        'step' => $step,
                        'manual' => (int) ($step->sort_order ?? 0),
                        'time' => $step->scheduled_time ? substr((string) $step->scheduled_time, 0, 5) : '99:99',
                        'title' => $routine->title.' → '.$step->name,
                    ];
                }
                // Steps without any time stay behind as a single "other steps" row.
                if ($untimedCount > 0 || $steps->isEmpty()) {
                    $items[] = [
                        'kind' => 'routine',
                        'period' => 'anytime',
                        'routine' => $routine,
                        'step' => null,
                        'manual' => (int) ($routine->sort_order ?? 0),
                        'time' => '99:99',
                        'title' => $routine->title,
                        'remainder' => true,
                        'remainder_count' => $untimedCount,
                    ];
                }

                continue;
            }

            $period = $routine->time_period
                ?: ($routine->start_time ? $this->hourToPeriodKey((string) $routine->start_time) : null)
                ?: ($timed->isNotEmpty()
                    ? ($timed->sortBy(fn ($s) => $s->sortKey())->first()->time_period
                        ?: $this->hourToPeriodKey((string) ($timed->sortBy(fn ($s) => $s->sortKey())->first()->scheduled_time ?? '')))
                    : 'anytime');

            $items[] = [
                'kind' => 'routine',
                'period' => $period ?: 'anytime',
                'routine' => $routine,
                'step' => null,
                'manual' => (int) ($routine->sort_order ?? 0),
                'time' => $routine->start_time ? Carbon::parse($routine->start_time)->format('H:i') : '99:99',
                'title' => $routine->title,
            ];
        }

        $orderOf = fn ($pk) => $pk === 'anytime' ? 99 : (int) config("routines.periods.{$pk}.order", 99);

        usort($items, function ($a, $b) use ($orderOf) {
            $oa = $orderOf($a['period']);
            $ob = $orderOf($b['period']);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }
            if ($a['manual'] !== $b['manual']) {
                return $a['manual'] <=> $b['manual'];
            }
            if ($a['time'] !== $b['time']) {
                return strcmp($a['time'], $b['time']);
            }

            return strcmp(mb_strtolower($a['title']), mb_strtolower($b['title']));
        });

        return $items;
    }

    private function hourToPeriodKey(?string $time): string
    {
        if (! $time) {
            return 'anytime';
        }
        try {
            $h = (int) Carbon::parse($time)->format('G');
            if ($h < 12) {
                return 'morning';
            }
            if ($h < 14) {
                return 'noon';
            }
            if ($h < 18) {
                return 'afternoon';
            }
            if ($h < 21) {
                return 'evening';
            }

            return 'night';
        } catch (\Exception $e) {
            return 'anytime';
        }
    }

    /**
     * Persist drag order of routines within a time period group.
     * Accepts an optional time_period per item so a routine row dropped
     * into another group also moves its schedule there.
     */
    public function reorder(Request $request)
    {
        $periodKeys = array_keys(config('routines.periods', []));
        $data = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer|min:0|max:99999',
            'items.*.time_period' => 'nullable|string',
        ]);

        $routines = Routine::where('user_id', Auth::id())
            ->whereIn('id', collect($data['items'])->pluck('id'))
            ->get()->keyBy('id');

        $updated = 0;
        foreach ($data['items'] as $item) {
            $routine = $routines->get($item['id']);
            if (! $routine) {
                continue;
            }
            $routine->sort_order = $item['sort_order'];
            // Cross-group drop: retime the routine (period groups only;
            // 'anytime' clears the period, exact times stay untouched).
            if (array_key_exists('time_period', $item)) {
                $tp = $item['time_period'];
                if ($tp === null || $tp === 'anytime' || $tp === '') {
                    $routine->time_period = null;
                } elseif (in_array($tp, $periodKeys, true)) {
                    $routine->time_period = $tp;
                    $routine->start_time = null;
                    $routine->end_time = null;
                }
            }
            $routine->save();
            $updated++;
        }

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    /**
     * Persist drag order of exploded timed steps (e.g. Cobra Pose steps)
     * inside a time-of-day group, and retime a step when it is dropped
     * into a different group. Each step keeps its own sort_order so
     * morning/noon/night steps of one routine can be prioritized
     * independently against the other routines.
     */
    public function reorderSteps(Request $request)
    {
        $periodKeys = array_keys(config('routines.periods', []));
        $data = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer|min:0|max:99999',
            'items.*.time_period' => 'nullable|string',
        ]);

        $steps = \App\Models\RoutineChecklistItem::where('user_id', Auth::id())
            ->whereIn('id', collect($data['items'])->pluck('id'))
            ->get()->keyBy('id');

        $updated = 0;
        foreach ($data['items'] as $item) {
            $step = $steps->get($item['id']);
            if (! $step) {
                continue;
            }
            $step->sort_order = $item['sort_order'];
            if (array_key_exists('time_period', $item)) {
                $tp = $item['time_period'];
                if ($tp === null || $tp === 'anytime' || $tp === '') {
                    $step->time_period = null;
                    // Keep an exact time if it had one, otherwise unscheduled.
                } elseif (in_array($tp, $periodKeys, true)) {
                    $step->time_period = $tp;
                    $step->scheduled_time = null;
                }
            }
            $step->save();
            $updated++;
        }

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    public function create()
    {
        return view('routines.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $items = $request->input('items', []);

        $routine = Auth::user()->routines()->create($data);
        $this->syncItems($routine, $items);

        return redirect()->route('routines.index')->with('success', 'Routine created successfully.');
    }

    public function edit(Routine $routine)
    {
        $this->authorizeRoutine($routine);
        $routine->load('checklistItems');

        return view('routines.edit', compact('routine'));
    }

    public function update(Request $request, Routine $routine)
    {
        $this->authorizeRoutine($routine);

        $data = $this->validated($request);
        $routine->update($data);
        $this->syncItems($routine, $request->input('items', []));

        return redirect()->route('routines.index')->with('success', 'Routine updated successfully.');
    }

    public function destroy(Routine $routine)
    {
        $this->authorizeRoutine($routine);

        $routine->delete();

        return redirect()->route('routines.index')->with('success', 'Routine deleted successfully.');
    }

    /**
     * Start a new cycle: duplicate the routine with its steps for editing
     * (change moves/sets for the coming weeks) and archive the current one.
     * History and logs of the old cycle stay untouched for comparison.
     */
    public function newCycle(Routine $routine)
    {
        $this->authorizeRoutine($routine);

        $copy = $routine->replicate(['deleted_at']);
        $copy->parent_id = $routine->id;
        $copy->cycle_no = ((int) $routine->cycle_no) + 1;
        $copy->save();

        foreach ($routine->checklistItems()->orderBy('sort_order')->orderBy('id')->get() as $i => $item) {
            $copy->checklistItems()->create([
                'user_id' => $copy->user_id,
                'name' => $item->name,
                'sort_order' => $i,
                'target_sets' => $item->target_sets,
                'unit' => $item->unit,
                'time_period' => $item->time_period,
                'scheduled_time' => $item->scheduled_time,
            ]);
        }

        $routine->delete();

        return redirect()->route('routines.edit', $copy)
            ->with('success', "Cycle {$copy->cycle_no} started — adjust moves and sets, then save. Previous cycle is archived with its history.");
    }

    public function stats(Routine $routine)
    {
        $this->authorizeRoutine($routine);

        $today = now()->startOfDay();
        $start = $today->copy()->startOfWeek(Carbon::SATURDAY)->subWeeks(15)->startOfDay();
        $end = $start->copy()->addDays(16 * 7 - 1)->startOfDay();

        $cells = $routine->heatmapCells($start, $end);

        $weeks = [];
        $monthSpans = [];
        $cursor = $start->copy();

        for ($w = 0; $w < 16; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $week[] = $cells[$cursor->toDateString()];
                $cursor->addDay();
            }
            $weeks[] = $week;

            $label = $week[0]['date']->format('M');
            if (empty($monthSpans) || end($monthSpans)['label'] !== $label) {
                $monthSpans[] = ['label' => $label, 'span' => 1];
            } else {
                $monthSpans[count($monthSpans) - 1]['span']++;
            }
        }

        $streak = $routine->streakStats($today);
        $adherence = $routine->adherence(30, $today);
        $tracker = $routine->completionTracker();
        $valueStats = $this->valueStats($routine, $today);
        $prevCycle = $this->prevCycleSummary($routine);

        // Avoid habits: clean-day stats + violated heatmap cells + slip/note summary.
        $avoid = null;
        if ($routine->isAvoid()) {
            $violated = array_flip($routine->violatedDateKeys($start, $end));
            foreach ($cells as $key => &$cell) {
                $cell['violated'] = isset($violated[$key]);
            }
            unset($cell);

            $am = $routine->avoidMetrics($today);
            $streak = [
                'current' => $am['current'],
                'best' => $am['best'],
                'completed' => $am['clean'],
                'total' => $am['total'],
                'rate' => $am['rate'],
            ];
            $adherence = ['completed' => $am['clean'], 'total' => $am['total'], 'rate' => $am['rate']];
            $monthAgo = $today->copy()->subDays(29)->startOfDay();
            $monthViolations = $routine->violations()->whereBetween('occurred_date', [$monthAgo->toDateString(), $today->toDateString()])->get();
            $perDay = [];
            $cursor = $monthAgo->copy();
            while ($cursor->lte($today)) {
                $perDay[$cursor->toDateString()] = 0;
                $cursor->addDay();
            }
            foreach ($monthViolations as $v) {
                $k = $v->occurred_date instanceof Carbon ? $v->occurred_date->toDateString() : Carbon::parse($v->occurred_date)->toDateString();
                if (array_key_exists($k, $perDay)) {
                    $perDay[$k] += (int) ($v->quantity ?? 1);
                }
            }
            $topTriggers = $monthViolations->filter(fn ($v) => $v->trigger)->groupBy(fn ($v) => mb_strtolower(trim((string) $v->trigger)))->map->count()->sortDesc()->take(5);
            $topLocations = $monthViolations->filter(fn ($v) => $v->location)->groupBy(fn ($v) => mb_strtolower(trim((string) $v->location)))->map->count()->sortDesc()->take(5);
            $moodVals = $monthViolations->pluck('mood')->filter(fn ($m) => $m !== null)->values();
            $byHour = array_fill(0, 24, 0);
            foreach ($monthViolations as $v) {
                if ($v->occurred_at) {
                    $byHour[(int) $v->occurred_at->format('G')]++;
                }
            }
            $peakHour = array_search(max($byHour), $byHour);
            $avoid = [
                'slip_total' => (int) $routine->violations()->sum('quantity'),
                'slip_days' => count($routine->violatedDateKeys($today->copy()->subYear(), $today)),
                'cravings' => $routine->routineNotes()->where('kind', 'craving')->count(),
                'recent_notes' => $routine->routineNotes()->orderByDesc('occurred_at')->limit(10)->get(),
                'today_violations' => $routine->violations()->whereDate('occurred_date', today()->toDateString())->orderByDesc('occurred_at')->get(),
                'today_notes' => $routine->routineNotes()->whereDate('occurred_at', today()->toDateString())->orderByDesc('occurred_at')->get(),
                'per_day' => $perDay,
                'per_day_max' => max(1, max($perDay)),
                'top_triggers' => $topTriggers,
                'top_locations' => $topLocations,
                'mood_avg' => $moodVals->isNotEmpty() ? round($moodVals->avg(), 1) : null,
                'mood_count' => $moodVals->count(),
                'by_hour' => $byHour,
                'peak_hour' => $peakHour,
            ];
            $tracker = null;
            $valueStats = null;
        }

        return view('routines.stats', compact('routine', 'weeks', 'monthSpans', 'streak', 'adherence', 'tracker', 'valueStats', 'prevCycle', 'avoid'));
    }

    /**
     * One-line comparison with the archived previous cycle (if any).
     */
    private function prevCycleSummary(Routine $routine): ?array
    {
        if (! $routine->parent_id) {
            return null;
        }
        $prev = Routine::withTrashed()->find($routine->parent_id);
        if (! $prev || $prev->user_id !== $routine->user_id) {
            return null;
        }
        $logs = $prev->completions()->count();
        $lastValue = null;
        if ($prev->tracking_mode === Routine::TRACKING_VALUE) {
            $lastValue = \App\Models\RoutineLog::where('routine_id', $prev->id)
                ->whereNull('checklist_item_id')
                ->orderByDesc('completed_date')
                ->first()?->value;
        }

        return [
            'title' => $prev->title,
            'cycle_no' => $prev->cycle_no,
            'completions' => $logs,
            'last_value' => $lastValue !== null ? (float) $lastValue : null,
            'unit' => $prev->value_unit,
        ];
    }

    /**
     * Logged-value history for tracked routines (single query):
     * daily series for the chart + personal record + per-step latest.
     */
    private function valueStats(Routine $routine, Carbon $today): ?array
    {
        if (! $routine->isTracked()) {
            return null;
        }

        $from = $today->copy()->subDays(89)->startOfDay();
        $logs = \App\Models\RoutineLog::where('routine_id', $routine->id)
            ->where('completed_date', '>=', $from->toDateString())
            ->orderBy('completed_date')
            ->get();

        if ($routine->tracking_mode === Routine::TRACKING_VALUE) {
            $points = $logs->whereNull('checklist_item_id')
                ->groupBy(fn ($l) => $l->completed_date instanceof Carbon
                    ? $l->completed_date->toDateString()
                    : substr((string) $l->completed_date, 0, 10))
                ->map(fn ($g, $d) => ['date' => $d, 'value' => (float) $g->first()->value])
                ->sortBy('date')->values();

            return [
                'mode' => 'value',
                'is_time' => $routine->isTimeValue(),
                'points' => $points->all(),
                'latest' => $points->last(),
                'pr' => $points->max('value'),
                'avg' => $points->count() ? round($points->avg('value'), 2) : null,
                'unit' => $routine->value_unit,
                'label' => $routine->trackingLabel(),
            ];
        }

        // Sets mode: daily volume (sum) for the chart + per-step latest session.
        $stepLogs = $logs->whereNotNull('checklist_item_id');
        $byDate = $stepLogs->groupBy(fn ($l) => $l->completed_date instanceof Carbon
            ? $l->completed_date->toDateString()
            : substr((string) $l->completed_date, 0, 10));
        $points = $byDate->map(fn ($g, $d) => ['date' => $d, 'value' => round($g->sum(fn ($l) => (float) $l->value), 2)])
            ->sortBy('date')->values();

        $lastDate = $byDate->keys()->sort()->last();
        $stepNames = $routine->checklistItems->keyBy('id');
        $perStep = $lastDate ? $byDate[$lastDate]->groupBy('checklist_item_id')->map(fn ($g, $itemId) => [
            'name' => $stepNames->get($itemId)?->name ?? ('Step #' . $itemId),
            'sets' => $g->sortBy('set_no')->map(fn ($l) => (float) $l->value)->values()->all(),
            'best' => $stepLogs->where('checklist_item_id', $itemId)->max(fn ($l) => (float) $l->value),
        ])->values()->all() : [];

        return [
            'mode' => 'sets',
            'points' => $points->all(),
            'latest' => $points->last(),
            'pr' => $points->max('value'),
            'avg' => null,
            'unit' => 'total',
            'label' => 'Daily volume',
            'per_step' => $perStep,
            'last_date' => $lastDate,
        ];
    }

    public function showAll()
    {
        return redirect()->route('routines.index');
    }

    public function showDaily()
    {
        return redirect()->route('routines.index', ['filter' => 'daily']);
    }

    public function showWeekly()
    {
        return redirect()->route('routines.index', ['filter' => 'weekly']);
    }

    public function showMonthly()
    {
        return redirect()->route('routines.index', ['filter' => 'monthly']);
    }

    /**
     * Weekly consistency: occurrences vs completions over the last 7 days
     * across all routines (today's unchecked occurrences don't count as misses).
     * Pure PHP over already-loaded `completions` relations (no queries).
     */
    private function weeklyConsistencyFromLoaded($routines, Carbon $today): array
    {
        $start = $today->copy()->subDays(6);
        $occ = 0;
        $done = 0;

        foreach ($routines as $routine) {
            $dates = $routine->occurrenceDates($start, $today);

            if ($dates && end($dates) === $today->toDateString() && ! $routine->completedOn($today)) {
                array_pop($dates);
            }

            $occ += count($dates);
            $done += count(array_intersect($dates, $routine->completionDateKeys($start, $today)));
        }

        return [
            'done' => $done,
            'total' => $occ,
            'rate' => $occ ? (int) round($done / $occ * 100) : 0,
        ];
    }

    /**
     * Pure-PHP habit metrics from the preloaded `completions` relation.
     */
    private function habitMetricsFromLoaded(Routine $routine, Carbon $date): array
    {
        $date = $date->copy()->startOfDay();
        $todayKey = $date->toDateString();
        $completedKeys = array_flip($routine->completionDateKeys($date->copy()->subYear(), $date));

        $rate = $routine->adherence(30, $date)['rate'];

        // Current streak without extra queries (uses the preloaded keys above).
        $occurrences = $routine->occurrenceDates($date->copy()->subYear(), $date);
        if ($occurrences && end($occurrences) === $todayKey && ! isset($completedKeys[$todayKey])) {
            array_pop($occurrences);
        }
        $streak = 0;
        for ($i = count($occurrences) - 1; $i >= 0; $i--) {
            if (! isset($completedKeys[$occurrences[$i]])) {
                break;
            }
            $streak++;
        }

        $from = $date->copy()->subDays(6);
        $last7Keys = array_flip($routine->completionDateKeys($from, $date));
        $now = now()->startOfDay();
        $last7 = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $date->copy()->subDays($i);
            $key = $day->toDateString();
            $occurs = (! $routine->created_at || ! $day->lt($routine->created_at->copy()->startOfDay()))
                && $routine->occursOn($day);

            $state = 'na';
            if ($day->isFuture() || $day->isSameDay($now)) {
                $state = $day->isSameDay($now) ? 'today' : 'future';
            } elseif ($occurs) {
                $state = isset($last7Keys[$key]) ? 'done' : 'missed';
            }

            $last7[] = ['date' => $key, 'state' => $state];
        }

        return ['rate' => $rate, 'streak' => $streak, 'last7' => $last7];
    }

    private function weeklyConsistency($routines, Carbon $today): array
    {
        $start = $today->copy()->subDays(6);
        $occ = 0;
        $done = 0;

        foreach ($routines as $routine) {
            $dates = $routine->occurrenceDates($start, $today);

            if ($dates && end($dates) === $today->toDateString() && ! $routine->completedOn($today)) {
                array_pop($dates);
            }

            $occ += count($dates);
            $done += count(array_intersect($dates, $routine->completionDateKeys($start, $today)));
        }

        return [
            'done' => $done,
            'total' => $occ,
            'rate' => $occ ? (int) round($done / $occ * 100) : 0,
        ];
    }

    private function validated(Request $request): array
    {
        $periodKeys = array_keys(config('routines.periods', []));

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'required|in:daily,weekly,monthly,every_n_days',
            'behavior_type' => 'nullable|in:build,avoid',
            'count_violations' => 'nullable|boolean',
            'every_n_days' => 'nullable|required_if:frequency,every_n_days|integer|min:2|max:60',
            'time_period' => 'nullable|in:'.implode(',', $periodKeys),
            'start_time' => 'nullable|required_with:end_time',
            'end_time' => 'nullable|required_with:start_time|after_or_equal:start_time',
            'items' => 'nullable|array|max:20',
            'items.*.name' => 'required_with:items.*.id|string|max:255',
            'tracking_mode' => 'nullable|in:none,value,sets',
            'value_kind' => 'nullable|in:number,weight,time,reps,percent',
            'value_unit' => 'nullable|string|max:20',
            'value_label' => 'nullable|string|max:100',
            'items.*.target_sets' => 'nullable|integer|min:1|max:20',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.time_period' => 'nullable|in:'.implode(',', $periodKeys),
            'items.*.scheduled_time' => 'nullable|date_format:H:i',
        ];

        if ($request->input('frequency') === 'weekly') {
            $rules['days'] = 'required|array|min:1';
            $rules['days.*'] = 'string|in:'.implode(',', self::WEEK_DAYS);
        } elseif ($request->input('frequency') === 'monthly') {
            $rules['month_days'] = 'required|array|min:1';
            $rules['month_days.*'] = 'integer|between:1,31';
        }

        $data = $request->validate($rules);

        $frequency = $data['frequency'];

        if ($frequency === 'weekly') {
            $data['days'] = array_values(array_unique(array_map('strtolower', $data['days'])));
            $data['month_days'] = null;
        } elseif ($frequency === 'monthly') {
            $monthDays = array_values(array_unique(array_map('intval', $data['month_days'])));
            sort($monthDays);
            $data['month_days'] = $monthDays;
            $data['days'] = null;
        } else {
            $data['days'] = null;
            $data['month_days'] = null;
        }

        if ($frequency === 'every_n_days') {
            $data['every_n_days'] = max(2, (int) $data['every_n_days']);
            $data['days'] = null;
            $data['month_days'] = null;
        } else {
            $data['every_n_days'] = null;
        }

        $data['weeks'] = null;
        $data['months'] = null;

        // A routine is scheduled either by a time-of-day period or by an exact
        // time window — never both.
        $data['time_period'] = $data['time_period'] ?? null;

        if ($data['time_period']) {
            $data['start_time'] = null;
            $data['end_time'] = null;
        } else {
            $data['start_time'] = $data['start_time'] ?? null;
            $data['end_time'] = $data['end_time'] ?? null;
        }

        // Behavior: build (do it) or avoid (must NOT do it). Count mode only
        // applies to avoid routines — it enables per-slip quantity input.
        $data['behavior_type'] = $data['behavior_type'] ?? Routine::BEHAVIOR_BUILD;
        $data['count_violations'] = $data['behavior_type'] === Routine::BEHAVIOR_AVOID
            && $request->boolean('count_violations');

        // Avoid routines never use metric tracking — slips are the metric.
        if ($data['behavior_type'] === Routine::BEHAVIOR_AVOID) {
            $data['tracking_mode'] = 'none';
            $data['value_kind'] = null;
            $data['value_unit'] = null;
            $data['value_label'] = null;
        }

        // Tracking: value mode needs a kind; sets mode needs no extra fields here.
        $data['tracking_mode'] = $data['tracking_mode'] ?? 'none';
        if ($data['tracking_mode'] === 'value' && empty($data['value_kind'])) {
            $data['value_kind'] = 'number';
        }
        if ($data['tracking_mode'] !== 'value') {
            $data['value_kind'] = null;
            $data['value_unit'] = null;
            $data['value_label'] = null;
        } else {
            $data['value_unit'] = $data['value_unit'] ?? null;
            $data['value_label'] = $data['value_label'] ?? null;
        }

        return $data;
    }

    private function authorizeRoutine(Routine $routine): void
    {
        abort_if($routine->user_id !== Auth::id(), 403);
    }

    /**
     * Sync persistent checklist "steps" from form rows:
     * rows with an id update, new rows create, missing rows delete.
     */
    private function syncItems(Routine $routine, $items): void
    {
        $keep = [];

        foreach ((array) $items as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $id = isset($row['id']) ? (int) $row['id'] : null;
            $targetSets = max(1, min(20, (int) ($row['target_sets'] ?? 1)));
            $unit = isset($row['unit']) && trim((string) $row['unit']) !== ''
                ? mb_substr(trim((string) $row['unit']), 0, 20)
                : null;

            // A step is scheduled either by a time-of-day period or an exact
            // time — never both (mirrors routines).
            $timePeriod = isset($row['time_period'])
                && in_array($row['time_period'], array_keys(config('routines.periods', [])), true)
                ? $row['time_period']
                : null;
            $scheduledTime = isset($row['scheduled_time']) && trim((string) $row['scheduled_time']) !== ''
                ? trim((string) $row['scheduled_time'])
                : null;

            if ($timePeriod) {
                $scheduledTime = null;
            } elseif ($scheduledTime) {
                $timePeriod = null;
                $scheduledTime = strlen($scheduledTime) === 5 ? $scheduledTime.':00' : $scheduledTime;
            } else {
                $scheduledTime = null;
            }

            $attributes = [
                'name' => $name,
                'sort_order' => $i,
                'target_sets' => $targetSets,
                'unit' => $unit,
                'time_period' => $timePeriod,
                'scheduled_time' => $scheduledTime,
            ];

            if ($id) {
                $item = $routine->checklistItems()->whereKey($id)->first();
                if ($item) {
                    $item->update($attributes);
                    $keep[] = $item->id;

                    continue;
                }
            }

            $item = $routine->checklistItems()->create(array_merge($attributes, [
                'user_id' => $routine->user_id,
            ]));
            $keep[] = $item->id;
        }

        $routine->checklistItems()->whereNotIn('id', $keep ?: [0])->delete();
    }
}
