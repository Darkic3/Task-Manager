<?php

namespace App\Http\Controllers;

use App\Models\Routine;
use App\Models\RoutineCheckitemCompletion;
use App\Models\RoutineChecklistItem;
use App\Models\RoutineCompletion;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlannerController extends Controller
{
    private const PRIORITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

    public function index(Request $request)
    {
        $user = Auth::user();
        $view = $request->input('view') === 'week' ? 'week' : 'day';
        $date = $this->parseDate($request->input('date'));

        return $view === 'week'
            ? $this->weekView($user, $date)
            : $this->dayView($user, $date);
    }

    private function dayView($user, Carbon $date)
    {
        $selected = $date->copy()->startOfDay();

        $tasks = Task::where('user_id', $user->id)
            ->whereDate('due_date', $selected->toDateString())
            ->with('project:id,name')
            ->get();

        $pending = $this->sortByPriority($tasks->where('status', '!=', 'completed')->values());
        $done = $tasks->where('status', 'completed')->values();

        $overdue = $this->sortByPriority(
            Task::where('user_id', $user->id)
                ->where('status', '!=', 'completed')
                ->whereDate('due_date', '<', $selected->toDateString())
                ->with('project:id,name')
                ->get()
        );

        // Single fetch for all routines + one batched completion/step fetch (no N+1).
        $routines = $this->fetchRoutines($user);
        $rangeStart = $selected->copy()->subYear()->startOfDay();
        $this->preloadRoutineCompletions($user->id, $routines, $rangeStart, $selected);
        $stepMap = $this->preloadStepCompletions($routines, $selected, $selected);

        $routinesData = $this->splitRoutines($routines, $selected);
        $this->decorateHabitMetricsBulk($routinesData['today'], $selected, $stepMap);

        return view('planner.index', [
            'view' => 'day',
            'date' => $selected,
            'isToday' => $selected->isSameDay(now()),
            'pending' => $pending,
            'done' => $done,
            'overdue' => $overdue,
            'routines' => $routinesData['today'],
            'bucketWeek' => $routinesData['week'],
            'bucketMonth' => $routinesData['month'],
            'routineDone' => $routinesData['done'],
            'routineTotal' => $routinesData['total'],
        ]);
    }

    private function weekView($user, Carbon $date)
    {
        $start = $date->copy()->startOfWeek(Carbon::SATURDAY)->startOfDay();
        $end = $start->copy()->addDays(6);

        $tasks = Task::where('user_id', $user->id)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->with('project:id,name')
            ->get();

        // Fetch routines once for the whole week; ring stays enabled via bulk maps.
        $routines = $this->fetchRoutines($user);
        $rangeStart = $start->copy()->subYear()->startOfDay();
        $this->preloadRoutineCompletions($user->id, $routines, $rangeStart, $end);
        $stepMap = $this->preloadStepCompletions($routines, $start, $end);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $dayTasks = $tasks->filter(
                fn ($t) => $t->due_date && Carbon::parse($t->due_date)->isSameDay($day)
            );
            $dayRoutines = $this->splitRoutines($routines, $day);
            $this->decorateHabitMetricsBulk($dayRoutines['today'], $day, $stepMap);
            $days[] = [
                'date' => $day,
                'tasks' => $this->sortByPriority($dayTasks->values()),
                'routines' => $dayRoutines['today'],
                'routineDone' => $dayRoutines['done'],
                'routineTotal' => $dayRoutines['total'],
            ];
        }

        return view('planner.index', [
            'view' => 'week',
            'date' => $date->copy(),
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'isToday' => now()->between($start->copy()->startOfDay(), $end->copy()->endOfDay()),
        ]);
    }

    public function toggleTask(Task $task)
    {
        abort_if($task->user_id !== Auth::id(), 403);

        $completed = $task->status !== 'completed';
        $task->status = $completed ? 'completed' : 'to_do';
        $task->completed_at = $completed ? now() : null;
        $task->save();

        return response()->json([
            'ok' => true,
            'status' => $task->status,
            'completed' => $completed,
            'completed_at' => $task->completed_at?->toIso8601String(),
        ]);
    }

    public function toggleRoutine(Request $request, Routine $routine)
    {
        abort_if($routine->user_id !== Auth::id(), 403);

        $date = $this->parseDate($request->input('date'));
        $completed = $routine->toggleOn($date);

        /* Keep per-step completions in sync with the whole-routine toggle (batched). */
        $items = $routine->checklistItems()->orderBy('sort_order')->orderBy('id')->get();
        if ($items->isNotEmpty()) {
            $key = RoutineCheckitemCompletion::dateKey($date);
            $doneIds = RoutineCheckitemCompletion::whereIn('checklist_item_id', $items->pluck('id'))
                ->where('completed_date', $key)
                ->pluck('checklist_item_id')
                ->map(fn ($id) => (int) $id)
                ->flip();

            foreach ($items as $item) {
                $isDone = isset($doneIds[(int) $item->id]);
                if ($isDone !== $completed) {
                    $item->toggleOn($date);
                }
            }
        }

        return response()->json([
            'ok' => true,
            'completed' => $completed,
            'date' => $date->toDateString(),
            /* accurate streak so the UI never inflates counts client-side */
            'streak' => $routine->fresh()->streakStats($date)['current'],
            'items' => $items->map(fn ($it) => ['id' => $it->id, 'completed' => $completed])->values(),
        ]);
    }

    /**
     * Toggle a single step of a routine; when every step is done the routine
     * itself completes for that day (and un-completes if a step is undone).
     */
    public function toggleCheckItem(Request $request, RoutineChecklistItem $item)
    {
        abort_if($item->user_id !== Auth::id(), 403);

        $date = $this->parseDate($request->input('date'));
        $itemCompleted = $item->toggleOn($date);

        $routine = $item->routine;
        $items = $routine->checklistItems()->orderBy('sort_order')->orderBy('id')->get();
        $key = RoutineCheckitemCompletion::dateKey($date);
        $doneIds = $items->isNotEmpty()
            ? RoutineCheckitemCompletion::whereIn('checklist_item_id', $items->pluck('id'))
                ->where('completed_date', $key)
                ->pluck('checklist_item_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
            : collect();
        $done = $items->filter(fn ($it) => isset($doneIds[(int) $it->id]))->count();
        $total = $items->count();
        $allDone = $total > 0 && $done === $total;

        $routineCompleted = $routine->completedOn($date);
        if ($allDone && ! $routineCompleted) {
            $routine->toggleOn($date);
            $routineCompleted = true;
        } elseif (! $allDone && $routineCompleted) {
            $routine->toggleOn($date);
            $routineCompleted = false;
        }

        return response()->json([
            'ok' => true,
            'item_id' => $item->id,
            'completed' => $itemCompleted,
            'routine_id' => $routine->id,
            'routine_completed' => $routineCompleted,
            'steps_done' => $done,
            'steps_total' => $total,
            'streak' => $routine->fresh()->streakStats($date)['current'],
        ]);
    }

    /**
     * Fetch all user routines with steps eager-loaded (single query + 1 for steps).
     */
    private function fetchRoutines($user)
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
    private function preloadRoutineCompletions(int $userId, $routines, Carbon $from, Carbon $to): array
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
    private function preloadStepCompletions($routines, Carbon $from, Carbon $to): array
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
     * Split routines into: occurring today, later-this-week, later-this-month,
     * plus completion counts for the selected date (pure PHP, no queries).
     */
    private function splitRoutines($routines, Carbon $date): array
    {
        $today = $routines
            ->filter(fn ($r) => $r->occursOn($date))
            ->sortBy(fn ($r) => $r->sortKey())
            ->values();

        // Buckets: routines NOT occurring today but still relevant this week / this month
        $weekStart = $date->copy()->startOfWeek(Carbon::SATURDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $weekBucket = $routines
            ->filter(fn ($r) => ! $r->occursOn($date))
            ->filter(function ($r) use ($weekStart, $weekEnd) {
                if ($r->frequency === 'weekly' && empty($r->decodedDays())) {
                    return true; // weekly with no days selected → "this week" bucket
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
                    return true; // monthly with no days selected → "this month" bucket
                }
                // occurs later this month but not today
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

        // Avoid duplicates in both buckets
        $weekIds = $weekBucket->pluck('id')->all();
        $monthBucket = $monthBucket->filter(fn ($r) => ! in_array($r->id, $weekIds))->values();

        $done = $today->filter(fn ($r) => $r->completedOn($date))->count();

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
    private function decorateHabitMetricsBulk($routines, Carbon $date, array $stepMap): void
    {
        foreach ($routines as $routine) {
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

            $dayKey = $date->toDateString();
            $steps = $routine->relationLoaded('checklistItems')
                ? $routine->checklistItems
                : collect();

            $routine->ringSteps = $steps->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'completed' => isset($stepMap[(int) $s->id][$dayKey]),
            ])->values();
        }
    }

    /**
     * Pure-PHP version of Routine::habitMetrics() using a preloaded completed set.
     */
    private function habitMetricsFromSet(Routine $routine, $completedSet, Carbon $date): array
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

    private function sortByPriority($tasks)
    {
        return $tasks->sortBy(fn ($t) => [
            self::PRIORITY_ORDER[$t->priority] ?? 3,
            $t->due_date ? Carbon::parse($t->due_date)->timestamp : PHP_INT_MAX,
        ])->values();
    }

    private function parseDate(?string $value): Carbon
    {
        if ($value) {
            try {
                return Carbon::parse($value);
            } catch (\Exception $e) {
                // fall through to now()
            }
        }

        return now();
    }
}
