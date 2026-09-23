<?php

namespace App\Http\Controllers;

use App\Models\Routine;
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

        $routinesData = $this->routinesForDate($user, $selected);
        $this->decorateHabitMetrics($routinesData['today'], $selected);

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

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $day = $start->copy()->addDays($i);
            $dayTasks = $tasks->filter(
                fn ($t) => $t->due_date && Carbon::parse($t->due_date)->isSameDay($day)
            );
            $dayRoutines = $this->routinesForDate($user, $day);
            $this->decorateHabitMetrics($dayRoutines['today'], $day);
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

        return response()->json([
            'ok' => true,
            'completed' => $completed,
            'date' => $date->toDateString(),
        ]);
    }

    /**
     * Split routines into: occurring today, later-this-week, later-this-month,
     * plus completion counts for the selected date.
     */
    private function routinesForDate($user, Carbon $date): array
    {
        $routines = $user->routines()->get();

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
     * Attach habit-ring data (adherence %, streak, last-7 squares) to each
     * routine so _routine-row can render the ring without extra queries there.
     */
    private function decorateHabitMetrics($routines, Carbon $date): void
    {
        foreach ($routines as $routine) {
            $m = $routine->habitMetrics($date);
            $routine->ringRate = $m['rate'];
            $routine->ringStreak = $m['streak'];
            $routine->ringLast7 = $m['last7'];
        }
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
