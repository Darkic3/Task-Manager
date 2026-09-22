<?php

namespace App\Http\Controllers;

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
        $done    = $tasks->where('status', 'completed')->values();

        $overdue = $this->sortByPriority(
            Task::where('user_id', $user->id)
                ->where('status', '!=', 'completed')
                ->whereDate('due_date', '<', $selected->toDateString())
                ->with('project:id,name')
                ->get()
        );

        return view('planner.index', [
            'view'     => 'day',
            'date'     => $selected,
            'isToday'  => $selected->isSameDay(now()),
            'pending'  => $pending,
            'done'     => $done,
            'overdue'  => $overdue,
        ]);
    }

    private function weekView($user, Carbon $date)
    {
        $start = $date->copy()->startOfWeek(Carbon::SATURDAY)->startOfDay();
        $end   = $start->copy()->addDays(6);

        $tasks = Task::where('user_id', $user->id)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->with('project:id,name')
            ->get();

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $dayTasks = $tasks->filter(
                fn ($t) => $t->due_date && Carbon::parse($t->due_date)->isSameDay($day)
            );
            $days[] = [
                'date'  => $day,
                'tasks' => $this->sortByPriority($dayTasks->values()),
            ];
        }

        return view('planner.index', [
            'view'    => 'week',
            'date'    => $date->copy(),
            'start'   => $start,
            'end'     => $end,
            'days'    => $days,
            'isToday' => now()->between($start->copy()->startOfDay(), $end->copy()->endOfDay()),
        ]);
    }

    public function toggleTask(Task $task)
    {
        abort_if($task->user_id !== Auth::id(), 403);

        $task->status = $task->status === 'completed' ? 'to_do' : 'completed';
        $task->save();

        return response()->json([
            'ok'        => true,
            'status'    => $task->status,
            'completed' => $task->status === 'completed',
        ]);
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
