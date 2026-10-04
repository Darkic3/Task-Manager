<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinishWorkoutSessionRequest;
use App\Http\Requests\SaveWorkoutSetRequest;
use App\Models\WorkoutDay;
use App\Models\WorkoutExerciseLog;
use App\Models\WorkoutSession;
use App\Models\WorkoutSetLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkoutSessionController extends Controller
{
    public function start(Request $request, WorkoutDay $workoutDay): RedirectResponse
    {
        $this->authorizeDay($workoutDay);
        $data = $request->validate(['date' => ['nullable', 'date']]);
        $date = isset($data['date']) ? Carbon::parse($data['date'])->toDateString() : now()->toDateString();

        $session = WorkoutSession::firstOrCreate(
            ['workout_day_id' => $workoutDay->id, 'workout_date' => $date],
            ['user_id' => auth()->id(), 'status' => WorkoutSession::IN_PROGRESS, 'started_at' => now()]
        );

        // Stamp the start the moment the session opens; never overwrite an
        // in-progress start, and never resurrect a finished one.
        if (! $session->started_at && ! $session->ended_at) {
            $session->update(['started_at' => now()]);
        }

        return redirect()->route('workouts.sessions.show', $session);
    }

    public function show(WorkoutSession $workoutSession)
    {
        $this->authorizeSession($workoutSession);
        $workoutSession->load([
            'day.plan',
            'day.exercises.exercise',
            'exerciseLogs.setLogs',
        ]);

        $exerciseIds = $workoutSession->day->exercises->pluck('exercise_id')->unique()->values();
        $previousRows = WorkoutSetLog::query()
            ->whereHas('exerciseLog', function ($query) use ($exerciseIds, $workoutSession): void {
                $query->whereHas('workoutExercise', fn ($exerciseQuery) => $exerciseQuery->whereIn('exercise_id', $exerciseIds))
                    ->whereHas('session', function ($sessionQuery) use ($workoutSession): void {
                        $sessionQuery->where('user_id', auth()->id())
                            ->where('status', WorkoutSession::COMPLETED)
                            ->where('id', '!=', $workoutSession->id);
                    });
            })
            ->with(['exerciseLog.workoutExercise', 'exerciseLog.session'])
            ->get()
            ->sortByDesc(fn (WorkoutSetLog $row) => $row->exerciseLog->session->workout_date->timestamp);

        $previousByExercise = $previousRows
            ->groupBy(fn (WorkoutSetLog $row) => $row->exerciseLog->workoutExercise->exercise_id)
            ->map(function ($rows): array {
                $latestDate = $rows->first()->exerciseLog->session->workout_date->toDateString();

                return [
                    'date' => $latestDate,
                    'sets' => $rows->filter(fn ($row) => $row->exerciseLog->session->workout_date->toDateString() === $latestDate)
                        ->sortBy('set_number')
                        ->map(fn ($row) => [
                            'set_number' => $row->set_number,
                            'reps' => $row->reps,
                            'weight' => $row->weight,
                            'duration_seconds' => $row->duration_seconds,
                            'rir' => $row->rir,
                        ])->values()->all(),
                ];
            });

        $logsByExercise = $workoutSession->exerciseLogs->keyBy('workout_exercise_id');

        // Anchor the live timer to the server clock so a skewed client clock
        // cannot inflate or deflate the recorded duration.
        $timerStartedAtMs = $workoutSession->started_at?->getTimestampMs() ?? 0;
        $serverNowMs = now()->getTimestampMs();
        $finishedSeconds = $workoutSession->ended_at ? $workoutSession->elapsedSeconds() : null;

        return view('workouts.sessions.show', compact(
            'workoutSession',
            'previousByExercise',
            'logsByExercise',
            'timerStartedAtMs',
            'serverNowMs',
            'finishedSeconds',
        ));
    }

    public function saveSet(SaveWorkoutSetRequest $request, WorkoutSession $workoutSession): JsonResponse
    {
        $this->authorizeSession($workoutSession);
        $data = $request->validated();
        $exercise = $workoutSession->day()->firstOrFail()->exercises()->whereKey($data['workout_exercise_id'])->firstOrFail();

        $exerciseLog = WorkoutExerciseLog::firstOrCreate(
            ['workout_session_id' => $workoutSession->id, 'workout_exercise_id' => $exercise->id],
            ['completed' => false]
        );

        $exerciseLog->update([
            'note' => $data['exercise_note'] ?? $exerciseLog->note,
            'skip_reason' => $data['skip_reason'] ?? $exerciseLog->skip_reason,
        ]);

        $set = $exerciseLog->setLogs()->updateOrCreate(
            ['set_number' => $data['set_number']],
            collect($data)->only([
                'reps', 'weight', 'duration_seconds', 'rir', 'form_rating',
                'pain_level', 'completed', 'note',
            ])->merge(['completed' => filter_var($data['completed'] ?? true, FILTER_VALIDATE_BOOLEAN)])->all()
        );

        $targetSets = $exercise->requiredSetCount();
        $completedSets = $exerciseLog->setLogs()->where('completed', true)->count();
        $exerciseLog->update([
            'completed' => ! empty($data['skip_reason']) ? false : $completedSets >= $targetSets,
        ]);

        $weight = (float) ($set->weight ?? 0);
        $reps = (float) ($set->reps ?? 0);
        $estimated1RM = ($reps > 0 && $weight > 0) ? round($weight * (1 + ($reps / 30)), 1) : 0;

        $exerciseId = $exercise->exercise_id;
        $previousBestWeight = (float) (WorkoutSetLog::whereHas('exerciseLog', function ($q) use ($exerciseId, $workoutSession) {
            $q->whereHas('workoutExercise', fn ($we) => $we->where('exercise_id', $exerciseId))
              ->whereHas('session', fn ($s) => $s->where('user_id', auth()->id())->where('id', '!=', $workoutSession->id));
        })->max('weight') ?? 0);

        $isPr = ($weight > 0 && $previousBestWeight > 0 && $weight > $previousBestWeight);

        return response()->json([
            'ok' => true,
            'set' => $set->fresh(),
            'exercise_completed' => $exerciseLog->completed,
            'completed_sets' => $completedSets,
            'target_sets' => $targetSets,
            'is_pr' => $isPr,
            'estimated_1rm' => $estimated1RM,
            'previous_best_weight' => $previousBestWeight,
        ]);
    }

    public function finish(FinishWorkoutSessionRequest $request, WorkoutSession $workoutSession): RedirectResponse
    {
        $this->authorizeSession($workoutSession);

        $endedAt = $workoutSession->ended_at ?? now();
        $startedAt = $workoutSession->started_at ?? $endedAt;
        $durationSeconds = (int) max(0, $endedAt->diffInSeconds($startedAt, true));

        $workoutSession->update([
            ...$request->validated(),
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
        ]);

        return redirect()
            ->route('workouts.sessions.show', $workoutSession)
            ->with('success', __('Workout session saved.'));
    }

    private function authorizeDay(WorkoutDay $day): void
    {
        abort_if($day->plan()->where('user_id', auth()->id())->doesntExist(), 403);
    }

    private function authorizeSession(WorkoutSession $session): void
    {
        abort_if($session->user_id !== auth()->id(), 403);
    }
}
