<?php

use Morilog\Jalali\Jalalian;
use Carbon\Carbon;

if (!function_exists('app_date')) {
    /**
     * Format date based on active locale (Shamsi/Jalali for Persian, Gregorian for English).
     */
    function app_date($date, ?string $format = null): string
    {
        if (!$date) {
            return '';
        }

        try {
            if (!($date instanceof Carbon)) {
                $date = Carbon::parse($date);
            }

            if (app()->getLocale() === 'fa') {
                $jalali = Jalalian::fromCarbon($date);
                $format = $format ?: 'Y/m/d';
                return $jalali->format($format);
            }

            $format = $format ?: 'Y-m-d';
            return $date->format($format);
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }
}

if (!function_exists('app_datetime')) {
    /**
     * Format date and time based on active locale.
     */
    function app_datetime($date, ?string $format = null): string
    {
        if (!$date) {
            return '';
        }

        try {
            if (!($date instanceof Carbon)) {
                $date = Carbon::parse($date);
            }

            if (app()->getLocale() === 'fa') {
                $jalali = Jalalian::fromCarbon($date);
                $format = $format ?: 'Y/m/d H:i';
                return $jalali->format($format);
            }

            $format = $format ?: 'Y-m-d H:i';
            return $date->format($format);
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }
}

if (!function_exists('app_human_date')) {
    /**
     * Format friendly date (e.g. دوشنبه، ۷ مهر ۱۴۰۳ / Monday, September 29, 2026).
     */
    function app_human_date($date = null): string
    {
        try {
            $date = $date ? ($date instanceof Carbon ? $date : Carbon::parse($date)) : now();

            if (app()->getLocale() === 'fa') {
                return Jalalian::fromCarbon($date)->format('%A، %d %B %Y');
            }

            return $date->format('l, F j, Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }
}

if (!function_exists('app_diff_for_humans')) {
    /**
     * Relative time ago.
     */
    function app_diff_for_humans($date): string
    {
        if (!$date) {
            return '';
        }

        try {
            if (!($date instanceof Carbon)) {
                $date = Carbon::parse($date);
            }

            if ($date->greaterThan(now()->subMinute())) {
                return __('Just now');
            }

            if (app()->getLocale() === 'fa') {
                return app_num(Jalalian::fromCarbon($date)->ago());
            }

            return $date->diffForHumans();
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }
}

if (!function_exists('app_num')) {
    /**
     * Localize digits for numbers rendered in the UI (Persian digits for "fa").
     */
    function app_num($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (app()->getLocale() !== 'fa') {
            return (string) $value;
        }

        return strtr((string) $value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    }
}

if (!function_exists('app_greeting')) {
    /**
     * Get dynamic greeting message (صبح بخیر / عصر بخیر / شب بخیر).
     */
    function app_greeting(): string
    {
        $hour = (int) now()->format('H');

        if ($hour < 12) {
            return __('Good morning');
        } elseif ($hour < 18) {
            return __('Good afternoon');
        } else {
            return __('Good evening');
        }
    }
}
