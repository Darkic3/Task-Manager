<?php

namespace App\Services\Reports;

use App\Models\TimeEntry;
use Carbon\Carbon;

/**
 * Tracked-time totals for a window: stopped sessions started in range,
 * split by project, plus a daily series.
 */
final class TimeReportService
{
    public static function summarize(int $userId, Carbon $from, Carbon $to): array
    {
        $entries = TimeEntry::where('user_id', $userId)
            ->where('status', TimeEntry::STATUS_STOPPED)
            ->whereBetween('started_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('project:id,name')
            ->get();

        $total = (int) $entries->sum('duration_seconds');

        $byProject = $entries
            ->groupBy(fn ($e) => $e->project?->name ?? 'No project')
            ->map(fn ($g) => (int) $g->sum('duration_seconds'))
            ->sortDesc()
            ->take(8)
            ->all();

        $perDay = array_fill_keys(self::dayKeys($from, $to), 0);
        foreach ($entries as $e) {
            $key = $e->started_at instanceof Carbon
                ? $e->started_at->toDateString()
                : Carbon::parse($e->started_at)->toDateString();
            if (array_key_exists($key, $perDay)) {
                $perDay[$key] += (int) $e->duration_seconds;
            }
        }

        return [
            'total' => $total,
            'sessions' => $entries->count(),
            'by_project' => $byProject,
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
