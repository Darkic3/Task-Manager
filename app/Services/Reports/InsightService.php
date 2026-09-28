<?php

namespace App\Services\Reports;

use App\Models\RoutineViolation;
use App\Models\Task;
use Carbon\Carbon;

/**
 * Automatic insights for the Reports page: each entry is
 * ['icon', 'tone' => good|warn|bad|info, 'title', 'body'].
 * Tones drive the card accent; the list is ordered most-actionable first.
 */
final class InsightService
{
    public static function generate(int $userId, ReportRange $range, array $data): array
    {
        $out = [];
        $period = match ($range->preset) {
            'today' => 'today',
            'month' => 'this month',
            'custom' => 'the selected period',
            default => 'this week',
        };

        $tasks = $data['tasks'];
        $routines = $data['routines'];
        $avoid = $data['avoid'];
        $workouts = $data['workouts'];
        $time = $data['time'];
        $deltas = $data['deltas'];

        // 1. Overdue tasks need attention first.
        if ($tasks['overdue_now'] > 0) {
            $out[] = [
                'icon' => 'bi-exclamation-triangle', 'tone' => 'bad',
                'title' => $tasks['overdue_now'].' overdue task'.($tasks['overdue_now'] === 1 ? '' : 's'),
                'body' => 'Clear these before planning anything new.',
            ];
        }

        // 2. Slip peak window (3-hour bucket with the most slips).
        $peak = self::slipPeakWindow($userId, $range);
        if ($peak) {
            $out[] = [
                'icon' => 'bi-clock-history', 'tone' => 'warn',
                'title' => 'Slips cluster between '.$peak['label'],
                'body' => $peak['count'].' slip'.($peak['count'] === 1 ? '' : 's').' — plan a replacement ritual for that slot.',
            ];
        }

        // 3. Struggling routine (lowest adherence under 50% with real occurrences).
        $weak = collect($routines['rows'] ?? [])
            ->filter(fn ($r) => ($r['occurrences'] ?? 0) > 0 && ($r['rate'] ?? 100) < 50)
            ->sortBy('rate')
            ->first();
        if ($weak) {
            $out[] = [
                'icon' => 'bi-arrow-down-circle', 'tone' => 'warn',
                'title' => "‘{$weak['title']}’ is struggling at {$weak['rate']}%",
                'body' => $weak['is_avoid'] ? 'Review its triggers and slip slots below.' : 'Consider shrinking it or moving it to a better slot.',
            ];
        }

        // 4. Task completion trend vs previous window.
        if ($tasks['created'] > 0 || $tasks['completed'] > 0) {
            $trend = $deltas['task_rate'];
            if ($trend > 0) {
                $out[] = [
                    'icon' => 'bi-graph-up-arrow', 'tone' => 'good',
                    'title' => "Completion rate up {$trend}pp {$period}",
                    'body' => "{$tasks['completed']} of {$tasks['created']} tasks done.",
                ];
            } elseif ($trend < 0) {
                $out[] = [
                    'icon' => 'bi-graph-down-arrow', 'tone' => 'warn',
                    'title' => 'Completion rate down '.abs($trend)."pp {$period}",
                    'body' => "{$tasks['completed']} of {$tasks['created']} tasks done — protect focus time.",
                ];
            }
        }

        // 5. Slip trend (fewer is better — inverted tone).
        if ($avoid['routines'] > 0 && ($avoid['slip_total'] > 0 || $deltas['slips'] !== 0)) {
            $trend = $deltas['slips'];
            if ($trend < 0) {
                $out[] = [
                    'icon' => 'bi-shield-check', 'tone' => 'good',
                    'title' => 'Slips down by '.abs($trend)." {$period}",
                    'body' => "{$avoid['clean_days']} clean days and counting.",
                ];
            } elseif ($trend > 0) {
                $out[] = [
                    'icon' => 'bi-shield-exclamation', 'tone' => 'bad',
                    'title' => "Slips up by {$trend} {$period}",
                    'body' => 'Check the peak slot and top triggers below.',
                ];
            } elseif ($avoid['slip_total'] === 0 && $avoid['occurrences'] > 0) {
                $out[] = [
                    'icon' => 'bi-shield-fill-check', 'tone' => 'good',
                    'title' => "Perfectly clean {$period}",
                    'body' => "{$avoid['clean_days']} clean days, zero slips. 🛡️",
                ];
            }
        }

        // 6. Clean-streak milestone.
        if (($routines['best_clean_streak'] ?? 0) >= 3) {
            $out[] = [
                'icon' => 'bi-fire', 'tone' => 'good',
                'title' => $routines['best_clean_streak'].'-day clean streak',
                'body' => 'Longest abstinence run in the window — keep it alive.',
            ];
        }

        // 7. Cravings managed without slipping.
        if ($avoid['cravings'] > 0 && $avoid['slip_total'] === 0) {
            $out[] = [
                'icon' => 'bi-hand-thumbs-up', 'tone' => 'good',
                'title' => "All {$avoid['cravings']} cravings managed",
                'body' => 'Urges logged, none turned into slips.',
            ];
        }

        // 8. Project spotlight: most time + overdue load.
        $spot = self::projectSpotlight($userId);
        if ($spot) {
            $out[] = [
                'icon' => 'bi-folder', 'tone' => $spot['overdue'] > 0 ? 'warn' : 'info',
                'title' => "Spotlight: {$spot['name']}",
                'body' => trim("{$spot['time']} tracked"
                    .($spot['overdue'] > 0 ? " · {$spot['overdue']} overdue" : ''), ' ·'),
            ];
        }

        // 9. Workout consistency.
        if ($workouts['completed'] > 0 || $deltas['workouts'] !== 0) {
            $trend = $deltas['workouts'];
            $out[] = [
                'icon' => 'bi-heart-pulse', 'tone' => $trend < 0 ? 'warn' : 'good',
                'title' => "{$workouts['completed']} workouts {$period}",
                'body' => trim("{$workouts['minutes']} min"
                    .($trend !== 0 ? ' · '.($trend > 0 ? '+' : '').$trend.' vs prev' : ''), ' ·'),
            ];
        }

        // 10. Best routine.
        $best = collect($routines['rows'] ?? [])
            ->filter(fn ($r) => ($r['occurrences'] ?? 0) > 0)
            ->sortByDesc('rate')
            ->first();
        if ($best && $best['rate'] >= 80) {
            $out[] = [
                'icon' => 'bi-trophy', 'tone' => 'good',
                'title' => "Strongest routine: ‘{$best['title']}’ at {$best['rate']}%",
                'body' => 'Protect whatever makes this one work.',
            ];
        }

        if (empty($out)) {
            $out[] = [
                'icon' => 'bi-compass', 'tone' => 'info',
                'title' => 'Not enough data yet',
                'body' => 'Complete tasks, check routines or log workouts to unlock insights.',
            ];
        }

        return array_slice($out, 0, 8);
    }

