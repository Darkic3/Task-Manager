<?php

namespace App\Services\Reports;

use App\Models\WorkoutSession;
use App\Models\WorkoutSetLog;
use Carbon\Carbon;

/**
 * Workout performance for a window: sessions, minutes, sets, reps, volume.
 */
final class WorkoutReportService
{
    public static function summarize(int $userId, Carbon $from, Carbon $to): array
    {
        $fromKey = $from->toDateString();
        $toKey = $to->toDateString();

        $sessions = WorkoutSession::where('user_id', $userId)
            ->whereBetween('workout_date', [$fromKey, $toKey])
            ->get();

        $completed = $sessions->where('status', WorkoutSession::COMPLETED);
        $minutes = $completed->sum(fn ($s) => $s->durationMinutes() ?? 0);

        $setRows = WorkoutSetLog::query()
            ->whereHas('exerciseLog.session', fn ($q) => $q
                ->where('user_id', $userId)
                ->where('status', WorkoutSession::COMPLETED)
                ->whereBetween('workout_date', [$fromKey, $toKey]))
            ->get();

        $doneSets = $setRows->where('completed', true);
        $reps = round($doneSets->sum(fn ($s) => (float) ($s->reps ?? 0)), 1);
        $volume = round($doneSets->sum(fn ($s) => (float) ($s->reps ?? 0) * (float) ($s->weight ?? 0)), 1);

        $perDay = array_fill_keys(self::dayKeys($from, $to), 0);
        foreach ($completed as $s) {
            $key = $s->workout_date instanceof Carbon
                ? $s->workout_date->toDateString()
                : Carbon::parse($s->workout_date)->toDateString();
            if (array_key_exists($key, $perDay)) {
                $perDay[$key]++;
            }
        }

        return [
            'sessions' => $sessions->count(),
            'completed' => $completed->count(),
            'minutes' => $minutes,
            'sets' => $doneSets->count(),
            'reps' => $reps,
            'volume' => $volume,
            'per_day' => $perDay,
            'per_day_max' => max(1, max($perDay)),
        ];
    }

    private static function dayKeys(Carbon $from, Carbon $to): array
    {
        $keys = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $keys[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $keys;
    }
}
