<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     */
    public function index()
    {
        $user = Auth::user();

        // Basic counts
        $tasksCount = $user->tasks()->count();
        $routinesCount = $user->routines()->count();
        $notesCount = $user->notes()->count();
        $remindersCount = $user->reminders()->count();
        $filesCount = $user->files()->count();
        $projectsCount = $user->projects()->count();

        // Recent items
        $recentTasks = $user->tasks()
            ->with('project')
            ->latest()
            ->take(5)
            ->get();

        $recentNotes = $user->notes()
            ->latest()
            ->take(5)
            ->get();

        // Today's routines (with done counter for the quick-check widget)
        // Batched: one routines query + one completions query, no per-row completedOn().
        $todayKey = now()->toDateString();
        $todayRoutines = $user->routines()->get();
        $todayCompletionIds = $todayRoutines->isNotEmpty()
            ? \App\Models\RoutineCompletion::where('user_id', $user->id)
                ->whereIn('routine_id', $todayRoutines->pluck('id'))
                ->where('completed_date', $todayKey)
                ->pluck('routine_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
            : collect();
        foreach ($todayRoutines as $routine) {
            $record = isset($todayCompletionIds[(int) $routine->id])
                ? new \App\Models\RoutineCompletion(['routine_id' => $routine->id, 'completed_date' => $todayKey])
                : null;
            $routine->setRelation('completions', collect($record ? [$record] : []));
        }
        $todayRoutines = $todayRoutines
            ->filter(fn ($routine) => $routine->occursOn(now()))
            ->sortBy(fn ($r) => $r->sortKey())
            ->values();

        $routineTotalCount = $todayRoutines->count();
        $routineDoneCount  = $todayRoutines->filter(fn ($r) => $r->completedOn(now()))->count();

        // Upcoming reminders
        $upcomingReminders = $user->reminders()
            ->where('date', '>=', now())
            ->orderBy('date')
            ->take(5)
            ->get();

        // Additional statistics for analytics (aggregated: 2 group-by queries + 3 counts)
        $statusCounts = $user->tasks()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $priorityCounts = $user->tasks()->where('status', '!=', 'completed')
            ->selectRaw('priority, COUNT(*) as c')->groupBy('priority')->pluck('c', 'priority');

        $completedTasksThisWeek = $user->tasks()
            ->where('status', 'completed')
            ->whereDate('updated_at', '>=', now()->startOfWeek())
            ->count();

        $totalTasks = max(array_sum($statusCounts->toArray()), 1);
        $completedTasks = (int) ($statusCounts['completed'] ?? 0);
        $completionRate = round(($completedTasks / $totalTasks) * 100);

        $activeProjects = $user->projects()
            ->where('status', 'in_progress')
            ->count();

        $overdueTasks = $user->tasks()
            ->where('due_date', '<', now())
            ->where('status', '!=', 'completed')
            ->count();

        // Task status distribution
        $taskStatusDistribution = [
            'to_do' => (int) ($statusCounts['to_do'] ?? 0),
            'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
            'completed' => (int) ($statusCounts['completed'] ?? 0),
        ];

        // Priority distribution (only non-completed tasks)
        $priorityDistribution = [
            'high' => (int) ($priorityCounts['high'] ?? 0),
            'medium' => (int) ($priorityCounts['medium'] ?? 0),
            'low' => (int) ($priorityCounts['low'] ?? 0),
        ];

        // Calculate priority percentages for progress bars
        $totalNonCompletedTasks = max($totalTasks - $completedTasks, 1);
        $priorityPercentages = [
            'high' => round(($priorityDistribution['high'] / $totalNonCompletedTasks) * 100),
            'medium' => round(($priorityDistribution['medium'] / $totalNonCompletedTasks) * 100),
            'low' => round(($priorityDistribution['low'] / $totalNonCompletedTasks) * 100),
        ];

        return view('dashboard', compact(
            'tasksCount',
            'routinesCount',
            'notesCount',
            'remindersCount',
            'filesCount',
            'projectsCount',
            'recentTasks',
            'todayRoutines',
            'routineTotalCount',
            'routineDoneCount',
            'recentNotes',
            'upcomingReminders',
            'completedTasksThisWeek',
            'completionRate',
            'activeProjects',
            'overdueTasks',
            'taskStatusDistribution',
            'priorityDistribution',
            'priorityPercentages'
        ));
    }

    /**
     * Get productivity data for charts (AJAX endpoint).
     */
    public function getProductivityData(Request $request)
    {
        $user = Auth::user();
        $period = $request->get('period', 'week');

        $data = [];
        $labels = [];

        switch ($period) {
            case 'week':
                $startDate = now()->startOfWeek();
                $rows = $user->tasks()
                    ->where('status', 'completed')
                    ->whereDate('updated_at', '>=', $startDate->toDateString())
                    ->selectRaw('DATE(updated_at) as d, COUNT(*) as c')
                    ->groupBy('d')
                    ->pluck('c', 'd');
                for ($i = 0; $i < 7; $i++) {
                    $date = $startDate->copy()->addDays($i);
                    $labels[] = $date->format('M j');
                    $data[] = (int) ($rows[$date->toDateString()] ?? 0);
                }
                break;

            case 'month':
                $startDate = now()->startOfMonth();
                $daysInMonth = now()->daysInMonth;
                $rows = $user->tasks()
                    ->where('status', 'completed')
                    ->whereDate('updated_at', '>=', $startDate->toDateString())
                    ->selectRaw('DATE(updated_at) as d, COUNT(*) as c')
                    ->groupBy('d')
                    ->pluck('c', 'd');
                for ($i = 0; $i < $daysInMonth; $i++) {
                    $date = $startDate->copy()->addDays($i);
                    $labels[] = $date->format('j');
                    $data[] = (int) ($rows[$date->toDateString()] ?? 0);
                }
                break;

            case 'year':
                $rows = $user->tasks()
                    ->where('status', 'completed')
                    ->whereYear('updated_at', now()->year)
                    ->selectRaw('MONTH(updated_at) as m, COUNT(*) as c')
                    ->groupBy('m')
                    ->pluck('c', 'm');
                for ($i = 0; $i < 12; $i++) {
                    $date = now()->startOfYear()->addMonths($i);
                    $labels[] = $date->format('M');
                    $data[] = (int) ($rows[$date->month] ?? 0);
                }
                break;
        }

        return response()->json([
            'labels' => $labels,
            'data' => $data,
        ]);
    }
}
