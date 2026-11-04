<?php

namespace App\Services\Vocabulary;

use App\Models\User;
use Carbon\Carbon;

/**
 * Read-only retention statistics built on the vocab_reviews history log.
 * Pure aggregation — no writes, easily unit-testable via factories.
 */
class VocabStatsService
{
    /**
     * Per-day review activity for the last $days days (oldest → newest).
     *
     * @return list<array{date: string, label: string, reviewed: int, remembered: int, retention: ?float}>
     */
    public static function dailyRetention(User $user, int $days = 14): array
    {
        $days = max(1, min(90, $days));
        $from = today()->subDays($days - 1)->toDateString();
        $to = today()->toDateString();

        // NOTE: aggregate aliases must not collide with model attribute names
        // (the boolean cast on `remembered` would corrupt SUM results).
        $rows = $user->vocabReviews()
            ->inRange($from, $to)
            ->selectRaw('DATE(reviewed_at) as d, COUNT(*) as reviewed_count, SUM(remembered) as remembered_count')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $key = $date->toDateString();
            $row = $rows->get($key);
            $reviewed = (int) ($row->reviewed_count ?? 0);
            $remembered = (int) ($row->remembered_count ?? 0);

            $out[] = [
                'date' => $key,
                'label' => $date->format('n/j'),
                'reviewed' => $reviewed,
                'remembered' => $remembered,
                'retention' => $reviewed > 0 ? round(($remembered / $reviewed) * 100, 1) : null,
            ];
        }

        return $out;
    }

    /**
     * Per-deck summary: totals plus 7-day review activity.
     *
     * @return list<array{id: int, name: string, color: ?string, total: int, due: int, mastered: int, reviewed_7d: int, retention_7d: ?float}>
     */
    public static function deckSummary(User $user): array
    {
        $from = today()->subDays(6)->toDateString();

        $activity = $user->vocabReviews()
            ->whereDate('reviewed_at', '>=', $from)
            ->selectRaw('deck_id, COUNT(*) as reviewed_count, SUM(remembered) as remembered_count')
            ->groupBy('deck_id')
            ->get()
            ->keyBy('deck_id');

        return $user->vocabDecks()
            ->withCount('cards')
            ->orderBy('sort_order')->orderByDesc('id')
            ->get()
            ->map(function ($deck) use ($user, $activity) {
                $due = $user->vocabCards()->byDeck($deck->id)->due()->count();
                $mastered = $user->vocabCards()->byDeck($deck->id)->mastered()->count();
                $act = $activity->get($deck->id);
                $reviewed = (int) ($act->reviewed_count ?? 0);

                return [
                    'id' => $deck->id,
                    'name' => $deck->name,
                    'color' => $deck->color,
                    'total' => $deck->cards_count,
                    'due' => $due,
                    'mastered' => $mastered,
                    'reviewed_7d' => $reviewed,
                    'retention_7d' => $reviewed > 0 ? round(((int) ($act->remembered_count ?? 0) / $reviewed) * 100, 1) : null,
                ];
            })->all();
    }

    /**
     * Overall retention across all time (null when nothing reviewed yet).
     */
    public static function overallRetention(User $user): ?float
    {
        $total = $user->vocabReviews()->count();

        if ($total === 0) {
            return null;
        }

        return round(($user->vocabReviews()->remembered()->count() / $total) * 100, 1);
    }
}
