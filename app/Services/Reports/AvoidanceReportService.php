<?php

namespace App\Services\Reports;

use App\Models\Routine;
use App\Models\RoutineNote;
use App\Models\RoutineViolation;
use Carbon\Carbon;

/**
 * Forbidden-habit analytics: slips, clean days, streaks, cravings, triggers
 * and slip timing — all scoped to avoid routines in the window.
 */
final class AvoidanceReportService
{
    public static function summarize(int $userId, Carbon $from, Carbon $to): array
    {
        $routines = Routine::where('user_id', $userId)
            ->where('behavior_type', Routine::BEHAVIOR_AVOID)
            ->get();

        if ($routines->isEmpty()) {
            return self::empty();
        }

        $fromKey = $from->toDateString();
        $toKey = $to->toDateString();
        $ids = $routines->pluck('id');

        $violations = RoutineViolation::where('user_id', $userId)
            ->whereIn('routine_id', $ids)
            ->whereBetween('occurred_date', [$fromKey, $toKey])
            ->with('item:id,name')
            ->orderBy('occurred_at')
            ->get();

        $notes = RoutineNote::where('user_id', $userId)
            ->whereIn('routine_id', $ids)
            ->whereBetween('occurred_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('occurred_at')
            ->get();

        $slipTotal = (int) $violations->sum('quantity');
        $slipDays = $violations->map(fn ($v) => $v->occurred_date instanceof Carbon
            ? $v->occurred_date->toDateString()
            : Carbon::parse($v->occurred_date)->toDateString())->unique()->count();

        // Clean days = occurrences without a slip, across all avoid routines.
        $cleanDays = 0;
        $occTotal = 0;
        $streaks = [];
        foreach ($routines as $r) {
            $occ = $r->occurrenceDates($from, $to);
            $occTotal += count($occ);
            $vKeys = array_flip($r->violatedDateKeys($from, $to));
            $cleanDays += count(array_diff($occ, array_keys($vKeys)));
            $streaks[] = $r->avoidMetrics($to)['current'];
        }

        // Top triggers.
        $triggers = $violations
            ->filter(fn ($v) => $v->trigger)
            ->groupBy(fn ($v) => mb_strtolower(trim((string) $v->trigger)))
            ->map->count()
            ->sortDesc()
            ->take(6)
            ->all();

        // Slips by day-period slot (step schedule, else routine slot).
        $periods = config('routines.periods', []);
        $bySlot = [];
        foreach ($violations as $v) {
            $slot = $v->item?->time_period
                ?? $routines->firstWhere('id', $v->routine_id)?->time_period
                ?? 'anytime';
            $bySlot[$slot] = ($bySlot[$slot] ?? 0) + (int) $v->quantity;
        }
        uksort($bySlot, fn ($a, $b) => ($periods[$a]['order'] ?? 99) <=> ($periods[$b]['order'] ?? 99));

        // Daily slip quantities.
        $perDay = array_fill_keys(self::dayKeys($from, $to), 0);
        foreach ($violations as $v) {
            $key = $v->occurred_date instanceof Carbon
                ? $v->occurred_date->toDateString()
                : Carbon::parse($v->occurred_date)->toDateString();
            if (array_key_exists($key, $perDay)) {
                $perDay[$key] += (int) $v->quantity;
            }
        }

        $cravingCount = $notes->where('kind', 'craving')->count();

        return [
            'routines' => $routines->count(),
            'slip_total' => $slipTotal,
            'slip_days' => $slipDays,
            'clean_days' => $cleanDays,
            'occurrences' => $occTotal,
            'clean_rate' => $occTotal ? (int) round($cleanDays / $occTotal * 100) : 0,
            'best_clean_streak' => $streaks ? max($streaks) : 0,
            'cravings' => $cravingCount,
            'notes' => $notes->count() - $cravingCount,
            'triggers' => $triggers,
            'by_slot' => $bySlot,
            'per_day' => $perDay,
            'per_day_max' => max(1, max($perDay)),
            'recent_notes' => $notes->take(8),
            'periods' => $periods,
        ];
    }

    private static function empty(): array
    {
        return [
            'routines' => 0, 'slip_total' => 0, 'slip_days' => 0, 'clean_days' => 0,
            'occurrences' => 0, 'clean_rate' => 0, 'best_clean_streak' => 0,
            'cravings' => 0, 'notes' => 0, 'triggers' => [], 'by_slot' => [],
            'per_day' => [], 'per_day_max' => 1, 'recent_notes' => collect(), 'periods' => [],
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
