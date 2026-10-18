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
                            'side' => $row->side ?? 'both',
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
        $side = isset($data['side']) && $data['side'] !== '' ? $data['side'] : 'both';

        $exerciseLog = WorkoutExerciseLog::firstOrCreate(
            ['workout_session_id' => $workoutSession->id, 'workout_exercise_id' => $exercise->id],
            ['completed' => false]
        );

        $exerciseLog->update([
            'note' => $data['exercise_note'] ?? $exerciseLog->note,
            'skip_reason' => $data['skip_reason'] ?? $exerciseLog->skip_reason,
        ]);

        // PR check runs on the incoming values (best weight across the user's
        // OTHER sessions) so the flag can be persisted with the set itself.
        // A re-save recomputes it, so lowering the weight clears a stale PR.
        $exerciseId = $exercise->exercise_id;
        $previousBestWeight = (float) (WorkoutSetLog::whereHas('exerciseLog', function ($q) use ($exerciseId, $workoutSession) {
            $q->whereHas('workoutExercise', fn ($we) => $we->where('exercise_id', $exerciseId))
              ->whereHas('session', fn ($s) => $s->where('user_id', auth()->id())->where('id', '!=', $workoutSession->id));
        })->where('side', $side)->max('weight') ?? 0);

        $incomingWeight = (float) ($data['weight'] ?? 0);
        $isPr = ($incomingWeight > 0 && $previousBestWeight > 0 && $incomingWeight > $previousBestWeight);

        $set = $exerciseLog->setLogs()->updateOrCreate(
            ['set_number' => $data['set_number'], 'side' => $side],
            collect($data)->only([
                'reps', 'weight', 'duration_seconds', 'rir', 'form_rating',
                'pain_level', 'completed', 'note',
            ])->merge([
                'completed' => filter_var($data['completed'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'is_pr' => $isPr,
                'side' => $side,
            ])->all()
        );

        $targetSetRows = $exercise->requiredSetCount();
        $sideMode = $exercise->side_mode ?: 'bilateral';

        $completedSetRows = match ($sideMode) {
            'per_side' => WorkoutSetLog::query()
                ->where('workout_exercise_log_id', $exerciseLog->id)
                ->where('completed', true)
                ->whereIn('side', ['left', 'right'])
                ->groupBy('set_number')
                ->havingRaw('COUNT(DISTINCT side) = 2')
                ->count(),
            default => WorkoutSetLog::query()
                ->where('workout_exercise_log_id', $exerciseLog->id)
                ->where('completed', true)
                ->select('set_number')
                ->distinct()
                ->count(),
        };
        $exerciseCompleted = ! empty($data['skip_reason']) ? false : $completedSetRows >= $targetSetRows;
        $exerciseLog->update([
            'completed' => $exerciseCompleted,
        ]);

        $weight = (float) ($set->weight ?? 0);
        $reps = (float) ($set->reps ?? 0);
        $estimated1RM = ($reps > 0 && $weight > 0) ? round($weight * (1 + ($reps / 30)), 1) : 0;

        return response()->json([
            'ok' => true,
            'set' => $set->fresh(),
            'exercise_completed' => $exerciseCompleted,
            'completed_sets' => $completedSetRows,
            'target_sets' => $targetSetRows,
            'is_pr' => $isPr,
            'estimated_1rm' => $estimated1RM,
            'previous_best_weight' => $previousBestWeight,
        ]);
    }

    public function saveBodyweight(Request $request, WorkoutSession $workoutSession): JsonResponse
    {
        $this->authorizeSession($workoutSession);

        $data = $request->validate([
            'bodyweight_kg' => ['nullable', 'numeric', 'min:0', 'max:600'],
        ]);

        $workoutSession->update([
            'bodyweight_kg' => isset($data['bodyweight_kg']) ? (float) $data['bodyweight_kg'] : null,
            'bodyweight_recorded_at' => $data['bodyweight_kg'] !== null ? now() : null,
        ]);

        return response()->json([
            'ok' => true,
            'bodyweight_kg' => $workoutSession->bodyweight_kg,
        ]);
    }

    public function saveExerciseNote(Request $request, WorkoutSession $workoutSession): JsonResponse
    {
        $this->authorizeSession($workoutSession);
        $data = $request->validate([
            'workout_exercise_id' => ['required', 'integer', 'exists:workout_exercises,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $exercise = $workoutSession->day()->firstOrFail()->exercises()->whereKey($data['workout_exercise_id'])->firstOrFail();

        $exerciseLog = WorkoutExerciseLog::firstOrCreate(
            ['workout_session_id' => $workoutSession->id, 'workout_exercise_id' => $exercise->id],
            ['completed' => false]
        );

        $note = trim((string) ($data['note'] ?? ''));
        $exerciseLog->update(['note' => $note !== '' ? $note : null]);

        return response()->json([
            'ok' => true,
            'note' => $exerciseLog->note,
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
