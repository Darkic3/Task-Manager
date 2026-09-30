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

        // Today's routines with full habit rings, adherence metrics, step completions, and avoid habit tracking
        $routinesData = app(\App\Services\RoutinePlannerService::class)->getDayRoutinesData($user, now());
        $todayRoutines     = $routinesData['today'];
        $routineTotalCount = $routinesData['total'];
        $routineDoneCount  = $routinesData['done'];

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

        // Today's Focus tasks (high priority or due today/overdue, not completed)
        $todayFocusTasks = $user->tasks()
            ->with(['project:id,name,slug', 'checklistItems', 'timeEntries'])
            ->where('status', '!=', 'completed')
            ->where(function ($q) {
                $q->whereDate('due_date', '<=', now()->toDateString())
                  ->orWhere('priority', 'high');
            })
            ->orderByRaw("CASE WHEN due_date IS NOT NULL AND due_date <= CURDATE() THEN 0 WHEN priority = 'high' THEN 1 WHEN priority = 'medium' THEN 2 ELSE 3 END")
            ->orderBy('due_date', 'asc')
            ->take(6)
            ->get();

        // Today's Workout Session integration
        $todayWeekday = strtolower(now()->format('l'));
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
                    ->whereDate('workout_date', now()->toDateString())
                    ->first();
            }
        }

        // Active running timer
        $activeTimeEntry = $user->timeEntries()
            ->where('status', \App\Models\TimeEntry::STATUS_RUNNING)
            ->with(['project:id,name', 'task:id,title'])
            ->first();

        // Tasks completed today
        $tasksCompletedToday = $user->tasks()
            ->where('status', 'completed')
            ->whereDate('updated_at', now()->toDateString())
            ->count();

        // Morning wake-up check-in prompt (first thing after login).
        $morningCheckin = $this->morningCheckinData($user);

        return view('dashboard', compact(
            'tasksCount',
            'routinesCount',
            'notesCount',
            'remindersCount',
            'filesCount',
            'projectsCount',
            'recentTasks',
            'todayFocusTasks',
            'todayRoutines',
            'routineTotalCount',
            'routineDoneCount',
            'activeWorkoutPlan',
            'todayWorkoutDay',
            'todayWorkoutSession',
            'activeTimeEntry',
            'tasksCompletedToday',
            'recentNotes',
            'upcomingReminders',
            'completedTasksThisWeek',
            'completionRate',
            'activeProjects',
            'overdueTasks',
            'taskStatusDistribution',
            'priorityDistribution',
            'priorityPercentages',
            'morningCheckin'
        ));
    }

    /**
     * Data for the morning wake-up check-in modal, or null when it should not show.
     */
    private function morningCheckinData($user): ?array
    {
        if (!$user->morning_checkin_enabled || !$user->wake_routine_id) {
            return null;
        }

        $now = now();
        $today = $now->toDateString();

        if (session('morning_checkin_dismissed_' . $today)) {
            return null;
        }

        $snoozeUntil = session('morning_checkin_snooze_until');
        if ($snoozeUntil) {
            try {
                if ($now->lt(\Carbon\Carbon::parse($snoozeUntil))) {
                    return null;
                }
            } catch (\Throwable $e) {
                // Ignore malformed value and continue.
            }
        }

        [$windowStart, $windowEnd] = $user->morningWindow();
        $hm = $now->format('H:i');
        if ($hm < $windowStart || $hm >= $windowEnd) {
            return null;
        }

        $routine = $user->wakeRoutine()->first();
        if (!$routine || !$routine->isTimeValue()) {
            return null;
        }

        if (!$routine->occursOn($today)) {
            return null;
        }

        $loggedToday = $routine->loggedValues($today)['value'] ?? null;
        if ($loggedToday !== null) {
            return null;
        }

        // Smart default: yesterday's logged time, else the scheduled time, else now.
        $yesterday = $now->copy()->subDay()->toDateString();
        $yesterdayValue = $routine->loggedValues($yesterday)['value'] ?? null;
        $defaultMinutes = $yesterdayValue !== null
            ? (int) round((float) $yesterdayValue)
            : $routine->scheduledReferenceMinutes();
        $default = \App\Models\Routine::minutesToTimeValue($defaultMinutes) ?? $now->format('H:i');

        return [
            'routine_id' => $routine->id,
            'routine_title' => $routine->title,
            'value_label' => $routine->value_label ?: 'Wake-up Time',
            'log_url' => route('planner.routines.log', $routine),
            'date' => $today,
            'default' => $default,
        ];
    }

    /**
     * Dismiss the morning check-in prompt (for today, or snooze 45 minutes).
     */
    public function dismissMorningCheckin(Request $request)
    {
        $request->validate(['mode' => ['required', 'in:today,snooze']]);

        if ($request->input('mode') === 'today') {
            session(['morning_checkin_dismissed_' . now()->toDateString() => true]);
        } else {
            session(['morning_checkin_snooze_until' => now()->addMinutes(45)->toIso8601String()]);
        }

        return response()->json(['ok' => true]);
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
                    $labels[] = app_date($date, app()->getLocale() === 'fa' ? '%d %B' : 'M j');
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
                    $labels[] = app_date($date, 'j');
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
                    $labels[] = app_date($date, app()->getLocale() === 'fa' ? '%B' : 'M');
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
