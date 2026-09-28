<?php

namespace App\Services\Reports;

use App\Models\Routine;
use App\Models\RoutineViolation;
use Carbon\Carbon;

/**
 * Routine performance for a window, split by behavior: build routines track
 * completions, avoid routines track clean days vs slips.
 */
final class RoutineReportService
{
    public static function summarize(int $userId, Carbon $from, Carbon $to): array
    {
        $routines = Routine::where('user_id', $userId)
            ->with(['checklistItems' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->get();

        if ($routines->isEmpty()) {
            return self::empty();
        }

        $fromKey = $from->toDateString();
        $toKey = $to->toDateString();

        $completions = \App\Models\RoutineCompletion::where('user_id', $userId)
            ->whereIn('routine_id', $routines->pluck('id'))
            ->whereBetween('completed_date', [$fromKey, $toKey])
            ->get()
            ->groupBy('routine_id');

        $violations = RoutineViolation::where('user_id', $userId)
            ->whereIn('routine_id', $routines->pluck('id'))
            ->whereBetween('occurred_date', [$fromKey, $toKey])
            ->get()
            ->groupBy('routine_id');

        foreach ($routines as $r) {
            $r->setRelation('completions', $completions->get($r->id, collect()));
            $r->setRelation('violations', $violations->get($r->id, collect()));
        }

        $rows = [];
        $donePerDay = array_fill_keys(self::dayKeys($from, $to), 0);
        $slippedPerDay = array_fill_keys(self::dayKeys($from, $to), 0);
        $byPeriod = [];
        $totalSlips = 0;
        $buildCompleted = 0;
        $rates = [];
        $cleanStreaks = [];

        foreach ($routines as $r) {
            $occ = $r->occurrenceDates($from, $to);
            $occCount = count($occ);
            $slot = $r->time_period ?: 'anytime';
            $byPeriod[$slot] ??= ['done' => 0, 'slips' => 0, 'occurrences' => 0];
            $byPeriod[$slot]['occurrences'] += $occCount;

            if ($r->isAvoid()) {
                $vKeys = array_flip($r->violatedDateKeys($from, $to));
                $clean = count(array_diff($occ, array_keys($vKeys)));
                $rate = $occCount ? (int) round($clean / $occCount * 100) : 0;
                $slips = (int) $r->violations->sum('quantity');
                $totalSlips += $slips;
                $rates[] = $rate;
                $byPeriod[$slot]['slips'] += $slips;
                $cleanStreaks[] = $r->avoidMetrics($to)['current'];
                foreach (array_keys($vKeys) as $d) {
                    if (array_key_exists($d, $slippedPerDay)) {
                        $slippedPerDay[$d]++;
                    }
                }
                $rows[] = [
                    'id' => $r->id, 'title' => $r->title, 'is_avoid' => true,
                    'occurrences' => $occCount, 'done' => $clean, 'rate' => $rate,
                    'slips' => $slips, 'period' => $slot,
                ];
            } else {
                $doneKeys = array_flip($r->completionDateKeys($from, $to));
                $hit = count(array_intersect($occ, array_keys($doneKeys)));
                $rate = $occCount ? (int) round($hit / $occCount * 100) : 0;
                $buildCompleted += $hit;
                if ($occCount) {
                    $rates[] = $rate;
                }
                $byPeriod[$slot]['done'] += $hit;
                foreach (array_keys($doneKeys) as $d) {
                    if (array_key_exists($d, $donePerDay)) {
                        $donePerDay[$d]++;
                    }
                }
                $rows[] = [
                    'id' => $r->id, 'title' => $r->title, 'is_avoid' => false,
                    'occurrences' => $occCount, 'done' => $hit, 'rate' => $rate,
                    'slips' => 0, 'period' => $slot,
                ];
            }
        }

        usort($rows, fn ($a, $b) => $b['rate'] <=> $a['rate']);

        return [
            'total' => $routines->count(),
            'build' => $routines->count(fn ($r) => ! $r->isAvoid()),
            'avoid' => $routines->count(fn ($r) => $r->isAvoid()),
            'avg_rate' => $rates ? (int) round(array_sum($rates) / count($rates)) : 0,
            'build_completed' => $buildCompleted,
            'total_slips' => $totalSlips,
            'best_clean_streak' => $cleanStreaks ? max($cleanStreaks) : 0,
            'rows' => $rows,
            'by_period' => $byPeriod,
            'per_day_done' => $donePerDay,
            'per_day_slipped' => $slippedPerDay,
            'per_day_max' => max(1, max($donePerDay), max($slippedPerDay)),
        ];
    }

    private static function empty(): array
    {
        return [
            'total' => 0, 'build' => 0, 'avoid' => 0, 'avg_rate' => 0,
            'build_completed' => 0, 'total_slips' => 0, 'best_clean_streak' => 0,
            'rows' => [], 'by_period' => [],
            'per_day_done' => [], 'per_day_slipped' => [], 'per_day_max' => 1,
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