    /**
     * Busiest 3-hour slip window in the range, e.g. 18:00–21:00.
     */
    private static function slipPeakWindow(int $userId, ReportRange $range): ?array
    {
        $hours = array_fill(0, 24, 0);
        RoutineViolation::where('user_id', $userId)
            ->whereBetween('occurred_date', [$range->from->toDateString(), $range->to->toDateString()])
            ->chunkById(500, function ($rows) use (&$hours) {
                foreach ($rows as $row) {
                    $at = $row->occurred_at instanceof Carbon
                        ? $row->occurred_at
                        : Carbon::parse($row->occurred_at);
                    $hours[$at->hour] += (int) ($row->quantity ?? 1);
                }
            });

        if (array_sum($hours) === 0) {
            return null;
        }

        $bestStart = 0;
        $bestCount = -1;
        for ($h = 0; $h < 24; $h++) {
            $count = $hours[$h] + $hours[($h + 1) % 24] + $hours[($h + 2) % 24];
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestStart = $h;
            }
        }

        if ($bestCount <= 0) {
            return null;
        }

        $fmt = fn ($h) => sprintf('%02d:00', $h % 24);

        return ['label' => $fmt($bestStart).'–'.$fmt($bestStart + 3), 'count' => $bestCount];
    }

    /**
     * Project with the most tracked time (all-time recent) + its open overdue load.
     */
    private static function projectSpotlight(int $userId): ?array
    {
        $top = \App\Models\TimeEntry::where('user_id', $userId)
            ->where('status', \App\Models\TimeEntry::STATUS_STOPPED)
            ->where('started_at', '>=', now()->subDays(30))
            ->with('project:id,name')
            ->get()
            ->groupBy(fn ($e) => $e->project?->name ?? 'No project')
            ->map(fn ($g) => (int) $g->sum('duration_seconds'))
            ->sortDesc();

        if ($top->isEmpty() || $top->first() <= 0) {
            return null;
        }
        $name = (string) $top->keys()->first();

        $overdue = Task::where('user_id', $userId)
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereHas('project', fn ($q) => $q->where('name', $name))
            ->count();

        return [
            'name' => $name,
            'time' => \App\Models\TimeEntry::formatDuration((int) $top->first()),
            'overdue' => $overdue,
        ];
    }
}
