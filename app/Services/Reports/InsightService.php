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
            'today' => __('today'),
            'month' => __('this month'),
            'custom' => __('the selected period'),
            default => __('this week'),
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
                'title' => __(':count overdue tasks', ['count' => $tasks['overdue_now']]),
                'body' => __('Clear these before planning anything new.'),
            ];
        }

        // 2. Slip peak window (3-hour bucket with the most slips).
        $peak = self::slipPeakWindow($userId, $range);
        if ($peak) {
            $out[] = [
                'icon' => 'bi-clock-history', 'tone' => 'warn',
                'title' => __('Slips cluster between :label', ['label' => $peak['label']]),
                'body' => __(':count slips — plan a replacement ritual for that slot.', ['count' => $peak['count']]),
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
                'title' => __('“:title” is struggling at :rate%', ['title' => $weak['title'], 'rate' => $weak['rate']]),
                'body' => $weak['is_avoid'] ? __('Review its triggers and slip slots below.') : __('Consider shrinking it or moving it to a better slot.'),
            ];
        }

        // 4. Task completion trend vs previous window.
        if ($tasks['created'] > 0 || $tasks['completed'] > 0) {
            $trend = $deltas['task_rate'];
            if ($trend > 0) {
                $out[] = [
                    'icon' => 'bi-graph-up-arrow', 'tone' => 'good',
                    'title' => __('Completion rate up :trend pp :period', ['trend' => $trend, 'period' => $period]),
                    'body' => __(':completed of :created tasks done.', ['completed' => $tasks['completed'], 'created' => $tasks['created']]),
                ];
            } elseif ($trend < 0) {
                $out[] = [
                    'icon' => 'bi-graph-down-arrow', 'tone' => 'warn',
                    'title' => __('Completion rate down :trend pp :period', ['trend' => abs($trend), 'period' => $period]),
                    'body' => __(':completed of :created tasks done — protect focus time.', ['completed' => $tasks['completed'], 'created' => $tasks['created']]),
                ];
            }
        }

        // 5. Slip trend (fewer is better — inverted tone).
        if ($avoid['routines'] > 0 && ($avoid['slip_total'] > 0 || $deltas['slips'] !== 0)) {
            $trend = $deltas['slips'];
            if ($trend < 0) {
                $out[] = [
                    'icon' => 'bi-shield-check', 'tone' => 'good',
                    'title' => __('Slips down by :count :period', ['count' => abs($trend), 'period' => $period]),
                    'body' => __(':count clean days and counting.', ['count' => $avoid['clean_days']]),
                ];
            } elseif ($trend > 0) {
                $out[] = [
                    'icon' => 'bi-shield-exclamation', 'tone' => 'bad',
                    'title' => __('Slips up by :count :period', ['count' => $trend, 'period' => $period]),
                    'body' => __('Check the peak slot and top triggers below.'),
                ];
            } elseif ($avoid['slip_total'] === 0 && $avoid['occurrences'] > 0) {
                $out[] = [
                    'icon' => 'bi-shield-fill-check', 'tone' => 'good',
                    'title' => __('Perfectly clean :period', ['period' => $period]),
                    'body' => __(':count clean days, zero slips. 🛡️', ['count' => $avoid['clean_days']]),
                ];
            }
        }

        // 6. Clean-streak milestone.
        if (($routines['best_clean_streak'] ?? 0) >= 3) {
            $out[] = [
                'icon' => 'bi-fire', 'tone' => 'good',
                'title' => __(':count-day clean streak', ['count' => $routines['best_clean_streak']]),
                'body' => __('Longest abstinence run in the window — keep it alive.'),
            ];
        }

        // 7. Cravings managed without slipping.
        if ($avoid['cravings'] > 0 && $avoid['slip_total'] === 0) {
            $out[] = [
                'icon' => 'bi-hand-thumbs-up', 'tone' => 'good',
                'title' => __('All :count cravings managed', ['count' => $avoid['cravings']]),
                'body' => __('Urges logged, none turned into slips.'),
            ];
        }

        // 8. Project spotlight: most time + overdue load.
        $spot = self::projectSpotlight($userId);
        if ($spot) {
            $out[] = [
                'icon' => 'bi-folder', 'tone' => $spot['overdue'] > 0 ? 'warn' : 'info',
                'title' => __('Spotlight: :name', ['name' => $spot['name']]),
                'body' => $spot['overdue'] > 0
                    ? __(':time tracked · :overdue overdue', ['time' => $spot['time'], 'overdue' => $spot['overdue']])
                    : __(':time tracked', ['time' => $spot['time']]),
            ];
        }

        // 9. Workout consistency.
        if ($workouts['completed'] > 0 || $deltas['workouts'] !== 0) {
            $trend = $deltas['workouts'];
            $out[] = [
                'icon' => 'bi-heart-pulse', 'tone' => $trend < 0 ? 'warn' : 'good',
                'title' => __(':count workouts :period', ['count' => $workouts['completed'], 'period' => $period]),
                'body' => $trend !== 0
                    ? __(':min min · :trend vs prev', ['min' => $workouts['minutes'], 'trend' => ($trend > 0 ? '+' : '').$trend])
                    : __(':min min', ['min' => $workouts['minutes']]),
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
                'title' => __('Strongest routine: “:title” at :rate%', ['title' => $best['title'], 'rate' => $best['rate']]),
                'body' => __('Protect whatever makes this one work.'),
            ];
        }

        if (empty($out)) {
            $out[] = [
                'icon' => 'bi-compass', 'tone' => 'info',
                'title' => __('Not enough data yet'),
                'body' => __('Complete tasks, check routines or log workouts to unlock insights.'),
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
