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
        $violationMap = $this->preloadRoutineViolations($user->id, $routines, $rangeStart, $selected);
        $this->preloadRoutineNotes($user->id, $routines, $selected, $selected);

        $routinesData = $this->splitRoutines($routines, $selected);
        $this->decorateHabitMetricsBulk($routinesData['today'], $selected, $stepMap, $violationMap);

        // Sort today's routines by their current active step's schedule.
        $routinesData['today'] = $routinesData['today']
            ->sortBy(fn ($r) => $r->sortKey())
            ->values();

        $totalEstimatedHours = $pending->sum(fn ($t) => (float) ($t->estimated_hours ?? 0));
        $dailyCapacityHours = 6.0;
        $capacityPercentage = min(round(($totalEstimatedHours / max($dailyCapacityHours, 0.1)) * 100), 150);

        $todayWeekday = strtolower($selected->format('l'));
        $activeWorkoutPlan = $user->workoutPlans()->where('status', 'active')->first()
            ?? $user->workoutPlans()->latest()->first();

        $todayWorkoutDay = null;
        $todayWorkoutSession = null;
        if ($activeWorkoutPlan) {
            $todayWorkoutDay = $activeWorkoutPlan->days()
                ->where('weekday', $todayWeekday)
                ->with(['exercises.exercise'])
                ->first();

            if ($todayWorkoutDay) {
                $todayWorkoutSession = \App\Models\WorkoutSession::where('user_id', $user->id)
                    ->where('workout_day_id', $todayWorkoutDay->id)
                    ->whereDate('workout_date', $selected->toDateString())
                    ->first();
            }
        }

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
            'totalEstimatedHours' => $totalEstimatedHours,
            'dailyCapacityHours' => $dailyCapacityHours,
            'capacityPercentage' => $capacityPercentage,
            'activeWorkoutPlan' => $activeWorkoutPlan,
            'todayWorkoutDay' => $todayWorkoutDay,
            'todayWorkoutSession' => $todayWorkoutSession,
            'nextUp' => $this->buildNextUp($pending, $routinesData['today'], $selected),
            'quickProjects' => Project::where('user_id', $user->id)
                ->whereNotIn('status', ['completed', 'closed'])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * AI Copilot: Optimize today's schedule across Morning, Afternoon, and Evening slots.
     */
    public function aiOptimize(Request $request)
    {
        $user = Auth::user();
        $tasks = Task::where('user_id', $user->id)
            ->where('status', '!=', 'completed')
            ->where(function ($q) {
                $q->whereDate('due_date', today())
                  ->orWhereNull('due_date');
            })
            ->get();

        if ($tasks->isEmpty()) {
            return response()->json([
                'ok' => true,
                'message' => 'No open tasks found for today to optimize. Add some tasks first!',
                'count' => 0,
                'tasks' => []
            ]);
        }

        $updates = [];
        foreach ($tasks as $task) {
            $task->due_date = today()->toDateString();
            if ($task->priority === 'high' || ($task->estimated_hours ?? 0) >= 1.5) {
                $task->time_period = 'morning';
            } elseif ($task->priority === 'medium') {
                $task->time_period = 'afternoon';
            } else {
                $task->time_period = 'evening';
            }
            $task->save();

            $updates[] = [
                'id' => $task->id,
                'title' => $task->title,
                'priority' => $task->priority,
                'time_period' => $task->time_period
            ];
        }

        return response()->json([
            'ok' => true,
            'message' => 'Lina successfully organized your tasks for peak focus!',
            'briefing' => 'Prioritized ' . count($updates) . ' tasks: High-impact deep work assigned to Morning, execution to Afternoon, and light admin tasks to Evening.',
            'count' => count($updates),
            'tasks' => $updates
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
        $violationMap = $this->preloadRoutineViolations($user->id, $routines, $rangeStart, $end);
        $this->preloadRoutineNotes($user->id, $routines, $start, $end);

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
            $this->decorateHabitMetricsBulk($todayRoutines, $day, $stepMap, $violationMap);
            $todayRoutines = $todayRoutines
                ->sortBy(fn ($r) => $r->sortKey())
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
        // Completing closes the loop: any earlier fail marker is cleared.
        if ($completed) {
            $task->failed_at = null;
            $task->fail_note = null;
        }
        $task->save();

        return response()->json([
            'ok' => true,
            'status' => $task->status,
            'completed' => $completed,
            'completed_at' => $task->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * Mark a task as failed ("won't do it") with an optional note and an
     * optional reschedule date chosen on the spot. The task renders red with
     * its note until it is undone or completed.
     */
    public function failTask(Request $request, Task $task)
    {
        abort_if($task->user_id !== Auth::id(), 403);
        abort_if($task->status === 'completed', 422, 'Completed tasks cannot be marked as failed.');

        $data = $request->validate([
            'note' => 'nullable|string|max:2000',
            'reschedule_date' => 'nullable|date',
        ]);

        $task->fail($data['note'] ?? null, $data['reschedule_date'] ?? null);

        return response()->json([
            'ok' => true,
            'failed' => true,
            'failed_at' => $task->failed_at?->toIso8601String(),
            'fail_note' => $task->fail_note,
            'due_date' => $task->due_date?->toDateString(),
            'rescheduled' => ! empty($data['reschedule_date']),
        ]);
    }

    /**
     * Undo a task fail marker (note is cleared too).
     */
    public function unfailTask(Task $task)
    {
        abort_if($task->user_id !== Auth::id(), 403);

        $task->unfail();

        return response()->json([
            'ok' => true,
            'failed' => false,
            'due_date' => $task->due_date?->toDateString(),
        ]);
    }

    /**
     * Reschedule a task straight from My Day: push to tomorrow, pull an
     * overdue task back to today, or drop its due date entirely.
     */
    public function postponeTask(Request $request, Task $task)
    {
        abort_if($task->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'action' => ['required', Rule::in(['tomorrow', 'today', 'clear', 'restore'])],
            'due_date' => ['nullable', 'date'],
        ]);
        $action = $data['action'];

        $previous = $task->due_date?->toDateString();
        $base = $task->due_date && $task->due_date->gt(today()) ? $task->due_date : today();

        $task->due_date = match ($action) {
            'tomorrow' => $base->copy()->addDay()->toDateString(),
            'today' => today()->toDateString(),
            'clear' => null,
            'restore' => ($data['due_date'] ?? null) ? Carbon::parse($data['due_date'])->toDateString() : null,
        };
        $task->save();

        // Pulling into the day means the row must reappear in the task groups.
        if ($action === 'today') {
            $task->load('project:id,name');

            return response()->json([
                'ok' => true,
                'action' => $action,
                'due_date' => $task->due_date,
                'previous_due_date' => $previous,
                'group' => $task->time_period ?: 'anytime',
                'row_html' => view('planner._task-row', [
                    'task' => $task,
                    'count' => true,
                    'postpone' => 'tomorrow',
                    'hideDue' => true,
                    'draggable' => true,
                ])->render(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'action' => $action,
            'due_date' => $task->due_date,
            'previous_due_date' => $previous,
        ]);
    }

    /**
     * Inline title edit from My Day (double-click).
     */
    public function renameTask(Request $request, Task $task)
    {
        abort_if($task->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $task->update(['title' => trim($data['title'])]);

        return response()->json(['ok' => true, 'title' => $task->title]);
    }

    /**
     * Quick estimate update from task row quick actions.
     */
    public function updateEstimate(Request $request, Task $task)
    {
        abort_if($task->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'estimated_hours' => ['required', 'numeric', 'min:0', 'max:999'],
        ]);

        $task->update(['estimated_hours' => (float) $data['estimated_hours']]);

        return response()->json([
            'ok' => true,
            'estimated_hours' => $task->estimated_hours,
            'estimated_label' => $task->estimatedLabel(),
        ]);
    }

    /**
     * One click: move every overdue open task to tomorrow.
     */
    public function postponeAllOverdue()
    {
        $moved = Task::where('user_id', Auth::id())
            ->where('status', '!=', 'completed')
            ->whereDate('due_date', '<', today())
            ->update(['due_date' => today()->addDay()->toDateString()]);

        return response()->json(['ok' => true, 'moved' => $moved]);
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
        // Avoid habits have no check: a tick would mean doing the forbidden
        // thing. Slips are logged through the slip endpoint instead.
        abort_if($routine->isAvoid(), 422, 'Avoid habits cannot be checked off.');

        $date = $this->parseDate($request->input('date'));

        // A failed day reopens through ✗ first — a bare tick can never
        // silently convert it back to done.
        if (! $routine->completedOn($date)) {
            $this->abortIfDayClosed($routine, $date);

            // Tracked (input-based, not checkbox) routines can only complete
            // through logged numbers — a bare tick with no logged value must
            // never mark them done. Otherwise the day stays open (pending).
            // The only other way out is the explicit ✗ "did not do it".
            if ($routine->isTracked()) {
                $this->abortUnlessTrackedInputsLogged($routine, $date);
            }
        }

        $completed = $routine->toggleOn($date);

        /* Keep per-step completions in sync with the whole-routine toggle (batched). */
        $items = $routine->checklistItems()->orderBy('sort_order')->orderBy('id')->get();
        if ($items->isNotEmpty()) {
            $key = RoutineCheckitemCompletion::dateKey($date);
            $doneIds = RoutineCheckitemCompletion::whereIn('checklist_item_id', $items->pluck('id'))
                ->where('completed_date', $key)
                ->where('status', RoutineCheckitemCompletion::STATUS_DONE)
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
     * Refuse a whole-routine tick when the day is already closed: either an
     * explicit skip row exists, or — for step routines — every step is
     * already resolved (done or skipped) so there is nothing to bulk-toggle.
     * Reopening happens step by step (unskip / log), never by re-ticking.
     */
    private function abortIfDayClosed(Routine $routine, Carbon $date): void
    {
        $key = $date->toDateString();

        $skipped = $routine->completions()
            ->where('user_id', $routine->user_id)
            ->where('completed_date', $key)
            ->where('status', RoutineCompletion::STATUS_SKIPPED)
            ->exists();
        abort_if($skipped, 422, 'Reopen the day first (undo the skip).');

        $items = $routine->checklistItems()->get();
        if ($items->isNotEmpty() && $items->every(fn ($it) => $it->completedOn($date) || $it->skippedOn($date))) {
            abort(422, 'All steps are already resolved.');
        }
    }

    /**
     * Guard for input-based routines: completing the whole routine with a
     * bare tick is refused unless the numbers behind it were actually logged.
     * Value mode needs its daily value; sets mode needs every step to carry
     * at least one logged set (mirrors the per-step tick guard).
     */
    private function abortUnlessTrackedInputsLogged(Routine $routine, Carbon $date): void
    {
        $key = $date->toDateString();

        if ($routine->tracking_mode === Routine::TRACKING_VALUE) {
            $hasValue = \App\Models\RoutineLog::where('user_id', $routine->user_id)
                ->where('routine_id', $routine->id)
                ->where('completed_date', $key)
                ->whereNull('checklist_item_id')
                ->exists();
            abort_if(! $hasValue, 422, 'Log a value for this routine first.');

            return;
        }

        if ($routine->tracking_mode === Routine::TRACKING_SETS) {
            $items = $routine->checklistItems()->get();
            // A sets routine with no steps has no inputs — checkbox still applies.
            if ($items->isEmpty()) {
                return;
            }
            $loggedItemIds = \App\Models\RoutineLog::where('user_id', $routine->user_id)
                ->where('routine_id', $routine->id)
                ->where('completed_date', $key)
                ->whereNotNull('checklist_item_id')
                ->distinct()
                ->pluck('checklist_item_id')
                ->map(fn ($id) => (int) $id)
                ->flip();
            $missing = $items->reject(fn ($it) => isset($loggedItemIds[(int) $it->id]))->count();
            abort_if($missing > 0, 422, 'Log the numbers for every step first.');
        }
    }

    /**
     * Mark a routine as "did not do it" for a date (or undo it). Lets a
     * missed day be recorded instead of silently staying open; skipped days
     * count as missed in streaks/rings but carry no completion timestamp.
     * Undoing an AUTO-closed day (all steps were skipped) also reopens the
     * step skips — otherwise the day would look untouched yet stay failed.
     */
    public function skipRoutine(Request $request, Routine $routine)
    {
        abort_if($routine->user_id !== Auth::id(), 403);
        // Avoid habits already track failure via slips; a ✗ would double-count.
        abort_if($routine->isAvoid(), 422, 'Avoid habits record slips instead of skips.');

        $data = $request->validate([
            'date' => 'nullable|date',
            'reason' => 'nullable|in:'.implode(',', array_keys(RoutineCompletion::SKIP_REASONS)),
        ]);

        $date = $this->parseDate($data['date'] ?? null);
        $key = $date->toDateString();

        $priorAuto = $routine->completions()
            ->where('user_id', $routine->user_id)
            ->where('completed_date', $key)
            ->where('status', RoutineCompletion::STATUS_SKIPPED)
            ->where('skip_reason', RoutineCompletion::AUTO_ALL_STEPS)
            ->exists();

        $skipped = $routine->skipOn($date, $data['reason'] ?? null);

        $stepsReopened = false;
        if (! $skipped && $priorAuto) {
            $itemIds = $routine->checklistItems()->pluck('id');
            if ($itemIds->isNotEmpty()) {
                $stepsReopened = (bool) RoutineCheckitemCompletion::whereIn('checklist_item_id', $itemIds)
                    ->where('completed_date', $key)
                    ->where('status', RoutineCheckitemCompletion::STATUS_SKIPPED)
                    ->delete();
            }
        }

        return response()->json([
            'ok' => true,
            'skipped' => $skipped,
            'date' => $key,
            'routine_skipped' => $skipped,
            'steps_reopened' => $stepsReopened,
            'streak' => $routine->fresh()->streakStats($date)['current'],
        ]);
    }

    /**
     * Toggle a single step of a routine; when every step is done the routine
     * itself completes for that day (and un-completes if a step is undone).
     * Skipped steps never count as done; completing a step reopens a
     * routine-level skip for that day.
     */
    public function toggleCheckItem(Request $request, RoutineChecklistItem $item)
    {
        abort_if($item->user_id !== Auth::id(), 403);
        abort_if($item->routine->isAvoid(), 422, 'Avoid-habit steps cannot be checked off.');

        $date = $this->parseDate($request->input('date'));
        $itemCompleted = $item->toggleOn($date);

        $routine = $item->routine;

        // A fresh tick reopens the day when the whole routine was marked "not done".
        if ($itemCompleted) {
            $routine->completions()
                ->where('user_id', $routine->user_id)
                ->where('completed_date', RoutineCheckitemCompletion::dateKey($date))
                ->where('status', RoutineCompletion::STATUS_SKIPPED)
                ->delete();
        }

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
                ->where('status', RoutineCheckitemCompletion::STATUS_DONE)
                ->pluck('checklist_item_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
            : collect();
        $done = $items->filter(fn ($it) => isset($doneIds[(int) $it->id]))->count();
        $total = $items->count();
        $allDone = $total > 0 && $done === $total;

        $routineCompleted = $routine->completedOn($date);
        if ($allDone && ! $routineCompleted) {
            // Value-mode routines complete through their logged value, never
            // through bare step ticks alone.
            if ($routine->tracking_mode === Routine::TRACKING_VALUE) {
                $hasValue = \App\Models\RoutineLog::where('user_id', $routine->user_id)
                    ->where('routine_id', $routine->id)
                    ->where('completed_date', $key)
                    ->whereNull('checklist_item_id')
                    ->exists();
                if ($hasValue) {
                    $routine->toggleOn($date);
                    $routineCompleted = true;
                }
            } else {
                $routine->toggleOn($date);
                $routineCompleted = true;
            }
        } elseif (! $allDone && $routineCompleted) {
            $routine->toggleOn($date);
            $routineCompleted = false;
        }

        // Fresh reads: preloaded relations are stale after the writes above.
        $routineSkippedNow = $routine->completions()
            ->where('user_id', $routine->user_id)
            ->where('completed_date', $key)
            ->where('status', RoutineCompletion::STATUS_SKIPPED)
            ->exists();
        [$stepsSkippedNow] = $this->stepSkipCounts($routine, $date);

        return response()->json([
            'ok' => true,
            'item_id' => $item->id,
            'completed' => $itemCompleted,
            'routine_id' => $routine->id,
            'routine_completed' => $routineCompleted,
            'routine_skipped' => $routineSkippedNow,
            'steps_done' => $done,
            'steps_total' => $total,
            'steps_skipped' => $stepsSkippedNow,
            'streak' => $routine->fresh()->streakStats($date)['current'],
        ]);
    }

    /**
     * Mark a single routine step as "did not do it" (or undo it). A skipped
     * step is settled for guidance (Next Up moves past it) but never counts
     * as done, so it can never auto-complete the routine by itself.
     * When the last open step is skipped, the whole routine closes as
     * skipped for the day (auto marker); reopening any step reopens it.
     */
    public function skipCheckItem(Request $request, RoutineChecklistItem $item)
    {
        abort_if($item->user_id !== Auth::id(), 403);
        abort_if($item->routine->isAvoid(), 422, 'Avoid-habit steps record slips instead of skips.');

        $data = $request->validate([
            'date' => 'nullable|date',
            'reason' => 'nullable|in:'.implode(',', array_keys(RoutineCompletion::SKIP_REASONS)),
        ]);

        $date = $this->parseDate($data['date'] ?? null);
        $skipped = $item->skipOn($date, $data['reason'] ?? null);
        $routineSkipped = $this->syncRoutineSkipWithSteps($item->routine, $date);

        [$stepsSkipped, $stepsTotal] = $this->stepSkipCounts($item->routine, $date);

        return response()->json([
            'ok' => true,
            'skipped' => $skipped,
            'item_id' => $item->id,
            'routine_id' => $item->routine_id,
            'date' => $date->toDateString(),
            'routine_skipped' => $routineSkipped,
            'steps_skipped' => $stepsSkipped,
            'steps_total' => $stepsTotal,
        ]);
    }

    /**
     * Keep the routine-level skip in sync with its steps: all steps skipped
     * closes the day (auto marker, manual rows untouched); any reopened step
     * reopens an auto-closed day (manual rows stay sticky).
     *
     * @return bool whether the routine carries a skip record now
     */
    private function syncRoutineSkipWithSteps(Routine $routine, Carbon $date): bool
    {
        $key = RoutineCompletion::dateKey($date);
        $items = $routine->checklistItems()->get();

        $allSkipped = $items->isNotEmpty() && $items->every(fn ($it) => $it->skippedOn($date));

        // Fresh query: the preloaded relation is stale after skipOn writes.
        $record = $routine->completions()
            ->where('user_id', $routine->user_id)
            ->where('completed_date', $key)
            ->where('status', RoutineCompletion::STATUS_SKIPPED)
            ->first();
        $isSkipped = $record !== null;

        if ($allSkipped && ! $isSkipped) {
            $routine->skipOn($date, RoutineCompletion::AUTO_ALL_STEPS);
            $isSkipped = true;
        } elseif (! $allSkipped && $isSkipped
            && ($record->skip_reason ?? null) === RoutineCompletion::AUTO_ALL_STEPS) {
            $routine->completions()
                ->where('user_id', $routine->user_id)
                ->where('completed_date', $key)
                ->where('status', RoutineCompletion::STATUS_SKIPPED)
                ->delete();
            $isSkipped = false;
        }

        return $isSkipped;
    }

    /**
     * @return array{int, int} [skipped steps, total steps] for the date
     */
    private function stepSkipCounts(Routine $routine, Carbon $date): array
    {
        $items = $routine->checklistItems()->get();

        return [
            $items->filter(fn ($it) => $it->skippedOn($date))->count(),
            $items->count(),
        ];
    }

    /**
     * Log a slip for an avoid habit (routine-level: the whole day is lost at
     * once when the habit has no steps).
     */
    public function logViolation(Request $request, Routine $routine)
    {
        abort_if($routine->user_id !== Auth::id(), 403);
        abort_if(! $routine->isAvoid(), 422, 'Only avoid habits accept slips.');

        $date = $this->parseDate($request->input('date'));
        $data = $request->validate([
            'quantity' => 'nullable|integer|min:1|max:100000',
            'occurred_at' => 'nullable|date|before_or_equal:' . now()->addMinutes(5)->toDateTimeString(),
            'trigger' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:2000',
            'mood' => 'nullable|integer|min:1|max:10',
            'location' => 'nullable|string|max:100',
        ]);

        $at = ! empty($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now();
        // Precise time: clamp future drift to now
        if ($at->gt(now()->addMinutes(5))) {
            $at = now();
        }
        // Plain mode (count_violations = false): one slip per day is enough — dedupe.
        if (! $routine->count_violations) {
            $existing = $routine->violations()
                ->where('occurred_date', $date->toDateString())
                ->whereNull('checklist_item_id')
                ->first();
            if ($existing) {
                return response()->json([
                    'ok' => true,
                    'violation_id' => $existing->id,
                    'routine_id' => $routine->id,
                    'date' => $date->toDateString(),
                    'violated' => true,
                    'day_qty' => $routine->fresh()->violationQtyOn($date),
                    'already_violated' => true,
                ]);
            }
        }

        $violation = $routine->violations()->create([
            'checklist_item_id' => null,
            'user_id' => Auth::id(),
            'occurred_at' => $at,
            'occurred_date' => $date->toDateString(),
            'quantity' => $routine->count_violations ? max(1, (int) ($data['quantity'] ?? 1)) : 1,
            'mood' => isset($data['mood']) ? max(1, min(10, (int) $data['mood'])) : null,
            'location' => isset($data['location']) ? trim((string) $data['location']) ?: null : null,
            'trigger' => isset($data['trigger']) ? trim((string) $data['trigger']) ?: null : null,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'violation_id' => $violation->id,
            'routine_id' => $routine->id,
            'date' => $date->toDateString(),
            'violated' => true,
            'day_qty' => $routine->fresh()->violationQtyOn($date),
        ]);
    }

    /**
     * Log a slip for one step of an avoid habit. Other steps stay open; only
     * this step's slot takes the hit.
     */
    public function logStepViolation(Request $request, RoutineChecklistItem $item)
    {
        abort_if($item->user_id !== Auth::id(), 403);
        abort_if(! $item->routine->isAvoid(), 422, 'Only avoid-habit steps accept slips.');

        $routine = $item->routine;
        $date = $this->parseDate($request->input('date'));
        $data = $request->validate([
            'quantity' => 'nullable|integer|min:1|max:100000',
            'occurred_at' => 'nullable|date|before_or_equal:' . now()->addMinutes(5)->toDateTimeString(),
            'trigger' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:2000',
            'mood' => 'nullable|integer|min:1|max:10',
            'location' => 'nullable|string|max:100',
        ]);

        $at = ! empty($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now();
        if ($at->gt(now()->addMinutes(5))) {
            $at = now();
        }
        // Plain mode: one slip per step per day is enough — dedupe.
        if (! $routine->count_violations) {
            $existing = $routine->violations()
                ->where('checklist_item_id', $item->id)
                ->where('occurred_date', $date->toDateString())
                ->first();
            if ($existing) {
                $stepQty = (int) $routine->violations()
                    ->where('checklist_item_id', $item->id)
                    ->where('occurred_date', $date->toDateString())
                    ->sum('quantity');

                return response()->json([
                    'ok' => true,
                    'violation_id' => $existing->id,
                    'routine_id' => $routine->id,
                    'item_id' => $item->id,
                    'date' => $date->toDateString(),
                    'violated' => true,
                    'step_qty' => $stepQty,
                    'day_qty' => $routine->violationQtyOn($date),
                    'already_violated' => true,
                ]);
            }
        }

        $violation = $routine->violations()->create([
            'checklist_item_id' => $item->id,
            'user_id' => Auth::id(),
            'occurred_at' => $at,
            'occurred_date' => $date->toDateString(),
            'quantity' => $routine->count_violations ? max(1, (int) ($data['quantity'] ?? 1)) : 1,
            'mood' => isset($data['mood']) ? max(1, min(10, (int) $data['mood'])) : null,
            'location' => isset($data['location']) ? trim((string) $data['location']) ?: null : null,
            'trigger' => isset($data['trigger']) ? trim((string) $data['trigger']) ?: null : null,
            'note' => $data['note'] ?? null,
        ]);

        $stepQty = (int) $routine->violations()
            ->where('checklist_item_id', $item->id)
            ->where('occurred_date', $date->toDateString())
            ->sum('quantity');

        return response()->json([
            'ok' => true,
            'violation_id' => $violation->id,
            'routine_id' => $routine->id,
            'item_id' => $item->id,
            'date' => $date->toDateString(),
            'violated' => true,
            'step_qty' => $stepQty,
            'day_qty' => $routine->violationQtyOn($date),
        ]);
    }

    /**
     * Undo a slip (violation) - only within 10 minutes of creation (mistaken entry).
     * No permanent delete: this is strictly a short undo window.
     */
    public function destroyViolation(Request $request, \App\Models\RoutineViolation $violation)
    {
        abort_if($violation->user_id !== Auth::id(), 403);
        // Only today's violation can be undone
        $today = today()->toDateString();
        $violDate = $violation->occurred_date instanceof Carbon ? $violation->occurred_date->toDateString() : substr((string) $violation->occurred_date, 0, 10);
        abort_if($violDate !== $today, 410, __('Undo window expired.'));
        // Strict 10-minute undo window based on creation time
        $createdAt = $violation->created_at instanceof Carbon ? $violation->created_at : Carbon::parse($violation->created_at);
        abort_if($createdAt->diffInSeconds(now()) > 600, 410, __('Undo window expired.'));

        $routineId = $violation->routine_id;
        $itemId = $violation->checklist_item_id;
        $dateKey = $violDate;
        $violation->delete();

        // Recalculate day quantities
        $routine = Routine::where('id', $routineId)->where('user_id', Auth::id())->first();
        $dayQty = $routine ? $routine->fresh()->violationQtyOn(Carbon::parse($dateKey)) : 0;
        $stepQty = null;
        if ($itemId && $routine) {
            $stepQty = (int) $routine->violations()->where('checklist_item_id', $itemId)->where('occurred_date', $dateKey)->sum('quantity');
        }

        return response()->json([
            'ok' => true,
            'routine_id' => $routineId,
            'item_id' => $itemId,
            'date' => $dateKey,
            'day_qty' => $dayQty,
            'step_qty' => $stepQty,
            'violated' => $dayQty > 0,
        ]);
    }

    /**
     * Undo a craving/note - only within 10 minutes of creation.
     */
    public function destroyNote(Request $request, \App\Models\RoutineNote $note)
    {
        abort_if($note->user_id !== Auth::id(), 403);
        $today = today()->toDateString();
        $noteDate = $note->occurred_at instanceof Carbon ? $note->occurred_at->toDateString() : Carbon::parse($note->occurred_at)->toDateString();
        abort_if($noteDate !== $today, 410, __('Undo window expired.'));
        $createdAt = $note->created_at instanceof Carbon ? $note->created_at : Carbon::parse($note->created_at);
        abort_if($createdAt->diffInSeconds(now()) > 600, 410, __('Undo window expired.'));
        $note->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Trigger + location suggestions for avoid forms: top values used by this
     * user for this routine (violations + notes), plus generic defaults.
     * Cached per user+routine for 5 minutes to keep the form instant.
     */
    public function avoidSuggestions(Request $request, Routine $routine)
    {
        abort_if($routine->user_id !== Auth::id(), 403);

        $q = trim((string) $request->input('q', ''));

        $cacheKey = 'avoid_suggest_' . Auth::id() . '_' . $routine->id . '_' . md5(mb_strtolower($q));
        $result = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($routine, $q) {
            $userId = Auth::id();

            $trigQuery = \App\Models\RoutineViolation::where('user_id', $userId)
                ->where('routine_id', $routine->id)
                ->whereNotNull('trigger')
                ->selectRaw('`trigger`, COUNT(*) as c')
                ->groupBy('trigger')
                ->orderByDesc('c')
                ->limit(15)
                ->pluck('trigger')
                ->filter(fn ($t) => trim((string) $t) !== '')
                ->values();

            $noteTriggers = \App\Models\RoutineNote::where('user_id', $userId)
                ->where('routine_id', $routine->id)
                ->whereNotNull('trigger')
                ->selectRaw('`trigger`, COUNT(*) as c')
                ->groupBy('trigger')
                ->orderByDesc('c')
                ->limit(15)
                ->pluck('trigger')
                ->filter(fn ($t) => trim((string) $t) !== '')
                ->values();

            $triggers = $trigQuery->merge($noteTriggers)->unique()->take(10)->values();

            $locQuery = \App\Models\RoutineViolation::where('user_id', $userId)
                ->where('routine_id', $routine->id)
                ->whereNotNull('location')
                ->selectRaw('`location`, COUNT(*) as c')
                ->groupBy('location')
                ->orderByDesc('c')
                ->limit(10)
                ->pluck('location')
                ->filter(fn ($t) => trim((string) $t) !== '')
                ->values();

            $noteLocs = \App\Models\RoutineNote::where('user_id', $userId)
                ->where('routine_id', $routine->id)
                ->whereNotNull('location')
                ->selectRaw('`location`, COUNT(*) as c')
                ->groupBy('location')
                ->orderByDesc('c')
                ->limit(10)
                ->pluck('location')
                ->filter(fn ($t) => trim((string) $t) !== '')
                ->values();

            $locations = $locQuery->merge($noteLocs)->unique()->take(8)->values();

            if ($q !== '') {
                $low = mb_strtolower($q);
                $triggers = $triggers->filter(fn ($t) => str_contains(mb_strtolower($t), $low))->values();
                $locations = $locations->filter(fn ($t) => str_contains(mb_strtolower($t), $low))->values();
            }

            return [
                'triggers' => $triggers,
                'locations' => $locations,
            ];
        });

        return response()->json(['ok' => true] + $result);
    }

    /**
     * Unified history for one avoid routine: slips + cravings/notes merged,
     * newest first, with type + date filters. Single organized place for the
     * user's slips/cravings (Phase 3).
     */
    public function avoidHistory(Request $request, Routine $routine)
    {
        abort_if($routine->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'type' => 'nullable|in:all,slip,craving,note',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'trigger' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:50',
        ]);

        $type = $data['type'] ?? 'all';
        $perPage = (int) ($data['per_page'] ?? 20);
        $to = !empty($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $from = !empty($data['from']) ? Carbon::parse($data['from'])->startOfDay() : $to->copy()->subDays(29)->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $items = collect();

        if (in_array($type, ['all', 'slip'])) {
            $vq = \App\Models\RoutineViolation::where('user_id', Auth::id())
                ->where('routine_id', $routine->id)
                ->whereBetween('occurred_at', [$from, $to])
                ->with('item:id,name')
                ->orderByDesc('occurred_at');
            if (!empty($data['trigger'])) {
                $vq->where('trigger', 'like', '%' . $data['trigger'] . '%');
            }
            $vq->chunk(500, function ($rows) use (&$items) {
                foreach ($rows as $v) {
                    $items->push([
                        'id' => $v->id,
                        'entry_type' => 'slip',
                        'kind' => null,
                        'occurred_at' => $v->occurred_at?->toIso8601String(),
                        'occurred_label' => $v->occurred_at ? $v->occurred_at->format('Y-m-d H:i') : null,
                        'quantity' => (int) ($v->quantity ?? 1),
                        'mood' => $v->mood,
                        'location' => $v->location,
                        'trigger' => $v->trigger,
                        'note' => $v->note,
                        'step' => $v->item?->name,
                    ]);
                }
            });
        }

        if (in_array($type, ['all', 'craving', 'note'])) {
            $nq = \App\Models\RoutineNote::where('user_id', Auth::id())
                ->where('routine_id', $routine->id)
                ->whereBetween('occurred_at', [$from, $to])
                ->with('item:id,name')
                ->orderByDesc('occurred_at');
            if ($type === 'craving' || $type === 'note') {
                $nq->where('kind', $type);
            }
            if (!empty($data['trigger'])) {
                $nq->where('trigger', 'like', '%' . $data['trigger'] . '%');
            }
            $nq->chunk(500, function ($rows) use (&$items) {
                foreach ($rows as $n) {
                    $items->push([
                        'id' => $n->id,
                        'entry_type' => $n->kind === 'craving' ? 'craving' : 'note',
                        'kind' => $n->kind,
                        'occurred_at' => $n->occurred_at?->toIso8601String(),
                        'occurred_label' => $n->occurred_at ? $n->occurred_at->format('Y-m-d H:i') : null,
                        'quantity' => null,
                        'mood' => $n->mood,
                        'location' => $n->location,
                        'trigger' => $n->trigger,
                        'note' => $n->note,
                        'step' => $n->item?->name,
                    ]);
                }
            });
        }

        $sorted = $items->sortByDesc('occurred_at')->values();
        $page = max(1, (int) $request->input('page', 1));
        $total = $sorted->count();
        $paged = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'ok' => true,
            'routine_id' => $routine->id,
            'type' => $type,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => $total > $page * $perPage,
            'items' => $paged,
        ]);
    }

    /**
     * Log a craving or free note for a routine (optionally one step), with an
     * exact timestamp so reports can use it later.
     */
    public function logRoutineNote(Request $request, Routine $routine)
    {
        abort_if($routine->user_id !== Auth::id(), 403);

        $date = $this->parseDate($request->input('date'));
        $data = $request->validate([
            'kind' => 'nullable|in:craving,note',
            'checklist_item_id' => 'nullable|integer|exists:routine_checklist_items,id',
            'occurred_at' => 'nullable|date|before_or_equal:' . now()->addMinutes(5)->toDateTimeString(),
            'note' => 'nullable|string|max:2000',
            'mood' => 'nullable|integer|min:1|max:10',
            'location' => 'nullable|string|max:100',
            'trigger' => 'nullable|string|max:100',
        ]);

        $itemId = null;
        if (! empty($data['checklist_item_id'])) {
            $item = RoutineChecklistItem::where('id', $data['checklist_item_id'])
                ->where('routine_id', $routine->id)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            $itemId = $item->id;
        }

        $at = ! empty($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now();
        if ($at->gt(now()->addMinutes(5))) {
            $at = now();
        }
        $note = $routine->routineNotes()->create([
            'checklist_item_id' => $itemId,
            'user_id' => Auth::id(),
            'kind' => $data['kind'] ?? \App\Models\RoutineNote::KIND_NOTE,
            'mood' => isset($data['mood']) ? max(1, min(10, (int) $data['mood'])) : null,
            'location' => isset($data['location']) ? trim((string) $data['location']) ?: null : null,
            'trigger' => isset($data['trigger']) ? trim((string) $data['trigger']) ?: null : null,
            'occurred_at' => $at,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'note_id' => $note->id,
            'routine_id' => $routine->id,
            'kind' => $note->kind,
            'date' => $date->toDateString(),
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
        // Failed tasks are closed for now (red state) — never suggested.
        $task = $pending->first(fn ($t) => ! $t->failed_at);
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

        // Skip done and explicitly marked "did not do it" — a skipped routine
        // is closed for the day and must not resurface as the next suggestion.
        // Fully resolved step routines (every step done, skipped or slipped)
        // are settled too: there is nothing left to guide to.
        $routine = $todayRoutines->first(function ($r) use ($date) {
            if ($r->completedOn($date)) {
                return false;
            }
            if (($r->completionRecord($date)?->status ?? null) === RoutineCompletion::STATUS_SKIPPED) {
                return false;
            }
            $steps = collect($r->ringSteps ?? []);
            if ($steps->isNotEmpty()) {
                $isAvoid = $r->isAvoid();
                $open = $steps->first(fn ($s) => $isAvoid
                    ? empty($s['violated'])
                    : (empty($s['completed']) && empty($s['skipped'])));
                if (! $open) {
                    return false;
                }
            }

            return true;
        });
        if ($routine) {
            $steps = collect($routine->ringSteps ?? []);
            $isAvoid = $routine->isAvoid();
            // Avoid habits guide the first step without a slip, never a check.
            // Skipped steps are settled too — guidance moves past them.
            $nextStep = $isAvoid
                ? $steps->first(fn ($s) => empty($s['violated']))
                : $steps->first(fn ($s) => empty($s['completed']) && empty($s['skipped']));

            return [
                'type' => 'routine',
                'routine' => $routine,
                'date' => $date,
                'is_avoid' => $isAvoid,
                'stepsDone' => $isAvoid
                    ? $steps->where('violated', true)->count()
                    : $steps->where('completed', true)->count(),
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
                ->whereNull('failed_at')
                ->with('project:id,name')
                ->get()
        );

        $routines = $this->fetchRoutines($user);
        // Next Up only needs today's completion state. The full day view owns
        // the one-year habit metrics; refreshing this small card must stay fast.
        $this->preloadRoutineCompletions($user->id, $routines, $date, $date);
        $stepMap = $this->preloadStepCompletions($routines, $date, $date);
        $this->preloadRoutineLogs($user->id, $routines, $date, $date);
        $violationMap = $this->preloadRoutineViolations($user->id, $routines, $date, $date);
        $this->preloadRoutineNotes($user->id, $routines, $date, $date);
        $today = $routines
            ->filter(fn ($r) => $r->occursOn($date))
            ->values();
        $this->decorateNextUpRoutines($today, $date, $stepMap, $violationMap);
        $today = $today
            ->sortBy(fn ($r) => $r->sortKey())
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
    private function decorateNextUpRoutines($routines, Carbon $date, array $stepMap, array $violationMap = []): void
    {
        $dayKey = $date->toDateString();

        foreach ($routines as $routine) {
            $isAvoid = $routine->isAvoid();
            $steps = $routine->relationLoaded('checklistItems')
                ? $routine->checklistItems->sortBy(fn ($s) => $s->sortKey())->values()
                : collect();

            $routine->ringSteps = $steps->map(function ($s) use ($routine, $isAvoid, $stepMap, $violationMap, $dayKey) {
                $violatedQty = $isAvoid ? (int) ($violationMap['step'][(int) $s->id][$dayKey] ?? 0) : 0;

                return [
                'id' => $s->id,
                'name' => $s->name,
                'completed' => $isAvoid ? false : isset($stepMap[(int) $s->id][$dayKey]),
                'skipped' => ! $isAvoid && $s->relationLoaded('completions')
                    && $s->completions->contains(fn ($c) => ($c->status ?? null) === RoutineCheckitemCompletion::STATUS_SKIPPED
                        && ($c->completed_date instanceof Carbon
                            ? $c->completed_date->toDateString()
                            : Carbon::parse($c->completed_date)->toDateString()) === $dayKey),
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
                : fn ($s) => ! empty($s['completed']) || ! empty($s['skipped']);
            $activeStep = $routine->ringSteps->first(fn ($s) => ! $isSettled($s) && $hasSchedule($s));
            $activeStep ??= $routine->ringSteps->last(fn ($s) => $hasSchedule($s));
            $routine->activeStepSortKey = $activeStep['sort_key'] ?? null;
            $routine->activeStepSchedule = $activeStep ? [
                'period_label' => $activeStep['period_label'],
                'period_icon' => $activeStep['period_icon'],
                'period_color' => $activeStep['period_color'],
                'time_label' => $activeStep['time_label'],
                'time_period' => $activeStep['time_period'] ?? null,
                'scheduled_time' => $activeStep['scheduled_time'] ?? null,
                'step_id' => $activeStep['id'] ?? null,
                'sort_order' => (int) ($steps->firstWhere('id', $activeStep['id'])?->sort_order ?? 0),
            ] : null;
            $routine->avoidDayQty = $isAvoid
                ? (int) ($violationMap['routine'][(int) $routine->id][$dayKey] ?? 0)
                : 0;
            $routine->avoidDayViolated = $routine->avoidDayQty > 0;
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
            'html' => view('planner._task-row', ['task' => $task, 'count' => true, 'postpone' => 'tomorrow', 'hideDue' => true, 'draggable' => true])->render(),
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
                // Only done rows drive the completed lookup; skipped steps
                // carry their own flag (see decorateHabitMetricsBulk).
                if (($row->status ?? RoutineCheckitemCompletion::STATUS_DONE) !== RoutineCheckitemCompletion::STATUS_DONE) {
                    continue;
                }
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
    private function preloadRoutineViolations(int $userId, $routines, Carbon $from, Carbon $to): array
    {
        $map = ['routine' => [], 'step' => []];
        if ($routines->isEmpty()) {
            return $map;
        }

        $rows = \App\Models\RoutineViolation::where('user_id', $userId)
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

    private function preloadRoutineNotes(int $userId, $routines, Carbon $from, Carbon $to): void
    {
        if ($routines->isEmpty()) {
            return;
        }
        $rows = \App\Models\RoutineNote::where('user_id', $userId)
            ->whereIn('routine_id', $routines->pluck('id'))
            ->whereBetween('occurred_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get()
            ->groupBy('routine_id');
        foreach ($routines as $routine) {
            $list = $rows->get($routine->id, collect());
            $routine->setRelation('routineNotes', $list);
        }
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
    private function decorateHabitMetricsBulk($routines, Carbon $date, array $stepMap, array $violationMap = []): void
    {
        foreach ($routines as $routine) {
            $isAvoid = $routine->isAvoid();
            $dayKey = $date->toDateString();

            if ($isAvoid) {
                // Clean-day metrics: today is excluded until violated.
                $m = $routine->avoidMetrics($date);
                $routine->ringRate = $m['rate'];
                $routine->ringStreak = $m['current'];
                $routine->ringBest = $m['best'];
                $routine->ringLast7 = $m['last7'];
            } else {
                $completedSet = $routine->relationLoaded('completions')
                    ? $routine->completions
                        ->filter(fn ($c) => ($c->status ?? RoutineCompletion::STATUS_DONE) === RoutineCompletion::STATUS_DONE)
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
                'skipped' => ! $isAvoid && $s->relationLoaded('completions')
                    && $s->completions->contains(fn ($c) => ($c->status ?? null) === RoutineCheckitemCompletion::STATUS_SKIPPED
                        && ($c->completed_date instanceof Carbon
                            ? $c->completed_date->toDateString()
                            : Carbon::parse($c->completed_date)->toDateString()) === $dayKey),
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
            // Avoid habits resolve steps by violation, not by check: a violated
            // step counts as settled for the day, the rest stay open.
            $isSettled = $isAvoid
                ? fn ($s) => ! empty($s['violated'])
                : fn ($s) => ! empty($s['completed']) || ! empty($s['skipped']);

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

            // Avoid day state: total slips today (routine + steps).
            $routine->avoidDayQty = $isAvoid
                ? (int) ($violationMap['routine'][(int) $routine->id][$dayKey] ?? 0)
                : 0;
            $routine->avoidDayViolated = $routine->avoidDayQty > 0;

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
            ->where('status', RoutineCheckitemCompletion::STATUS_DONE)
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
                    ->where('status', RoutineCheckitemCompletion::STATUS_DONE)
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
