<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\Project;
use App\Models\Routine;
use App\Models\RoutineCheckitemCompletion;
use App\Models\RoutineChecklistItem;
use App\Models\RoutineCompletion;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
                ->get(),
            byDate: true
        );

        // Single fetch for all routines + one batched completion/step fetch (no N+1).
        $routines = $this->fetchRoutines($user);
        $rangeStart = $selected->copy()->subYear()->startOfDay();
        $this->preloadRoutineCompletions($user->id, $routines, $rangeStart, $selected);
        $stepMap = $this->preloadStepCompletions($routines, $selected, $selected);
        $this->preloadRoutineLogs($user->id, $routines, $selected, $selected);

        $routinesData = $this->splitRoutines($routines, $selected);
        $this->decorateHabitMetricsBulk($routinesData['today'], $selected, $stepMap);

        // Sort today's routines by their current active step's schedule.
        $routinesData['today'] = $routinesData['today']
            ->sortBy(fn ($r) => $r->activeStepSortKey ?? $r->sortKey())
            ->values();

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
            'nextUp' => $this->buildNextUp($pending, $routinesData['today'], $selected),
            'quickProjects' => Project::where('user_id', $user->id)
                ->whereNotIn('status', ['completed', 'closed'])
                ->orderBy('name')
                ->get(['id', 'name']),
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
        $this->preloadRoutineLogs($user->id, $routines, $start, $end);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $dayTasks = $tasks->filter(
                fn ($t) => $t->due_date && Carbon::parse($t->due_date)->isSameDay($day)
            );
            $dayRoutines = $this->splitRoutines($routines, $day);
            // Clone per day: the same routine instance (e.g. a daily one like
            // Cobra Pose) occurs on several days, and decorateHabitMetricsBulk
            // mutates ringSteps/logValues/ring in place. Without cloning, every
            // day column would render the LAST decorated day's state (e.g. Thu
            // showing Fri's empty steps even though Thu step 1 was logged).
            $todayRoutines = $dayRoutines['today']->map(fn ($r) => clone $r);
            $this->decorateHabitMetricsBulk($todayRoutines, $day, $stepMap);
            $todayRoutines = $todayRoutines
                ->sortBy(fn ($r) => $r->activeStepSortKey ?? $r->sortKey())
                ->values();
            $days[] = [
                'date' => $day,
                'tasks' => $this->sortByPriority($dayTasks->values()),
                'routines' => $todayRoutines,
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

    /**
     * Reschedule a task straight from My Day: push to tomorrow, pull an
     * overdue task back to today, or drop its due date entirely.
     */
    public function postponeTask(Request $request, Task $task)
    {
        abort_if($task->user_id !== Auth::id(), 403);

        $action = $request->validate([
            'action' => ['required', Rule::in(['tomorrow', 'today', 'clear'])],
        ])['action'];

        $base = $task->due_date && $task->due_date->gt(today()) ? $task->due_date : today();

        $task->due_date = match ($action) {
            'tomorrow' => $base->copy()->addDay()->toDateString(),
            'today' => today()->toDateString(),
            'clear' => null,
        };
        $task->save();

        // Pulling into the day means the row must reappear in the task groups.
        if ($action === 'today') {
            $task->load('project:id,name');

            return response()->json([
                'ok' => true,
                'action' => $action,
                'due_date' => $task->due_date,
                'group' => $task->time_period ?: 'anytime',
                'row_html' => view('planner._task-row', [
                    'task' => $task,
                    'count' => true,
                    'postpone' => 'tomorrow',
                    'hideDue' => true,
                ])->render(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'action' => $action,
            'due_date' => $task->due_date,
        ]);
    }

    /**
     * Persist My Day drag & drop: manual order inside a period group and,
     * when the row moved across groups, the new time period.
     */
    public function reorderTasks(Request $request)
    {
        $periodKeys = array_keys(config('routines.periods', []));

        $data = $request->validate([
            'items' => 'required|array|min:1|max:200',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer|min:0|max:99999',
            'items.*.time_period' => ['nullable', Rule::in($periodKeys)],
        ]);

        $tasks = Task::where('user_id', Auth::id())
            ->whereIn('id', collect($data['items'])->pluck('id'))
            ->get()->keyBy('id');

        $updated = 0;
        foreach ($data['items'] as $item) {
            $task = $tasks->get($item['id']);
            if (! $task) {
                continue;
            }
            $task->sort_order = $item['sort_order'];
            if (array_key_exists('time_period', $item)) {
                $task->time_period = $item['time_period'];
            }
            $task->save();
            $updated++;
        }

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    /**
     * Toggle one checklist step of a task from My Day (ownership-checked).
     * The task auto-completes when its last step is ticked (and un-completes
     * when a step is unticked), mirroring routine step behavior.
     */
    public function toggleTaskCheckItem(Request $request, ChecklistItem $item)
    {
        abort_if($item->task->user_id !== Auth::id(), 403);

        $item->update(['completed' => ! $item->completed]);

        $task = $item->task;
        $items = $task->checklistItems()->get();
        $done = $items->where('completed', true)->count();
        $total = $items->count();
        $allDone = $total > 0 && $done === $total;

        if ($allDone && $task->status !== 'completed') {
            $task->status = 'completed';
            $task->completed_at = $task->completed_at ?? now();
            $task->save();
        } elseif (! $allDone && $task->status === 'completed') {
            $task->status = 'to_do';
            $task->completed_at = null;
            $task->save();
        }

        return response()->json([
            'ok' => true,
            'item_id' => $item->id,
            'completed' => (bool) $item->completed,
            'task_id' => $task->id,
            'task_completed' => $allDone,
            'steps_done' => $done,
            'steps_total' => $total,
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

        // Tracked sets-mode steps need a logged number: completing the tick
        // without one is reverted (the UI must ask for the number first).
        if ($itemCompleted && $routine->tracking_mode === Routine::TRACKING_SETS) {
            $hasLog = \App\Models\RoutineLog::where('user_id', Auth::id())
                ->where('routine_id', $routine->id)
                ->where('checklist_item_id', $item->id)
                ->where('completed_date', RoutineCheckitemCompletion::dateKey($date))
                ->exists();
            if (! $hasLog) {
                $item->toggleOn($date); // revert to uncompleted

                return response()->json(['ok' => false, 'error' => 'Log the number for this step first.'], 422);
            }
        }
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
     * Pick the single most important item for the "Next Up" card: the top
     * pending task (priority → time → due date), else the first undone routine.
     * Routines carry their steps so the card can point at the first unfinished
     * step instead of a bulk "Complete".
     */
    private function buildNextUp($pending, $todayRoutines, Carbon $date): ?array
    {
        $task = $pending->first();
        if ($task) {
            $task->loadMissing('checklistItems');
            $items = $task->checklistItems;
            $firstOpen = $items->first(fn ($i) => ! $i->completed);

            return [
                'type' => 'task',
                'task' => $task,
                'date' => $date,
                'stepsDone' => $items->where('completed', true)->count(),
                'stepsTotal' => $items->count(),
                'nextStep' => $firstOpen ? ['id' => $firstOpen->id, 'name' => $firstOpen->name, 'target_sets' => 1, 'sets' => [], 'completed' => false] : null,
            ];
        }

        $routine = $todayRoutines->first(fn ($r) => ! $r->completedOn($date));
        if ($routine) {
            $steps = collect($routine->ringSteps ?? []);
            $nextStep = $steps->first(fn ($s) => empty($s['completed']));

            return [
                'type' => 'routine',
                'routine' => $routine,
                'date' => $date,
                'stepsDone' => $steps->where('completed', true)->count(),
                'stepsTotal' => $steps->count(),
                'nextStep' => $nextStep,
            ];
        }

        return null;
    }

    /**
     * Lightweight endpoint that re-computes the Next Up card for a given date
     * so the UI can refresh it after a completion without a full reload.
     */
    public function nextUp(Request $request)
    {
        $user = Auth::user();
        $date = $this->parseDate($request->input('date'))->startOfDay();

        $pending = $this->sortByPriority(
            Task::where('user_id', $user->id)
                ->whereDate('due_date', $date->toDateString())
                ->where('status', '!=', 'completed')
                ->with('project:id,name')
                ->get()
        );

        $routines = $this->fetchRoutines($user);
        // Next Up only needs today's completion state. The full day view owns
        // the one-year habit metrics; refreshing this small card must stay fast.
        $this->preloadRoutineCompletions($user->id, $routines, $date, $date);
        $stepMap = $this->preloadStepCompletions($routines, $date, $date);
        $this->preloadRoutineLogs($user->id, $routines, $date, $date);
        $today = $routines
            ->filter(fn ($r) => $r->occursOn($date))
            ->values();
        $this->decorateNextUpRoutines($today, $date, $stepMap);
        $today = $today
            ->sortBy(fn ($r) => $r->activeStepSortKey ?? $r->sortKey())
            ->values();

        $nextUp = $this->buildNextUp($pending, $today, $date);

        return response()->json([
            'ok' => true,
            'html' => view('planner._next-up', ['nextUp' => $nextUp, 'date' => $date])->render(),
        ]);
    }

    /**
     * Decorate only the step/log state needed by the Next Up card.
     * Habit rings and one-year streak metrics are intentionally excluded from
     * this refresh path because they are not rendered by the card.
     */
    private function decorateNextUpRoutines($routines, Carbon $date, array $stepMap): void
    {
        $dayKey = $date->toDateString();

        foreach ($routines as $routine) {
            $steps = $routine->relationLoaded('checklistItems')
                ? $routine->checklistItems->sortBy(fn ($s) => $s->sortKey())->values()
                : collect();

            $routine->ringSteps = $steps->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'completed' => isset($stepMap[(int) $s->id][$dayKey]),
                'target_sets' => (int) ($s->target_sets ?? 1),
                'unit' => $s->unit,
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
            ])->values();

            $hasSchedule = fn ($s) => ! empty($s['period_label']) || ! empty($s['time_label']);
            $activeStep = $routine->ringSteps->first(fn ($s) => ! $s['completed'] && $hasSchedule($s));
            $activeStep ??= $routine->ringSteps->last(fn ($s) => $hasSchedule($s));
            $routine->activeStepSortKey = $activeStep['sort_key'] ?? null;
            $routine->activeStepSchedule = $activeStep ? [
                'period_label' => $activeStep['period_label'],
                'period_icon' => $activeStep['period_icon'],
                'period_color' => $activeStep['period_color'],
                'time_label' => $activeStep['time_label'],
            ] : null;
            $routine->logValues = $routine->isTracked() ? $routine->loggedValues($date) : [];
        }
    }

    /**
     * Quick-add a task from My Day without leaving the page: only a title is
     * required; the task lands on the selected day and may have no project.
     */
    public function quickAddTask(Request $request)
    {
        $user = Auth::user();
        $periodKeys = array_keys(config('routines.periods', []));

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $user->id)],
            'time_period' => 'nullable|in:'.implode(',', $periodKeys),
            'due_time' => 'nullable|date_format:H:i',
            'priority' => 'nullable|in:low,medium,high',
            'estimated_minutes' => 'nullable|integer|in:5,10,15,20,30,45,60,90,120',
            'date' => 'nullable|date',
        ]);

        $date = ! empty($data['date']) ? Carbon::parse($data['date'])->startOfDay() : now()->startOfDay();
        $projectId = $data['project_id'] ?? null;

        $task = Task::create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'title' => trim($data['title']),
            'due_date' => $date->toDateString(),
            'time_period' => $data['time_period'] ?? null,
            'due_time' => isset($data['due_time']) && $data['due_time'] !== '' ? $data['due_time'].':00' : null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'to_do',
            'estimated_hours' => isset($data['estimated_minutes'])
                ? round(((int) $data['estimated_minutes']) / 60, 2)
                : null,
        ]);
        $task->load('project:id,name');

        return response()->json([
            'ok' => true,
            'task' => ['id' => $task->id, 'title' => $task->title],
            'group' => $task->time_period ?: 'anytime',
            'html' => view('planner._task-row', ['task' => $task, 'count' => true, 'postpone' => 'tomorrow', 'hideDue' => true])->render(),
        ], 201);
    }

    /**
     * Quick-add a routine from My Day. Defaults to a daily routine so a title
     * alone is enough; weekly/monthly inherit sensible defaults.
     */
    public function quickAddRoutine(Request $request)
    {
        $user = Auth::user();
        $periodKeys = array_keys(config('routines.periods', []));

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'frequency' => 'nullable|in:daily,weekly,monthly,every_n_days',
            'days' => 'nullable|array',
            'days.*' => 'string|in:'.implode(',', Routine::WEEK_DAYS),
            'time_period' => 'nullable|in:'.implode(',', $periodKeys),
            'date' => 'nullable|date',
        ]);

        $date = ! empty($data['date']) ? Carbon::parse($data['date'])->startOfDay() : now()->startOfDay();
        $frequency = $data['frequency'] ?? 'daily';

        $attributes = [
            'title' => trim($data['title']),
            'frequency' => $frequency,
            'time_period' => $data['time_period'] ?? null,
            'tracking_mode' => Routine::TRACKING_NONE,
        ];

        if ($frequency === 'weekly') {
            $days = array_values(array_unique(array_map('strtolower', $data['days'] ?? [])));
            $attributes['days'] = $days ?: [strtolower($date->format('l'))];
            $attributes['month_days'] = null;
            $attributes['every_n_days'] = null;
        } elseif ($frequency === 'monthly') {
            $attributes['month_days'] = [$date->day];
            $attributes['days'] = null;
            $attributes['every_n_days'] = null;
        } elseif ($frequency === 'every_n_days') {
            $attributes['every_n_days'] = 2;
            $attributes['days'] = null;
            $attributes['month_days'] = null;
        } else {
            $attributes['days'] = null;
            $attributes['month_days'] = null;
            $attributes['every_n_days'] = null;
        }

        $routine = $user->routines()->create($attributes);

        return response()->json([
            'ok' => true,
            'routine' => ['id' => $routine->id, 'title' => $routine->title],
            'html' => view('planner._routine-row', [
                'routine' => $routine,
                'routineDate' => $date,
                'count' => true,
            ])->render(),
        ], 201);
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
        // Sorting is intentionally deferred until after decoration, because the
        // visible slot is driven by the first unfinished scheduled step.
        $today = $routines
            ->filter(fn ($r) => $r->occursOn($date))
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
                ? $routine->checklistItems->sortBy(fn ($s) => $s->sortKey())->values()
                : collect();

            $routine->ringSteps = $steps->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'completed' => isset($stepMap[(int) $s->id][$dayKey]),
                'target_sets' => (int) ($s->target_sets ?? 1),
                'unit' => $s->unit,
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
            ])->values();

            $hasSchedule = fn ($s) => ! empty($s['period_label']) || ! empty($s['time_label']);

            $activeStep = $routine->ringSteps->first(fn ($s) => ! $s['completed'] && $hasSchedule($s));
            if (! $activeStep) {
                $activeStep = $routine->ringSteps->last(fn ($s) => $hasSchedule($s));
            }

            if ($activeStep) {
                $routine->activeStepSchedule = [
                    'period_label' => $activeStep['period_label'],
                    'period_icon' => $activeStep['period_icon'],
                    'period_color' => $activeStep['period_color'],
                    'time_label' => $activeStep['time_label'],
                ];
                $routine->activeStepSortKey = $activeStep['sort_key'];
            } else {
                $routine->activeStepSchedule = null;
                $routine->activeStepSortKey = null;
            }

            $routine->logValues = $routine->isTracked() ? $routine->loggedValues($date) : [];
        }
    }

    /**
     * Preload routine metric logs for [from..to] in ONE query.
     */
    private function preloadRoutineLogs(int $userId, $routines, Carbon $from, Carbon $to): void
    {
        if ($routines->isEmpty()) {
            return;
        }

        $rows = \App\Models\RoutineLog::where('user_id', $userId)
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
     * Log a tracked value for a routine day.
     * Value mode: {date, value}. Sets mode: {date, item_id, sets: {set_no: value}}.
     * Sets mode auto-completes the routine when every target set is logged.
     */
    public function logRoutine(Request $request, Routine $routine)
    {
        abort_if($routine->user_id !== Auth::id(), 403);
        abort_if(! $routine->isTracked(), 422, 'Routine is not tracked.');

        $date = $this->parseDate($request->input('date'))->startOfDay();

        if ($routine->tracking_mode === Routine::TRACKING_VALUE) {
            // Time-kind routines store minutes since midnight — clamp to a day.
            $max = $routine->isTimeValue() ? 1439 : 1000000;
            $data = $request->validate(['value' => "required|numeric|min:0|max:{$max}"]);
            \App\Models\RoutineLog::logValue(Auth::id(), $routine->id, $date, $data['value']);

            // A value routine has exactly one input per day: logging it
            // completes the routine, same as filling every set in sets mode.
            $fresh = $routine->fresh();
            $routineCompleted = $fresh->completedOn($date);
            if (! $routineCompleted) {
                $fresh->toggleOn($date);
                $routineCompleted = true;
            }

            return response()->json([
                'ok' => true,
                'values' => $fresh->loggedValues($date),
                'routine_completed' => $routineCompleted,
                'streak' => $fresh->streakStats($date)['current'],
            ]);
        }

        // Sets mode.
        $data = $request->validate([
            'item_id' => 'required|integer',
            'sets' => 'required|array|min:1|max:20',
            'sets.*' => 'required|numeric|min:0|max:1000000',
        ]);
        $item = $routine->checklistItems()->whereKey($data['item_id'])->first();
        abort_if(! $item, 422, 'Invalid step.');

        foreach ($data['sets'] as $setNo => $value) {
            $setNo = max(1, min(20, (int) $setNo));
            \App\Models\RoutineLog::logValue(Auth::id(), $routine->id, $date, $value, $item->id, $setNo);
        }

        // A logged set auto-ticks its step (tick without a number is not allowed).
        $key = RoutineCheckitemCompletion::dateKey($date);
        $stepDone = RoutineCheckitemCompletion::where('checklist_item_id', $item->id)
            ->where('completed_date', $key)
            ->exists();
        if (! $stepDone) {
            RoutineCheckitemCompletion::create([
                'user_id' => Auth::id(),
                'checklist_item_id' => $item->id,
                'completed_date' => $key,
                'completed_at' => now(),
            ]);
            $stepDone = true;
        }

        $fresh = $routine->fresh();
        $routineCompleted = $fresh->completedOn($date);
        if (! $routineCompleted && $this->allSetsLogged($fresh, $date)) {
            $fresh->toggleOn($date);
            $routineCompleted = true;
        }

        $stepsDone = $fresh->checklistItems()->get()
            ->mapWithKeys(function ($it) use ($key) {
                $done = RoutineCheckitemCompletion::where('checklist_item_id', $it->id)
                    ->where('completed_date', $key)
                    ->exists();

                return [(int) $it->id => $done];
            })->all();

        return response()->json([
            'ok' => true,
            'routine_completed' => $routineCompleted,
            'steps_done' => $stepsDone,
            'values' => $fresh->loggedValues($date),
        ]);
    }

    /**
     * Every step has logs for set 1..target_sets on the given date.
     */
    private function allSetsLogged(Routine $routine, Carbon $date): bool
    {
        $items = $routine->checklistItems()->get();
        if ($items->isEmpty()) {
            return false;
        }
        $key = $date->toDateString();
        $logs = \App\Models\RoutineLog::where('user_id', $routine->user_id)
            ->where('routine_id', $routine->id)
            ->where('completed_date', $key)
            ->whereNotNull('checklist_item_id')
            ->get()
            ->groupBy('checklist_item_id');

        foreach ($items as $item) {
            $have = isset($logs[$item->id]) ? $logs[$item->id]->pluck('set_no')->map(fn ($n) => (int) $n)->all() : [];
            for ($s = 1; $s <= max(1, (int) $item->target_sets); $s++) {
                if (! in_array($s, $have, true)) {
                    return false;
                }
            }
        }

        return true;
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

    private function sortByPriority($tasks, bool $byDate = false): \Illuminate\Support\Collection
    {
        // Overdue lists care about dates first; the day plan uses the
        // scheduled order: period → manual drag order → priority → exact time.
        if ($byDate) {
            return $tasks->sortBy(fn ($t) => [
                $t->due_date ? Carbon::parse($t->due_date)->timestamp : PHP_INT_MAX,
                $this->periodOrder($t->time_period),
                (int) ($t->sort_order ?? 0),
                self::PRIORITY_ORDER[$t->priority] ?? 3,
            ])->values();
        }

        return $tasks->sortBy(fn ($t) => [
            $this->periodOrder($t->time_period),
            (int) ($t->sort_order ?? 0),
            self::PRIORITY_ORDER[$t->priority] ?? 3,
            $t->due_time ? substr((string) $t->due_time, 0, 5) : '99:99',
            $t->due_date ? Carbon::parse($t->due_date)->timestamp : PHP_INT_MAX,
        ])->values();
    }

    private function periodOrder(?string $period): int
    {
        if (! $period) {
            return 99;
        }

        return (int) config("routines.periods.{$period}.order", 99);
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
