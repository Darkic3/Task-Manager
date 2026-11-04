<?php

namespace App\Services\Reports;

use App\Models\Law;
use App\Models\LawEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Laws report: XP scored per law and per day within the selected window,
 * split by automatic (linked tasks/routines/slips) vs manual (DayLog) source.
 */
final class LawReportService
{
    /**
     * @return array{total_xp:int, plus:int, minus:int, auto_xp:int, manual_xp:int, rows:array, days:array}
     */
    public static function summarize(int $userId, Carbon $from, Carbon $to): array
    {
        $fromKey = $from->toDateString();
        $toKey = $to->toDateString();

        $events = LawEvent::where('user_id', $userId)
            ->whereBetween('occurred_on', [$fromKey, $toKey])
            ->get(['law_id', 'direction', 'xp_delta', 'source', 'occurred_on']);

        $laws = Law::where('user_id', $userId)
            ->whereIn('id', $events->pluck('law_id')->unique()->all() ?: [0])
            ->get(['id', 'title', 'coefficient', 'color', 'icon'])
            ->keyBy('id');

        $byLaw = $events->groupBy('law_id');
        $rows = [];
        foreach ($byLaw as $lawId => $group) {
            $law = $laws->get($lawId);
            $rows[] = [
                'id' => (int) $lawId,
                'title' => $law?->title ?? __('Deleted law'),
                'coefficient' => (int) ($law?->coefficient ?? 0),
                'color' => $law?->color ?: '#7c3aed',
                'icon' => $law?->icon ?: 'bi-shield-check',
                'xp' => (int) $group->sum('xp_delta'),
                'plus' => $group->where('direction', 1)->count(),
                'minus' => $group->where('direction', -1)->count(),
                'auto_xp' => (int) $group->where('source', LawEvent::SOURCE_AUTO)->sum('xp_delta'),
                'manual_xp' => (int) $group->where('source', LawEvent::SOURCE_MANUAL)->sum('xp_delta'),
            ];
        }
        usort($rows, fn ($a, $b) => $b['xp'] <=> $a['xp']);

        $byDay = $events->groupBy(fn (LawEvent $e) => $e->occurred_on instanceof Carbon
            ? $e->occurred_on->toDateString()
            : substr((string) $e->occurred_on, 0, 10));

        $days = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $guard = 0;
        while ($cursor->lte($end) && $guard++ < 400) {
            $key = $cursor->toDateString();
            $group = $byDay->get($key, collect());
            $days[] = [
                'date' => $key,
                'label' => $cursor->format('m/d'),
                'xp' => (int) $group->sum('xp_delta'),
            ];
            $cursor->addDay();
        }

        return [
            'total_xp' => (int) $events->sum('xp_delta'),
            'plus' => $events->where('direction', 1)->count(),
            'minus' => $events->where('direction', -1)->count(),
            'auto_xp' => (int) $events->where('source', LawEvent::SOURCE_AUTO)->sum('xp_delta'),
            'manual_xp' => (int) $events->where('source', LawEvent::SOURCE_MANUAL)->sum('xp_delta'),
            'rows' => $rows,
            'days' => $days,
        ];
    }
}
