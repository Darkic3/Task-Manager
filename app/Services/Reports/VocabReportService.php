<?php

namespace App\Services\Reports;

use App\Models\VocabCard;
use App\Models\VocabDeck;
use App\Models\VocabReview;
use Carbon\Carbon;

/**
 * Vocabulary (Leitner box) performance for a window: reviews done,
 * remembered rate, per-day activity and per-deck rows, plus the live
 * "due now" count that drives reminders and insights.
 *
 * Conventions (shared with StudyReportService):
 * - review day = DATE(reviewed_at).
 * - per-deck rows capped at 50, most reviews first.
 */
final class VocabReportService
{
    public static function summarize(int $userId, Carbon $from, Carbon $to): array
    {
        $fromKey = $from->toDateString();
        $toKey = $to->toDateString();

        $dayKeys = self::dayKeys($from, $to);
        $perDayReviewed = array_fill_keys($dayKeys, 0);
        $perDayRemembered = array_fill_keys($dayKeys, 0);

        $reviews = VocabReview::where('user_id', $userId)
            ->whereDate('reviewed_at', '>=', $fromKey)
            ->whereDate('reviewed_at', '<=', $toKey)
            ->get(['deck_id', 'remembered', 'reviewed_at']);

        $reviewed = 0;
        $remembered = 0;
        $byDeck = [];
        foreach ($reviews as $r) {
            $day = $r->reviewed_at instanceof Carbon
                ? $r->reviewed_at->toDateString()
                : Carbon::parse($r->reviewed_at)->toDateString();
            if (! isset($perDayReviewed[$day])) {
                continue;
            }
            $perDayReviewed[$day]++;
            $reviewed++;
            $deckId = (int) $r->deck_id;
            $byDeck[$deckId] ??= ['reviewed' => 0, 'remembered' => 0];
            $byDeck[$deckId]['reviewed']++;
            if ($r->remembered) {
                $perDayRemembered[$day]++;
                $remembered++;
                $byDeck[$deckId]['remembered']++;
            }
        }

        $deckNames = VocabDeck::where('user_id', $userId)->pluck('name', 'id');
        $dueByDeck = VocabCard::where('user_id', $userId)
            ->due()
            ->selectRaw('deck_id, COUNT(*) as total')
            ->groupBy('deck_id')
            ->pluck('total', 'deck_id');

        $rows = [];
        foreach ($byDeck as $deckId => $a) {
            $rows[] = [
                'deck_id' => $deckId,
                'name' => $deckNames[$deckId] ?? __('Deleted deck'),
                'reviewed' => $a['reviewed'],
                'retention' => $a['reviewed'] > 0 ? round($a['remembered'] / $a['reviewed'] * 100, 1) : 0.0,
                'due' => (int) ($dueByDeck[$deckId] ?? 0),
            ];
        }
        // Decks with due cards but no reviews in range still show up.
        foreach ($dueByDeck as $deckId => $due) {
            if (! isset($byDeck[$deckId])) {
                $rows[] = [
                    'deck_id' => (int) $deckId,
                    'name' => $deckNames[$deckId] ?? __('Deleted deck'),
                    'reviewed' => 0,
                    'retention' => 0.0,
                    'due' => (int) $due,
                ];
            }
        }
        usort($rows, fn ($a, $b) => $b['reviewed'] <=> $a['reviewed']);
        $rows = array_slice($rows, 0, 50);

        $perDayRetention = [];
        foreach ($dayKeys as $day) {
            $perDayRetention[$day] = $perDayReviewed[$day] > 0
                ? round($perDayRemembered[$day] / $perDayReviewed[$day] * 100, 1)
                : null;
        }

        return [
            'reviewed' => $reviewed,
            'remembered' => $remembered,
            'retention' => $reviewed > 0 ? round($remembered / $reviewed * 100, 1) : 0.0,
            'per_day_reviewed' => $perDayReviewed,
            'per_day_reviewed_max' => max(1, max($perDayReviewed)),
            'per_day_retention' => $perDayRetention,
            'due_now' => (int) $dueByDeck->sum(),
            'total_cards' => VocabCard::where('user_id', $userId)->count(),
            'mastered' => VocabCard::where('user_id', $userId)->mastered()->count(),
            'rows' => $rows,
        ];
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
