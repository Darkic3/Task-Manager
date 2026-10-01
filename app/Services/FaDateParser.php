<?php

namespace App\Services;

use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

/**
 * FaDateParser — tiny Persian date/time cue parser for reminders & plans.
 *
 * Understands things like:
 *   "امروز ساعت 6 بعد از ظهر"      → today 18:00
 *   "فردا ساعت ۹ صبح"               → tomorrow 09:00
 *   "پس‌فردا 18:00"                 → +2 days 18:00
 *   "جمعه ساعت ۵ عصر"               → next Friday 17:00
 *   "۹ مهر ساعت ۱۰ صبح"             → Jalali Mehr 9 10:00
 *   "1403/07/09"                    → Jalali → Gregorian
 *
 * Pure function, no DB. Returns ['date' => ?Y-m-d, 'time' => ?H:i].
 */
class FaDateParser
{
    private const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const AR_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const WEEKDAYS = [
        'شنبه' => 6, 'یکشنبه' => 0, 'یک شنبه' => 0, 'دوشنبه' => 1, 'دو شنبه' => 1,
        'سه شنبه' => 2, 'سه‌شنبه' => 2, 'چهارشنبه' => 3, 'چهار شنبه' => 3,
        'پنجشنبه' => 4, 'پنج شنبه' => 4, 'جمعه' => 5,
    ];

    private const JALALI_MONTHS = [
        'فروردین' => 1, 'اردیبهشت' => 2, 'خرداد' => 3, 'تیر' => 4,
        'مرداد' => 5, 'شهریور' => 6, 'مهر' => 7, 'آبان' => 8,
        'آذر' => 9, 'دی' => 10, 'بهمن' => 11, 'اسفند' => 12,
    ];

    public static function normalize(string $text): string
    {
        $text = str_replace(self::FA_DIGITS, range(0, 9), $text);
        $text = str_replace(self::AR_DIGITS, range(0, 9), $text);
        // Unify Arabic kaf/yeh + ZWNJ variants so keyword matching is stable.
        $text = str_replace(['ي', 'ك', '‌'], ['ی', 'ک', ' '], $text);

        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
    }

    public static function parse(string $text, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->startOfDay();
        $t = self::normalize($text);
        $date = null;

        if (mb_strpos($t, 'پس فردا') !== false || mb_strpos($t, 'پسفردا') !== false) {
            $date = $now->copy()->addDays(2);
        } elseif (mb_strpos($t, 'فردا') !== false) {
            $date = $now->copy()->addDay();
        } elseif (mb_strpos($t, 'امروز') !== false) {
            $date = $now->copy();
        }

        // Explicit Jalali/Gregorian numeric date: 1403/07/09 or 2026-10-01.
        if (! $date && preg_match('/(\d{4})\s*[\/\-.]\s*(\d{1,2})\s*[\/\-.]\s*(\d{1,2})/u', $t, $m)) {
            $date = self::parseNumericDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Jalali "9 مهر" style.
        if (! $date) {
            foreach (self::JALALI_MONTHS as $name => $month) {
                if (preg_match('/(\d{1,2})\s*' . preg_quote($name, '/') . '/u', $t, $m)) {
                    $date = self::jalaliMonthDay((int) $m[1], $month, $now);
                    break;
                }
            }
        }

        // Persian weekday name → nearest occurrence (today if it is that day).
        if (! $date) {
            foreach (self::WEEKDAYS as $name => $dow) {
                if (mb_strpos($t, $name) !== false) {
                    $date = $now->copy();
                    $delta = ($dow - (int) $now->dayOfWeek + 7) % 7;
                    $date->addDays($delta);
                    break;
                }
            }
        }

        $time = self::parseTime($t);

        // A bare time ("ساعت 6 عصر") with no date cue means today in reminder context.
        if ($time && ! $date && mb_strpos($t, 'ساعت') !== false) {
            $date = $now->copy();
        }

        return [
            'date' => $date?->toDateString(),
            'time' => $time,
        ];
    }

    private static function parseNumericDate(int $y, int $m, int $d): ?Carbon
    {
        if ($m < 1 || $m > 12 || $d < 1 || $d > 31) {
            return null;
        }
        try {
            // Years >= 2000 are Gregorian; smaller ones are Jalali (e.g. 1403).
            if ($y >= 2000) {
                return Carbon::create($y, $m, $d)->startOfDay();
            }

            return Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $y, $m, $d))->toCarbon()->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function jalaliMonthDay(int $day, int $month, Carbon $now): ?Carbon
    {
        if ($day < 1 || $day > 31) {
            return null;
        }
        try {
            $jNow = Jalalian::fromCarbon($now);
            $candidate = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $jNow->getYear(), $month, min($day, 29)));
            $carbon = $candidate->toCarbon()->startOfDay();
            if ($carbon->lt($now)) {
                $candidate = Jalalian::fromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $jNow->getYear() + 1, $month, min($day, 29)));
                $carbon = $candidate->toCarbon()->startOfDay();
            }

            return $carbon;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Returns H:i or null.
     */
    private static function parseTime(string $t): ?string
    {
        // Midnight words win over numbers.
        if (preg_match('/(نیمه شب|نصف شب)/u', $t) && ! preg_match('/ساعت\s*\d/u', $t)) {
            return '00:00';
        }

        if (! preg_match('/ساعت\s*(\d{1,2})(?:\s*[:.]\s*(\d{1,2}))?/u', $t, $m)
            && ! preg_match('/(\d{1,2})\s*[:.]\s*(\d{2})/u', $t, $m)) {
            return null;
        }

        $h = (int) $m[1];
        $min = isset($m[2]) && $m[2] !== '' ? min(59, (int) $m[2]) : 0;

        $isNoon = mb_strpos($t, 'ظهر') !== false;
        $isEvening = $isNoon
            || mb_strpos($t, 'عصر') !== false
            || mb_strpos($t, 'غروب') !== false
            || mb_strpos($t, 'شب') !== false
            || mb_strpos($t, 'بعد از') !== false
            || mb_strpos($t, 'بعداز') !== false;
        $isMorning = mb_strpos($t, 'صبح') !== false;

        if ($isEvening && $h < 12) {
            $h += 12;
        } elseif ($isMorning && $h === 12) {
            $h = 0;
        }
        if ($h > 23) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $min);
    }
}
