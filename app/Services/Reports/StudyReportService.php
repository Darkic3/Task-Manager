<?php

namespace App\Services\Reports;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\Carbon;

/**
 * Study performance for a window (کنکور ارشد): pages read (= task weight,
 * see Task::WEIGHT_UNIT_PAGES) and focused minutes, broken down per chapter
 * (every project that owns tasks directly), plus daily-goal adherence.
 *
 * Conventions:
 * - completion day = completed_at, falling back to updated_at (old rows
 *   predate the completed_at column; a backfill also covers them).
 * - time is attributed to the entry's project, or to its task's project
 *   when the entry itself has no project.
 * - labels are hierarchy paths ("Book / Chapter") so same-named chapters
 *   in different books never merge.
 */
final class StudyReportService
{
    public static function summarize(int $userId, Carbon $from, Carbon $to): array
    {
        $fromKey = $from->toDateString();
        $toKey = $to->toDateString();
        $todayKey = now()->toDateString();

        $paths = Project::pathMap($userId);

        $projects = Project::where('user_id', $userId)
            ->get(['id', 'name', 'parent_id', 'daily_goal_value', 'daily_goal_unit']);

        $parentOf = [];
        $goalOf = [];
        foreach ($projects as $p) {
            $parentOf[(int) $p->id] = $p->parent_id !== null ? (int) $p->parent_id : null;
            if ($p->daily_goal_value !== null && (int) $p->daily_goal_value > 0) {
                $goalOf[(int) $p->id] = [
                    'value' => (int) $p->daily_goal_value,
                    'unit' => in_array($p->daily_goal_unit, Project::DAILY_GOAL_UNITS, true) ? $p->daily_goal_unit : 'tasks',
                ];
            }
        }

        $depthOf = function (int $id) use ($parentOf): int {
            $depth = 0;
            $parent = $parentOf[$id] ?? null;
            $guard = 0;
            while ($parent !== null && isset($parentOf[$parent]) && $guard++ < 50) {
                $depth++;
                $parent = $parentOf[$parent];
            }

            return $depth;
        };

        // Descendant id sets (in-memory, single data load).
        $childrenOf = [];
        foreach ($parentOf as $id => $parent) {
            if ($parent !== null) {
                $childrenOf[$parent][] = $id;
            }
        }
        $descendantsOf = function (int $id) use ($childrenOf): array {
            $ids = [$id];
            $stack = $childrenOf[$id] ?? [];
            $guard = 0;
            while (! empty($stack) && $guard++ < 1000) {
                $child = array_pop($stack);
                if (in_array($child, $ids, true)) {
                    continue;
                }
                $ids[] = $child;
                foreach ($childrenOf[$child] ?? [] as $grand) {
                    $stack[] = $grand;
                }
            }

            return $ids;
        };

        $tasks = Task::where('user_id', $userId)
            ->get(['id', 'project_id', 'status', 'weight', 'completed_at', 'updated_at', 'created_at', 'due_date']);

        $entries = TimeEntry::where('user_id', $userId)
            ->where('status', TimeEntry::STATUS_STOPPED)
            ->whereBetween('started_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['id', 'project_id', 'task_id', 'started_at', 'duration_seconds']);

        $taskProjectOf = [];
        foreach ($tasks as $t) {
            $taskProjectOf[(int) $t->id] = $t->project_id !== null ? (int) $t->project_id : null;
        }

        $dayKeys = self::dayKeys($from, $to);
        $perDayPages = array_fill_keys($dayKeys, 0.0);
        $perDayMinutes = array_fill_keys($dayKeys, 0);

        $pagesCompleted = 0.0;
        $tasksCompleted = 0;

        // Per-project accumulators (direct tasks only — no double counting).
        $acc = [];
        $touch = function (int $pid) use (&$acc, $paths, $depthOf): array {
            if (! isset($acc[$pid])) {
                $acc[$pid] = [
                    'project_id' => $pid,
                    'path' => $paths[$pid] ?? __('No project'),
                    'depth' => isset($paths[$pid]) ? $depthOf($pid) : 0,
                    'tasks_total' => 0, 'tasks_done' => 0,
                    'pages_total' => 0.0, 'pages_done' => 0.0,
                    'pages_in_range' => 0.0, 'completed_in_range' => 0,
                    'minutes_in_range' => 0, 'overdue' => 0,
                ];
            }

            return $acc[$pid];
        };

        foreach ($tasks as $t) {
            $pid = $t->project_id !== null ? (int) $t->project_id : null;
            $weight = max(0.0, (float) ($t->weight ?? 1));
            $done = $t->status === 'completed';

            if ($pid !== null) {
                $row = $touch($pid);
                $row['tasks_total']++;
                $row['pages_total'] += $weight;
                if ($done) {
                    $row['tasks_done']++;
                    $row['pages_done'] += $weight;
                } elseif ($t->due_date && $t->due_date->toDateString() < $todayKey) {
                    $row['overdue']++;
                }
                $acc[$pid] = $row;
            }

            if ($done) {
                $day = self::completionDay($t);
                if ($day !== null && isset($perDayPages[$day])) {
                    $perDayPages[$day] += $weight;
                    $pagesCompleted += $weight;
                    $tasksCompleted++;
                    if ($pid !== null) {
                        $acc[$pid]['pages_in_range'] += $weight;
                        $acc[$pid]['completed_in_range']++;
                    }
                }
            }
        }

        $minutesTotal = 0;
        foreach ($entries as $e) {
            $day = $e->started_at instanceof Carbon
                ? $e->started_at->toDateString()
                : Carbon::parse($e->started_at)->toDateString();
            if (! isset($perDayMinutes[$day])) {
                continue;
            }
            $seconds = (int) ($e->duration_seconds ?? 0);
            $perDayMinutes[$day] += $seconds;
            $minutesTotal += $seconds;

            $pid = $e->project_id !== null
                ? (int) $e->project_id
                : ($e->task_id !== null ? ($taskProjectOf[(int) $e->task_id] ?? null) : null);
            if ($pid !== null && isset($paths[$pid])) {
                $touch($pid);
                $acc[$pid]['minutes_in_range'] += $seconds;
            }
        }

        // Daily-goal adherence: days in range (up to today) where the goal
        // project + subtree hit its target. Computed from the loaded rows.
        $goalDaysMet = 0;
        $goalDaysTotal = 0;
        if ($goalOf !== []) {
            $elapsedDays = array_values(array_filter($dayKeys, fn ($d) => $d <= $todayKey));
            $goalDaysTotal = count($elapsedDays);

            // Per-day completed weight/count per project (direct tasks).
            $dayTaskCount = [];
            $dayTaskPages = [];
            foreach ($tasks as $t) {
                if ($t->status !== 'completed' || $t->project_id === null) {
                    continue;
                }
                $day = self::completionDay($t);
                if ($day === null || ! in_array($day, $elapsedDays, true)) {
                    continue;
                }
                $pid = (int) $t->project_id;
                $dayTaskCount[$day][$pid] = ($dayTaskCount[$day][$pid] ?? 0) + 1;
                $dayTaskPages[$day][$pid] = ($dayTaskPages[$day][$pid] ?? 0.0) + max(0.0, (float) ($t->weight ?? 1));
            }
            $dayMinutes = [];
            foreach ($entries as $e) {
                $day = $e->started_at instanceof Carbon
                    ? $e->started_at->toDateString()
                    : Carbon::parse($e->started_at)->toDateString();
                if (! in_array($day, $elapsedDays, true)) {
                    continue;
                }
                $pid = $e->project_id !== null
                    ? (int) $e->project_id
                    : ($e->task_id !== null ? ($taskProjectOf[(int) $e->task_id] ?? null) : null);
                if ($pid === null) {
                    continue;
                }
                $dayMinutes[$day][$pid] = ($dayMinutes[$day][$pid] ?? 0) + (int) ($e->duration_seconds ?? 0);
            }

            foreach ($elapsedDays as $day) {
                $met = false;
                foreach ($goalOf as $gid => $goal) {
                    $ids = $descendantsOf($gid);
                    if ($goal['unit'] === 'minutes') {
                        $mins = 0;
                        foreach ($ids as $id) {
                            $mins += $dayMinutes[$day][$id] ?? 0;
                        }
                        if (floor($mins / 60) >= $goal['value']) {
                            $met = true;
                            break;
                        }
                    } elseif ($goal['unit'] === 'pages') {
                        $pages = 0.0;
                        foreach ($ids as $id) {
                            $pages += $dayTaskPages[$day][$id] ?? 0.0;
                        }
                        if ($pages >= $goal['value']) {
                            $met = true;
                            break;
                        }
                    } else {
                        $count = 0;
                        foreach ($ids as $id) {
                            $count += $dayTaskCount[$day][$id] ?? 0;
                        }
                        if ($count >= $goal['value']) {
                            $met = true;
                            break;
                        }
                    }
                }
                if ($met) {
                    $goalDaysMet++;
                }
            }
        }

        $rows = array_values($acc);
        foreach ($rows as &$row) {
            $row['progress'] = $row['pages_total'] > 0
                ? round($row['pages_done'] / $row['pages_total'] * 100, 1)
                : 0.0;
            $row['pages_total'] = round($row['pages_total'], 1);
            $row['pages_done'] = round($row['pages_done'], 1);
            $row['pages_in_range'] = round($row['pages_in_range'], 1);
        }
        unset($row);

        // Needs attention first: overdue chapters, then most pages remaining.
        usort($rows, function ($a, $b) {
            if ($a['overdue'] !== $b['overdue']) {
                return $b['overdue'] <=> $a['overdue'];
            }
            $remA = $a['pages_total'] - $a['pages_done'];
            $remB = $b['pages_total'] - $b['pages_done'];

            return $remB <=> $remA;
        });
        $rows = array_slice($rows, 0, 50);

        return [
            'pages_completed' => round($pagesCompleted, 1),
            'tasks_completed' => $tasksCompleted,
            'minutes_total' => $minutesTotal,
            'per_day_pages' => $perDayPages,
            'per_day_pages_max' => max(1.0, max($perDayPages)),
            'per_day_minutes' => $perDayMinutes,
            'per_day_minutes_max' => max(1, max($perDayMinutes)),
            'goal_days_met' => $goalDaysMet,
            'goal_days_total' => $goalDaysTotal,
            'goal_rate' => $goalDaysTotal > 0 ? (int) round($goalDaysMet / $goalDaysTotal * 100) : 0,
            'rows' => $rows,
        ];
    }

    private static function completionDay(Task $task): ?string
    {
        $at = $task->completed_at ?? $task->updated_at ?? $task->created_at;
        if ($at === null) {
            return null;
        }

        return $at instanceof Carbon ? $at->toDateString() : Carbon::parse($at)->toDateString();
    }

    /** @return array<string> */
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
