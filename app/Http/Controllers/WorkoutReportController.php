<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\WorkoutSession;
use App\Models\WorkoutSetLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WorkoutReportController extends Controller
{
    public function index(Request $request)
    {
        $date = $this->parseDate($request->input('date'));
        $mode = $request->input('view') === 'day' ? 'day' : 'week';
        $start = $mode === 'day' ? $date->copy() : $date->copy()->startOfWeek(Carbon::SATURDAY);
        $end = $mode === 'day' ? $date->copy() : $start->copy()->addDays(6);
        $sessions = $this->sessionsBetween($start, $end);
        $plannedByDate = $this->plannedDaysBetween($start, $end);

        $days = collect(range(0, $mode === 'day' ? 0 : 6))->map(function (int $offset) use ($start, $sessions, $plannedByDate): array {
            $day = $start->copy()->addDays($offset);
            $daySessions = $sessions->filter(fn (WorkoutSession $session) => $session->workout_date->isSameDay($day))->values();

            return [
                'date' => $day,
                'sessions' => $daySessions,
                'summary' => $this->summarizeSessions($daySessions),
                'scheduled' => $plannedByDate[$day->toDateString()] ?? 0,
            ];
        });

        $summary = $this->summarizeSessions($sessions);
        $summary['scheduled'] = $days->sum('scheduled');

        return view('workouts.reports.index', [
            'mode' => $mode,
            'date' => $date,
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'sessions' => $sessions,
            'summary' => $summary,
        ]);
    }

    public function exercise(Exercise $exercise)
    {
        abort_if($exercise->user_id !== null && $exercise->user_id !== auth()->id(), 403);

        $sets = WorkoutSetLog::query()
            ->with(['exerciseLog.session.day.plan', 'exerciseLog.workoutExercise'])
            ->whereHas('exerciseLog.workoutExercise', fn ($query) => $query->where('exercise_id', $exercise->id))
            ->whereHas('exerciseLog.session', fn ($query) => $query->where('user_id', auth()->id())->where('status', WorkoutSession::COMPLETED))
            ->orderByDesc('created_at')
            ->get();

        $byDate = $sets->groupBy(fn (WorkoutSetLog $set) => $set->exerciseLog->session->workout_date->toDateString())
            ->map(fn ($rows, $date) => [
                'date' => $date,
                'reps' => round($rows->sum(fn ($row) => (float) $row->reps), 2),
                'volume' => round($rows->sum(fn ($row) => (float) $row->reps * (float) ($row->weight ?? 0)), 2),
                'max_weight' => $rows->max(fn ($row) => (float) ($row->weight ?? 0)),
                'avg_rir' => $rows->whereNotNull('rir')->count() ? round($rows->whereNotNull('rir')->avg('rir'), 1) : null,
                'sets' => $rows->count(),
            ])->sortByDesc('date')->values();

        return view('workouts.reports.exercise', [
            'exercise' => $exercise,
            'sessions' => $byDate,
            'stats' => [
                'sessions' => $byDate->count(),
                'sets' => $sets->count(),
                'reps' => round($sets->sum(fn ($row) => (float) $row->reps), 2),
                'volume' => round($sets->sum(fn ($row) => (float) $row->reps * (float) ($row->weight ?? 0)), 2),
                'best_weight' => $sets->max(fn ($row) => (float) ($row->weight ?? 0)) ?: null,
                'best_reps' => $sets->max(fn ($row) => (float) ($row->reps ?? 0)) ?: null,
            ],
        ]);
    }

    private function sessionsBetween(Carbon $start, Carbon $end)
    {
        return auth()->user()->workoutSessions()
            ->whereBetween('workout_date', [$start->toDateString(), $end->toDateString()])
            ->with(['day.plan', 'day.exercises', 'exerciseLogs.workoutExercise.exercise', 'exerciseLogs.setLogs'])
            ->orderBy('workout_date')
            ->orderBy('started_at')
            ->get();
    }

    private function plannedDaysBetween(Carbon $start, Carbon $end): array
    {
        $plans = auth()->user()->workoutPlans()
            ->with('days')
            ->where(function ($query) use ($end): void {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $end->toDateString());
            })
            ->latest('start_date')
            ->latest()
            ->get();
        $planned = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $plan = $plans->first(fn ($candidate) => ! $candidate->start_date || $candidate->start_date->lte($date));
            $weekday = strtolower($date->format('l'));
            $planned[$date->toDateString()] = $plan?->days
                ->where('weekday', $weekday)
                ->whereIn('type', ['training', 'recovery'])
                ->count() ?? 0;
        }

        return $planned;
    }

    private function summarizeSessions($sessions): array
    {
        $exerciseLogs = $sessions->flatMap->exerciseLogs;
        $sets = $exerciseLogs->flatMap->setLogs;
        $completedSets = $sets->where('completed', true);
        $painSets = $sets->filter(fn ($set) => (int) ($set->pain_level ?? 0) > 0);
        $rirSets = $completedSets->whereNotNull('rir');

        return [
            'sessions' => $sessions->count(),
            'completed_sessions' => $sessions->where('status', WorkoutSession::COMPLETED)->count(),
            'exercises' => $exerciseLogs->count(),
            'completed_exercises' => $exerciseLogs->where('completed', true)->count(),
            'sets' => $completedSets->count(),
            'reps' => round($completedSets->sum(fn ($set) => (float) ($set->reps ?? 0)), 2),
            'volume' => round($completedSets->sum(fn ($set) => (float) ($set->reps ?? 0) * (float) ($set->weight ?? 0)), 2),
            'avg_rir' => $rirSets->count() ? round($rirSets->avg('rir'), 1) : null,
            'pain_events' => $painSets->count(),
            'minutes' => $sessions->sum(fn (WorkoutSession $session) => $session->durationMinutes() ?? 0),
        ];
    }

    private function parseDate(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
