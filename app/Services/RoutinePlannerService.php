<?php

namespace App\Services;

use App\Models\Routine;
use App\Models\RoutineCheckitemCompletion;
use App\Models\RoutineCompletion;
use App\Models\RoutineLog;
use App\Models\RoutineViolation;
use Carbon\Carbon;

class RoutinePlannerService
{
    /**
     * Fetch all user routines with steps eager-loaded (single query + 1 for steps).
     */
    public function fetchRoutines($user)
    {
        return $user->routines()->with(['checklistItems' => function ($q) {
            $q->orderBy('sort_order')->orderBy('id');
        }])->get();
    }

    /**
     * Preload routine completions for [from..to] in ONE query and attach them
     * as the loaded `completions` relation, so completedOn()/completionRecord()
     * never query again. Returns date-key map per routine for ring math.
     */
    public function preloadRoutineCompletions(int $userId, $routines, Carbon $from, Carbon $to): array
    {
        if ($routines->isEmpty()) {
            return [];
        }

        $rows = RoutineCompletion::where('user_id', $userId)
            ->whereIn('routine_id', $routines->pluck('id'))
            ->whereBetween('completed_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('routine_id');

        $keysByRoutine = [];
        foreach ($routines as $routine) {
            $list = $rows->get($routine->id, collect());
            $routine->setRelation('completions', $list);
            $keysByRoutine[$routine->id] = $list
                ->map(fn ($c) => $c->completed_date instanceof Carbon
                    ? $c->completed_date->toDateString()
                    : Carbon::parse($c->completed_date)->toDateString())
                ->flip();
        }

        return $keysByRoutine;
    }

    /**
     * Preload step completions for [from..to] in ONE query, attach per-step
     * loaded relations, and return lookup [item_id][date] => true.
     */
    public function preloadStepCompletions($routines, Carbon $from, Carbon $to): array
    {
        $steps = $routines->flatMap(fn ($r) => $r->getRelation('checklistItems'));
        if ($steps->isEmpty()) {
            return [];
        }

        $rows = RoutineCheckitemCompletion::whereIn('checklist_item_id', $steps->pluck('id'))
            ->whereBetween('completed_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('checklist_item_id');

        $map = [];
        foreach ($steps as $step) {
            $list = $rows->get($step->id, collect());
            $step->setRelation('completions', $list);
            foreach ($list as $row) {
                $key = $row->completed_date instanceof Carbon
                    ? $row->completed_date->toDateString()
                    : Carbon::parse($row->completed_date)->toDateString();
                $map[(int) $step->id][$key] = true;
            }
        }

        return $map;
    }

    /**
     * Preload avoid-habit slips for [from..to] in ONE query: attach the loaded
     * `violations` relation per routine and return quantity lookups
     * [routine_id][date] and [item_id][date].
     */
    public function preloadRoutineViolations(int $userId, $routines, Carbon $from, Carbon $to): array
    {
        $map = ['routine' => [], 'step' => []];
        if ($routines->isEmpty()) {
            return $map;
        }

        $rows = RoutineViolation::where('user_id', $userId)
            ->whereIn('routine_id', $routines->pluck('id'))
            ->whereBetween('occurred_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('routine_id');

        foreach ($routines as $routine) {
            $list = $rows->get($routine->id, collect());
            $routine->setRelation('violations', $list);
            foreach ($list as $row) {
                $key = $row->occurred_date instanceof Carbon
                    ? $row->occurred_date->toDateString()
                    : Carbon::parse($row->occurred_date)->toDateString();
                $qty = (int) ($row->quantity ?? 1);
                $map['routine'][(int) $routine->id][$key]
                    = ($map['routine'][(int) $routine->id][$key] ?? 0) + $qty;
                if ($row->checklist_item_id !== null) {
                    $map['step'][(int) $row->checklist_item_id][$key]
                        = ($map['step'][(int) $row->checklist_item_id][$key] ?? 0) + $qty;
                }
            }
        }

        return $map;
    }

    /**
     * Preload routine metric logs for [from..to] in ONE query.
     */
    public function preloadRoutineLogs(int $userId, $routines, Carbon $from, Carbon $to): void
    {
        if ($routines->isEmpty()) {
            return;
        }

        $rows = RoutineLog::where('user_id', $userId)
            ->whereIn('routine_id', $routines->pluck('id'))
            ->whereBetween('completed_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('checklist_item_id')->orderBy('set_no')
            ->get()
            ->groupBy('routine_id');

        foreach ($routines as $routine) {
            $routine->setRelation('logs', $rows->get($routine->id, collect()));
        }
    }

    /**
     * Split routines into: occurring today, later-this-week, later-this-month,
     * plus completion counts for the selected date (pure PHP, no queries).
     */
    public function splitRoutines($routines, Carbon $date): array
    {
        $today = $routines
            ->filter(fn ($r) => $r->occursOn($date))
            ->values();

        $weekStart = $date->copy()->startOfWeek(Carbon::SATURDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $weekBucket = $routines
            ->filter(fn ($r) => ! $r->occursOn($date))
            ->filter(function ($r) use ($weekStart, $weekEnd) {
                if ($r->frequency === 'weekly' && empty($r->decodedDays())) {
                    return true;
                }
                for ($i = 0; $i < 7; $i++) {
                    $d = $weekStart->copy()->addDays($i);
                    if ($d->between($weekStart, $weekEnd) && $r->occursOn($d)) {
                        return true;
                    }
                }

                return false;
            })
            ->filter(fn ($r) => ! $r->occursOn($date))
            ->values();

        $monthBucket = $routines
            ->filter(fn ($r) => $r->frequency === 'monthly')
            ->filter(function ($r) use ($date) {
                if (empty($r->decodedMonthDays())) {
                    return true;
                }
                if ($r->occursOn($date)) {
                    return false;
                }
                $daysInMonth = $date->daysInMonth;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $candidate = $date->copy()->startOfMonth()->addDays($d - 1);
                    if ($r->occursOn($candidate)) {
                        return true;
                    }
                }

                return false;
            })
            ->filter(fn ($r) => ! $r->occursOn($date))
            ->values();

        $weekIds = $weekBucket->pluck('id')->all();
        $monthBucket = $monthBucket->filter(fn ($r) => ! in_array($r->id, $weekIds))->values();

        $done = $today->filter(fn ($r) => $r->isAvoid() ? ! $r->avoidDayViolated : $r->completedOn($date))->count();

        return [
            'today' => $today,
            'week' => $weekBucket,
            'month' => $monthBucket,
            'done' => $done,
            'total' => $today->count(),
        ];
    }

    /**
     * Attach habit-ring data from already-loaded relations (no queries):
     * adherence %, streak, last-7 squares + per-day step states.
     */
    public function decorateHabitMetricsBulk($routines, Carbon $date, array $stepMap, array $violationMap = []): void
    {
        foreach ($routines as $routine) {
            $isAvoid = $routine->isAvoid();
            $dayKey = $date->toDateString();

            if ($isAvoid) {
                $m = $routine->avoidMetrics($date);
                $routine->ringRate = $m['rate'];
                $routine->ringStreak = $m['current'];
                $routine->ringBest = $m['best'];
                $routine->ringLast7 = $m['last7'];
            } else {
                $completedSet = $routine->relationLoaded('completions')
                    ? $routine->completions
                        ->map(fn ($c) => $c->completed_date instanceof Carbon
                            ? $c->completed_date->toDateString()
                            : Carbon::parse($c->completed_date)->toDateString())
                        ->flip()
                    : [];

                $m = $this->habitMetricsFromSet($routine, $completedSet, $date);
                $routine->ringRate = $m['rate'];
                $routine->ringStreak = $m['streak'];
                $routine->ringLast7 = $m['last7'];
            }

            $steps = $routine->relationLoaded('checklistItems')
                ? $routine->checklistItems->sortBy(fn ($s) => $s->sortKey())->values()
                : collect();

            $routine->ringSteps = $steps->map(function ($s) use ($routine, $isAvoid, $stepMap, $violationMap, $dayKey) {
                $violatedQty = $isAvoid ? (int) ($violationMap['step'][(int) $s->id][$dayKey] ?? 0) : 0;

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'completed' => $isAvoid ? false : isset($stepMap[(int) $s->id][$dayKey]),
                    'violated' => $violatedQty > 0,
                    'violation_qty' => $violatedQty,
                    'target_sets' => (int) ($s->target_sets ?? 1),
                    'unit' => $s->unit,
                    'time_period' => $s->time_period,
                    'scheduled_time' => $s->scheduled_time,
                    'period_label' => $s->periodLabel(),
                    'period_icon' => $s->periodIcon(),
                    'period_color' => $s->periodColor(),
                    'time_label' => $s->scheduledTimeLabel(),
                    'sort_key' => $s->sortKey(),
                    'sets' => $routine->relationLoaded('logs')
                        ? $routine->logs
                            ->filter(fn ($l) => (int) $l->checklist_item_id === (int) $s->id
                                && (($l->completed_date instanceof Carbon)
                                    ? $l->completed_date->toDateString()
                                    : substr((string) $l->completed_date, 0, 10)) === $dayKey)
                            ->mapWithKeys(fn ($l) => [(int) $l->set_no => (float) $l->value])
                            ->all()
                        : [],
                ];
            })->values();

            $hasSchedule = fn ($s) => ! empty($s['period_label']) || ! empty($s['time_label']);
            $isSettled = $isAvoid
                ? fn ($s) => ! empty($s['violated'])
                : fn ($s) => ! empty($s['completed']);

            $activeStep = $routine->ringSteps->first(fn ($s) => ! $isSettled($s) && $hasSchedule($s));
            if (! $activeStep) {
                $activeStep = $routine->ringSteps->last(fn ($s) => $hasSchedule($s));
            }

            if ($activeStep) {
                $routine->activeStepSchedule = [
                    'period_label' => $activeStep['period_label'],
                    'period_icon' => $activeStep['period_icon'],
                    'period_color' => $activeStep['period_color'],
                    'time_label' => $activeStep['time_label'],
                    'time_period' => $activeStep['time_period'] ?? null,
                    'scheduled_time' => $activeStep['scheduled_time'] ?? null,
                    'step_id' => $activeStep['id'] ?? null,
                    'sort_order' => (int) ($steps->firstWhere('id', $activeStep['id'])?->sort_order ?? 0),
                ];
                $routine->activeStepSortKey = $activeStep['sort_key'];
            } else {
                $routine->activeStepSchedule = null;
                $routine->activeStepSortKey = null;
            }

            $routine->avoidDayQty = $isAvoid
                ? (int) ($violationMap['routine'][(int) $routine->id][$dayKey] ?? 0)
                : 0;
            $routine->avoidDayViolated = $routine->avoidDayQty > 0;

            $routine->logValues = $routine->isTracked() ? $routine->loggedValues($date) : [];
        }
    }

    /**
     * Pure-PHP version of Routine::habitMetrics() using a preloaded completed set.
     */
    public function habitMetricsFromSet(Routine $routine, $completedSet, Carbon $date): array
    {
        $date = $date->copy()->startOfDay();
        $todayKey = $date->toDateString();

        // 30-day adherence (excluding today when still open).
        $from30 = $date->copy()->subDays(29);
        $occ30 = $routine->occurrenceDates($from30, $date);
        if ($occ30 && end($occ30) === $todayKey && ! isset($completedSet[$todayKey])) {
            array_pop($occ30);
        }
        $done30 = count(array_intersect($occ30, array_keys(is_array($completedSet) ? $completedSet : $completedSet->toArray())));
        $rate = count($occ30) ? (int) round($done30 / count($occ30) * 100) : 0;

        // Current streak over the trailing year.
        $fromYear = $date->copy()->subYear();
        $occYear = $routine->occurrenceDates($fromYear, $date);
        if ($occYear && end($occYear) === $todayKey && ! isset($completedSet[$todayKey])) {
            array_pop($occYear);
        }
        $streak = 0;
        for ($i = count($occYear) - 1; $i >= 0; $i--) {
            if (! isset($completedSet[$occYear[$i]])) {
                break;
            }
            $streak++;
        }

        // Last-7 squares.
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
                $state = isset($completedSet[$key]) ? 'done' : 'missed';
            }

            $last7[] = ['date' => $key, 'state' => $state];
        }

        return ['rate' => $rate, 'streak' => $streak, 'last7' => $last7];
    }

    /**
     * Get fully decorated routines for a single day.
     */
    public function getDayRoutinesData($user, Carbon $date): array
    {
        $selected = $date->copy()->startOfDay();
        $routines = $this->fetchRoutines($user);
        $rangeStart = $selected->copy()->subYear()->startOfDay();

        $this->preloadRoutineCompletions($user->id, $routines, $rangeStart, $selected);
        $stepMap = $this->preloadStepCompletions($routines, $selected, $selected);
        $this->preloadRoutineLogs($user->id, $routines, $selected, $selected);
        $violationMap = $this->preloadRoutineViolations($user->id, $routines, $rangeStart, $selected);

        $routinesData = $this->splitRoutines($routines, $selected);
        $this->decorateHabitMetricsBulk($routinesData['today'], $selected, $stepMap, $violationMap);

        $routinesData['today'] = $routinesData['today']
            ->sortBy(fn ($r) => $r->sortKey())
            ->values();

        return $routinesData;
    }
}
