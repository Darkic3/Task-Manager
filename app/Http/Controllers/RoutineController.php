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
        $routines = $user->routines()->orderBy('title')->get();

        // Batch: one completions query for [today-1y .. today]; ring math is pure PHP.
        $from = $today->copy()->subYear()->startOfDay();
        $rows = $routines->isNotEmpty()
            ? \App\Models\RoutineCompletion::where('user_id', $user->id)
                ->whereIn('routine_id', $routines->pluck('id'))
                ->whereBetween('completed_date', [$from->toDateString(), $today->toDateString()])
                ->get()
                ->groupBy('routine_id')
            : collect();

        foreach ($routines as $routine) {
            $routine->setRelation('completions', $rows->get($routine->id, collect()));
            $m = $this->habitMetricsFromLoaded($routine, $today);
            $routine->ringRate = $m['rate'];
            $routine->ringStreak = $m['streak'];
            $routine->ringLast7 = $m['last7'];
        }

        $weekly = $this->weeklyConsistencyFromLoaded($routines, $today);

        return view('routines.index', compact('routines', 'weekly'));
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

        return view('routines.stats', compact('routine', 'weeks', 'monthSpans', 'streak', 'adherence', 'tracker', 'valueStats', 'prevCycle'));
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

            if ($id) {
                $item = $routine->checklistItems()->whereKey($id)->first();
                if ($item) {
                    $item->update(['name' => $name, 'sort_order' => $i, 'target_sets' => $targetSets, 'unit' => $unit]);
                    $keep[] = $item->id;

                    continue;
                }
            }

            $item = $routine->checklistItems()->create([
                'user_id' => $routine->user_id,
                'name' => $name,
                'sort_order' => $i,
                'target_sets' => $targetSets,
                'unit' => $unit,
            ]);
            $keep[] = $item->id;
        }

        $routine->checklistItems()->whereNotIn('id', $keep ?: [0])->delete();
    }
}
