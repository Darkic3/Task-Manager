<?php

namespace App\Services\Vocabulary;

use Carbon\Carbon;

/**
 * Pure Leitner 5-box scheduler (no DB access — easily unit-testable).
 *
 * Intervals: Box1 = daily, Box2 = every 3 days, Box3 = weekly,
 *            Box4 = every 14 days, Box5 = every 30 days (mastered).
 *
 * Rules:
 *  - remembered  → advance one box (max 5), next review = interval of the NEW box.
 *  - forgotten   → back to Box 1, next review = tomorrow.
 */
class LeitnerScheduler
{
    public const BOX_MIN = 1;

    public const BOX_MAX = 5;

    public const MASTERED_BOX = 5;

    public const INTERVALS = [
        1 => 1,
        2 => 3,
        3 => 7,
        4 => 14,
        5 => 30,
    ];

    public static function intervalForBox(int $box): int
    {
        $box = max(self::BOX_MIN, min(self::BOX_MAX, $box));

        return self::INTERVALS[$box];
    }

    /**
     * @return array{box: int, next_review_at: Carbon, is_mastered: bool}
     */
    public static function resolve(bool $remembered, int $currentBox): array
    {
        $currentBox = max(self::BOX_MIN, min(self::BOX_MAX, $currentBox));

        $newBox = $remembered
            ? min(self::BOX_MAX, $currentBox + 1)
            : self::BOX_MIN;

        return [
            'box' => $newBox,
            'next_review_at' => static::nextReviewDate($newBox),
            'is_mastered' => $newBox === self::MASTERED_BOX,
        ];
    }

    public static function nextReviewDate(int $box): Carbon
    {
        return today()->addDays(static::intervalForBox($box));
    }

    public static function initialState(): array
    {
        return [
            'box' => self::BOX_MIN,
            // New cards are due immediately so today's session picks them up.
            'next_review_at' => today(),
            'is_mastered' => false,
        ];
    }
}
