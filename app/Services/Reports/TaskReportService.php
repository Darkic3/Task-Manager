<?php

namespace App\Services\Reports;

use App\Models\Task;
use Carbon\Carbon;

/**
 * Task performance for a window: created vs completed, completion rate,
 * overdue snapshot, average completion time, and breakdowns.
 */
final class TaskReportService
{
    public static function summarize(int $userId, Carbon $from, Carbon $to): array
    {
        $fromKey = $from->toDateString();
        $toKey = $to->toDateString();

        $created = Task::where('user_id', $userId)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->count();

        $completedRows = Task::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['id', 'project_id', 'priority', 'status', 'created_at', 'completed_at', 'due_date']);

        $completed = $completedRows->count();
        $rate = $created > 0 ? (int) round($completed / $created * 100) : 0;

        $avgHours = null;
        $withBoth = $completedRows->filter(fn ($t) => $t->created_at && $t->completed_at);
        if ($withBoth->isNotEmpty()) {
            $avgHours = round($withBoth->avg(fn ($t) => $t->created_at->diffInMinutes($t->completed_at, true) / 60), 1);
        }

        $overdue = Task::where('user_id', $userId)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        $byProject = Task::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('project:id,name')
            ->get()
            ->groupBy(fn ($t) => $t->project?->name ?? 'No project')
            ->map->count()
            ->sortDesc()
            ->take(8)
            ->all();

        $byPriority = Task::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('priority, COUNT(*) as c')
            ->groupBy('priority')
            ->pluck('c', 'priority')
            ->all();

        $byStatus = Task::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        // Daily series: completed per day in the window.
        $perDay = array_fill_keys(self::dayKeys($from, $to), 0);
        foreach ($completedRows as $t) {
            $key = $t->completed_at instanceof Carbon
                ? $t->completed_at->toDateString()
                : Carbon::parse($t->completed_at)->toDateString();
            if (array_key_exists($key, $perDay)) {
                $perDay[$key]++;
            }
        }

        return [
            'created' => $created,
            'completed' => $completed,
            'rate' => $rate,
            'avg_hours' => $avgHours,
            'overdue_now' => $overdue,
            'by_project' => $byProject,
            'by_priority' => $byPriority,
            'by_status' => $byStatus,
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
