<?php

use Morilog\Jalali\Jalalian;
use Carbon\Carbon;
use App\Models\Note;

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

if (!function_exists('name_initials')) {
    /**
     * Initials for avatar placeholders (multibyte safe, e.g. "Sara Mohammadi" -> "SM").
     */
    function name_initials(?string $name, int $limit = 1): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        $initials = collect(preg_split('/\s+/u', $name) ?: [])
            ->filter()
            ->take($limit)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($name, 0, $limit));
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

if (!function_exists('note_slug')) {
    /**
     * Normalised uniqueness key for note labels and subjects.
     * Persian/Arabic text has no latin slug, so fall back to a stable hash.
     */
    function note_slug(?string $value): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));

        $slug = \Illuminate\Support\Str::slug($normalized);

        if ($slug === '') {
            return 'n-'.substr(hash('sha256', $normalized), 0, 16);
        }

        return mb_substr($slug, 0, 120);
    }
}

if (!function_exists('note_search_key')) {
    /**
     * Fold a Persian/Arabic phrase into a comparable key: unify Arabic yeh/kaf,
     * strip ZWNJ and tatweel, collapse spaces and lowercase.
     */
    function note_search_key(?string $value): string
    {
        $value = (string) $value;

        $value = strtr($value, [
            "\u{064A}" => "\u{06CC}", // ARABIC YEH  -> FARSI YEH
            "\u{0649}" => "\u{06CC}", // ALEF MAKSURA -> FARSI YEH
            "\u{0643}" => "\u{06A9}", // ARABIC KAF  -> KEHEH
            "\u{200C}" => ' ',          // ZWNJ
            "\u{0640}" => '',           // TATWEEL
        ]);

        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}

if (!function_exists('note_kind_meta')) {
    /**
     * Icon + translated label for every note type.
     *
     * @return array<string, array{icon: string, label: string}>
     */
    function note_kind_meta(): array
    {
        return [
            Note::KIND_DAILY => ['icon' => 'bi-calendar-day', 'label' => __('Daily')],
            Note::KIND_EVENT => ['icon' => 'bi-lightning-charge', 'label' => __('Event')],
            Note::KIND_PERSON => ['icon' => 'bi-person', 'label' => __('Person')],
            Note::KIND_TOPIC => ['icon' => 'bi-collection', 'label' => __('Topic')],
            Note::KIND_MEETING => ['icon' => 'bi-people', 'label' => __('Meeting')],
            Note::KIND_DECISION => ['icon' => 'bi-signpost-split', 'label' => __('Decision')],
            Note::KIND_REFERENCE => ['icon' => 'bi-bookmark', 'label' => __('Reference')],
            Note::KIND_IDEA => ['icon' => 'bi-lightbulb', 'label' => __('Idea')],
            Note::KIND_LOG => ['icon' => 'bi-journal-text', 'label' => __('Log')],
            Note::KIND_GENERAL => ['icon' => 'bi-journal', 'label' => __('General')],
        ];
    }
}

if (!function_exists('note_kind_label')) {
    function note_kind_label(?string $kind): string
    {
        $meta = note_kind_meta();

        return $meta[$kind ?: Note::KIND_GENERAL]['label'] ?? $kind;
    }
}

if (!function_exists('note_kind_icon')) {
    function note_kind_icon(?string $kind): string
    {
        $meta = note_kind_meta();

        return $meta[$kind ?: Note::KIND_GENERAL]['icon'] ?? 'bi-journal';
    }
}

if (!function_exists('note_kind_template')) {
    function note_kind_template(?string $kind): string
    {
        return \App\Services\Notes\NoteTemplateService::bodyFor($kind ?: \App\Models\Note::KIND_GENERAL);
    }
}

if (!function_exists('note_mood_icon')) {
    /**
     * Emoji + colour for the 1..5 mood / energy scale.
     */
    function note_mood_icon(?int $value): array
    {
        $scale = [
            1 => ['emoji' => '😞', 'color' => '#ef4444'],
            2 => ['icon' => 'bi-emoji-frown', 'color' => '#f97316'],
            3 => ['emoji' => '😐', 'color' => '#f59e0b'],
            4 => ['emoji' => '🙂', 'color' => '#10b981'],
            5 => ['emoji' => '😄', 'color' => '#059669'],
        ];

        return $scale[$value] ?? ['emoji' => '', 'icon' => 'bi-emoji-neutral', 'color' => '#94a3b8'];
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
