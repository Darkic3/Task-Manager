<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Routine extends Model
{
    use HasFactory, SoftDeletes;

    public const WEEK_DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    public const TRACKING_NONE = 'none';
    public const TRACKING_VALUE = 'value';
    public const TRACKING_SETS = 'sets';

    public const BEHAVIOR_BUILD = 'build';
    public const BEHAVIOR_AVOID = 'avoid';

    public const BEHAVIORS = [self::BEHAVIOR_BUILD, self::BEHAVIOR_AVOID];

    public const VALUE_KINDS = ['number', 'weight', 'time', 'reps', 'percent'];

    protected $fillable = [
        'user_id',
        'parent_id',
        'cycle_no',
        'title',
        'description',
        'frequency',
        'behavior_type',
        'count_violations',
        'time_period',
        'sort_order',
        'days',
        'weeks',
        'months',
        'month_days',
        'every_n_days',
        'start_time',
        'end_time',
        'tracking_mode',
        'value_kind',
        'value_unit',
        'value_label',
    ];

    protected $casts = [
        'days' => 'array',
        'weeks' => 'array',
        'months' => 'array',
        'month_days' => 'array',
        'count_violations' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(RoutineCompletion::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RoutineLog::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Routine::class, 'parent_id');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(Routine::class, 'parent_id')->orderBy('cycle_no');
    }

    public function isTracked(): bool
    {
        return in_array($this->tracking_mode, [self::TRACKING_VALUE, self::TRACKING_SETS], true);
    }

    /**
     * Value-tracked "time" routines log clock times; stored as minutes from
     * midnight (0-1439) so deltas, sparklines and sorting stay numeric.
     */
    public function isTimeValue(): bool
    {
        return $this->tracking_mode === self::TRACKING_VALUE && ($this->value_kind ?? null) === 'time';
    }

    public static function minutesToTimeValue($minutes): ?string
    {
        if ($minutes === null || ! is_numeric($minutes)) {
            return null;
        }
        $m = ((int) round((float) $minutes)) % 1440;
        if ($m < 0) {
            $m += 1440;
        }

        return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }

    /**
     * "07:30" / "6:45 PM" display form for time kinds; plain number otherwise.
     */
    public function formatValue($value, bool $amPm = false): string
    {
        if ($value === null || ! is_numeric($value)) {
            return '';
        }
        if (! $this->isTimeValue()) {
            $float = (float) $value;
            if ($float === 0.0) {
                return '0';
            }

            return rtrim(rtrim(number_format($float, 2, '.', ''), '0'), '.');
        }
        $time = self::minutesToTimeValue($value);
        if (! $amPm || $time === null) {
            return $time ?? '';
        }
        [$h, $mi] = explode(':', $time);
        $h = (int) $h;
        $suffix = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12 === 0 ? 12 : $h % 12;

        return $h12 . ':' . $mi . ' ' . $suffix;
    }

    public function trackingLabel(): string
    {
        return match ($this->tracking_mode) {
            self::TRACKING_VALUE => trim(($this->value_label ?: 'Value') . ($this->value_unit ? " ({$this->value_unit})" : '')),
            self::TRACKING_SETS => 'Sets',
            default => '',
        };
    }

    /**
     * Logged values for a date: ['value' => ?float] for value mode, or
     * [itemId => [setNo => value]] for sets mode. Uses loaded `logs` if present.
     */
    public function loggedValues($date): array
    {
        $key = RoutineLog::dateKey($date);
        $logs = $this->relationLoaded('logs')
            ? $this->logs->filter(fn ($l) => $l->completed_date instanceof Carbon
                ? $l->completed_date->toDateString() === $key
                : substr((string) $l->completed_date, 0, 10) === $key)
            : $this->logs()->where('completed_date', $key)->get();

        if ($this->tracking_mode === self::TRACKING_VALUE) {
            $first = $logs->firstWhere('checklist_item_id', null);

            return ['value' => $first ? (float) $first->value : null];
        }

        $out = [];
        foreach ($logs as $log) {
            if ($log->checklist_item_id === null) {
                continue;
            }
            $out[(int) $log->checklist_item_id][(int) $log->set_no] = (float) $log->value;
        }

        return $out;
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(RoutineChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function decodedDays(): array
    {
        return $this->decode($this->days);
    }

    public function decodedWeeks(): array
    {
        return $this->decode($this->weeks);
    }

    public function decodedMonths(): array
    {
        return $this->decode($this->months);
    }

    public function decodedMonthDays(): array
    {
        return array_map('intval', $this->decode($this->month_days));
    }

    /**
     * Human-readable recurrence summary for display.
     */
    public function recurrenceLabel(): string
    {
        switch ($this->frequency) {
            case 'daily':
                return 'Every day';

            case 'weekly':
                $days = $this->decodedDays();

                if (! $days) {
                    return 'No days selected';
                }

                return collect($days)
                    ->map(fn ($d) => ucfirst(substr($d, 0, 3)))
                    ->implode(', ');

            case 'monthly':
                $monthDays = $this->decodedMonthDays();

                if (! $monthDays) {
                    return 'No days selected';
                }

                return 'Day '.implode(', ', $monthDays);

            case 'every_n_days':
                $n = max(2, (int) $this->every_n_days);

                return $n === 2 ? 'Every other day' : "Every {$n} days";
        }

        return ucfirst((string) $this->frequency);
    }

    public function timeLabel(): string
    {
        if ($this->time_period) {
            return (string) $this->periodLabel();
        }

        if (! $this->start_time || ! $this->end_time) {
            return '';
        }

        return Carbon::parse($this->start_time)->format('g:i A')
            .' – '.Carbon::parse($this->end_time)->format('g:i A');
    }

    /**
     * Human label for the assigned time-of-day period, if any.
     */
    public function periodLabel(): ?string
    {
        if (! $this->time_period) {
            return null;
        }

        return config("routines.periods.{$this->time_period}.label") ?? ucfirst($this->time_period);
    }

    public function periodIcon(): ?string
    {
        if (! $this->time_period) {
            return null;
        }

        return config("routines.periods.{$this->time_period}.icon");
    }

    public function periodColor(): ?string
    {
        if (! $this->time_period) {
            return null;
        }

        return config("routines.periods.{$this->time_period}.color");
    }

    /**
     * Scheduling reference (minutes from midnight) used by the tracker:
     * the exact start time, or the approximate hour of the assigned period.
     */
    public function scheduledReferenceMinutes(): ?int
    {
        if ($this->start_time) {
            $start = Carbon::parse($this->start_time);

            return $start->hour * 60 + $start->minute;
        }

        if ($this->time_period) {
            $at = config("routines.periods.{$this->time_period}.at");

            return $at !== null ? ((int) $at) * 60 : null;
        }

        return null;
    }

    /**
     * Completion-time analytics based on stored `completed_at` timestamps.
     */
    public function completionTracker(?Carbon $since = null): array
    {
        $since = ($since ?? now()->subMonths(3))->startOfDay();

        $rows = $this->completions()
            ->whereNotNull('completed_at')
            ->where('completed_date', '>=', $since->toDateString())
            ->get(['completed_at']);

        $hours = array_fill(0, 24, 0);
        $minutesOfDay = [];

        foreach ($rows as $row) {
            $at = Carbon::parse($row->completed_at);
            $hours[$at->hour]++;
            $minutesOfDay[] = $at->hour * 60 + $at->minute;
        }

        $count = count($minutesOfDay);
        $reference = $this->scheduledReferenceMinutes();
        $avgMinutes = $count ? (int) round(array_sum($minutesOfDay) / $count) : null;
        $avgOffset = ($count && $reference !== null)
            ? (int) round((array_sum($minutesOfDay) / $count) - $reference)
            : null;

        return [
            'count' => $count,
            'hours' => $hours,
            'avgMinutes' => $avgMinutes,
            'avgOffset' => $avgOffset,
            'reference' => $reference,
        ];
    }

    /**
     * Whether the routine has any schedule hint (period or exact time).
     */
    public function hasSchedule(): bool
    {
        return $this->time_period !== null || ($this->start_time && $this->end_time);
    }

    /**
     * The single ordering used everywhere (Routines page, My Day, Dashboard):
     * time-period slot -> manual drag order -> exact start time -> title.
     * When steps have schedules and an active step is resolved, its schedule is used.
     * Fixed-width pieces keep plain string comparison byte-wise correct.
     */
    public function sortKey(): string
    {
        $period = $this->time_period;
        $startTime = $this->start_time;

        if (! empty($this->activeStepSchedule)) {
            if (! empty($this->activeStepSchedule['time_period'])) {
                $period = $this->activeStepSchedule['time_period'];
            }
            if (! empty($this->activeStepSchedule['scheduled_time'])) {
                $startTime = $this->activeStepSchedule['scheduled_time'];
            }
        }

        $slot = $period
            ? (int) config("routines.periods.{$period}.order", 99)
            : ($startTime ? $this->hourToPeriodSlot($startTime) : 99);

        $time = $startTime
            ? Carbon::parse($startTime)->format('H:i')
            : '99:99';

        return str_pad((string) $slot, 2, '0', STR_PAD_LEFT)
            . str_pad((string) (int) $this->sort_order, 4, '0', STR_PAD_LEFT)
            . $time
            . '-' . mb_strtolower((string) $this->title);
    }

    private function hourToPeriodSlot(string $time): int
    {
        try {
            $h = (int) Carbon::parse($time)->format('G');
            if ($h < 12) {
                return 1;
            }
            if ($h < 14) {
                return 2;
            }
            if ($h < 18) {
                return 3;
            }
            if ($h < 21) {
                return 4;
            }

            return 5;
        } catch (\Exception $e) {
            return 99;
        }
    }

    /**
     * Does this routine fall on the given date?
     */
    public function occursOn($date): bool
    {
        $date = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();

        switch ($this->frequency) {
            case 'daily':
                return true;

            case 'weekly':
                return in_array(strtolower($date->format('l')), $this->decodedDays(), true);

            case 'monthly':
                return in_array((int) $date->day, $this->decodedMonthDays(), true);

            case 'every_n_days':
                $n = max(2, (int) $this->every_n_days);
                $base = $this->created_at ? $this->created_at->copy()->startOfDay() : $date->copy();

                /* Anchored to creation day: runs on day 0, n, 2n, … */
                return (int) round($date->copy()->diffInDays($base, true)) % $n === 0;
        }

        return false;
    }

    public function completedOn($date): bool
    {
        if ($this->relationLoaded('completions')) {
            $key = RoutineCompletion::dateKey($date);

            return $this->completions->contains(fn ($c) => $this->completionDateEquals($c, $key));
        }

        return $this->completionRecord($date) !== null;
    }

    public function completionRecord($date): ?RoutineCompletion
    {
        $key = RoutineCompletion::dateKey($date);

        if ($this->relationLoaded('completions')) {
            return $this->completions->first(fn ($c) => $this->completionDateEquals($c, $key));
        }

        return $this->completions()
            ->where('completed_date', $key)
            ->first();
    }

    /**
     * Toggle completion for the given date. Returns true when now completed.
     * Idempotent: re-checking after an uncheck never creates duplicate rows.
     */
    public function toggleOn($date): bool
    {
        $key = RoutineCompletion::dateKey($date);

        $existing = $this->completions()
            ->where('user_id', $this->user_id)
            ->where('completed_date', $key)
            ->get();

        if ($existing->isNotEmpty()) {
            $existing->each->delete();

            return false;
        }

        RoutineCompletion::create([
            'user_id' => $this->user_id,
            'routine_id' => $this->id,
            'completed_date' => $key,
            'completed_at' => now(),
        ]);

        return true;
    }

    public function isAvoid(): bool
    {
        return ($this->behavior_type ?? self::BEHAVIOR_BUILD) === self::BEHAVIOR_AVOID;
    }

    public function violations(): HasMany
    {
        return $this->hasMany(RoutineViolation::class);
    }

    public function routineNotes(): HasMany
    {
        return $this->hasMany(RoutineNote::class);
    }

    /**
     * Violation date keys (Y-m-d) within the range.
     * Uses the eager-loaded `violations` relation when available (no query).
     */
    public function violatedDateKeys(Carbon $start, Carbon $end): array
    {
        $from = $start->toDateString();
        $to = $end->toDateString();

        $keys = $this->relationLoaded('violations')
            ? $this->violations
                ->map(fn ($v) => $v->occurred_date instanceof Carbon
                    ? $v->occurred_date->toDateString()
                    : Carbon::parse($v->occurred_date)->toDateString())
                ->filter(fn ($key) => $key >= $from && $key <= $to)
                ->values()
                ->all()
            : $this->violations()
                ->whereBetween('occurred_date', [$from, $to])
                ->pluck('occurred_date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->all();

        return array_values(array_unique($keys));
    }

    /**
     * Total slip quantity on a date (routine-level + all steps).
     */
    public function violationQtyOn($date): int
    {
        $key = RoutineViolation::dateKey($date);

        $q = $this->relationLoaded('violations')
            ? $this->violations->filter(fn ($v) => ($v->occurred_date instanceof Carbon
                ? $v->occurred_date->toDateString()
                : Carbon::parse($v->occurred_date)->toDateString()) === $key)
            : $this->violations()->where('occurred_date', $key)->get();

        return (int) $q->sum('quantity');
    }

    public function violatedOn($date): bool
    {
        return $this->violationQtyOn($date) > 0;
    }

    /**
     * Avoid-aware metrics over the trailing number of days.
     * $violatedSet is a flipped [Y-m-d => true] map (optional, avoids queries).
     * Mirrors adherence()/habitMetrics() semantics: today is excluded until
     * violated — a violation today breaks the streak immediately.
     */
    public function avoidMetrics(Carbon $date, int $days = 30, ?array $violatedSet = null): array
    {
        $date = $date->copy()->startOfDay();
        $from = $date->copy()->subDays($days - 1);

        $occurrences = $this->occurrenceDates($from, $date);
        if ($violatedSet === null) {
            $violatedSet = array_flip($this->violatedDateKeys($from, $date));
        }
        $todayKey = $date->toDateString();

        if ($occurrences && end($occurrences) === $todayKey && ! isset($violatedSet[$todayKey])) {
            array_pop($occurrences);
        }

        $clean = count(array_diff($occurrences, array_keys($violatedSet)));

        $current = 0;
        for ($i = count($occurrences) - 1; $i >= 0; $i--) {
            if (isset($violatedSet[$occurrences[$i]])) {
                break;
            }
            $current++;
        }

        $best = 0;
        $run = 0;
        foreach ($occurrences as $d) {
            if (isset($violatedSet[$d])) {
                $run = 0;
            } else {
                $best = max($best, ++$run);
            }
        }

        $now = now()->startOfDay();
        $last7 = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $date->copy()->subDays($i);
            $key = $day->toDateString();
            $occurs = $this->occursForStats($day);

            $state = 'na';
            if ($day->isFuture() || $day->isSameDay($now)) {
                $state = isset($violatedSet[$key]) ? 'violated' : ($day->isSameDay($now) ? 'today' : 'future');
            } elseif ($occurs) {
                $state = isset($violatedSet[$key]) ? 'violated' : 'done';
            }

            $last7[] = ['date' => $key, 'state' => $state];
        }

        return [
            'clean' => $clean,
            'total' => count($occurrences),
            'rate' => count($occurrences) ? (int) round($clean / count($occurrences) * 100) : 0,
            'current' => $current,
            'best' => $best,
            'last7' => $last7,
        ];
    }

    /**
     * Scheduled date keys (Y-m-d) within the range, ignoring days before creation.
     */
    public function occurrenceDates(Carbon $start, Carbon $end): array
    {
        $dates = [];
        $cursor = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if ($this->occursForStats($cursor)) {
                $dates[] = $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return $dates;
    }

    /**
      * Completion date keys (Y-m-d) within the range.
      * Uses the eager-loaded `completions` relation when available (no query).
      */
    public function completionDateKeys(Carbon $start, Carbon $end): array
    {
        $from = $start->toDateString();
        $to = $end->toDateString();

        if ($this->relationLoaded('completions')) {
            return $this->completions
                ->map(fn ($c) => $c->completed_date instanceof Carbon
                    ? $c->completed_date->toDateString()
                    : Carbon::parse($c->completed_date)->toDateString())
                ->filter(fn ($key) => $key >= $from && $key <= $to)
                ->values()
                ->all();
        }

        return $this->completions()
            ->whereBetween('completed_date', [$from, $to])
            ->pluck('completed_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();
    }

    /**
     * Current streak, best streak and adherence across the last year.
     */
    public function streakStats(?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $from = $today->copy()->subYear();

        $occurrences = $this->occurrenceDates($from, $today);
        $completed = array_flip($this->completionDateKeys($from, $today));

        // The current day is not over yet, so don't count it as a miss.
        if ($occurrences && end($occurrences) === $today->toDateString() && ! isset($completed[$today->toDateString()])) {
            array_pop($occurrences);
        }

        $current = 0;
        for ($i = count($occurrences) - 1; $i >= 0; $i--) {
            if (! isset($completed[$occurrences[$i]])) {
                break;
            }

            $current++;
        }

        $best = 0;
        $run = 0;
        foreach ($occurrences as $date) {
            if (isset($completed[$date])) {
                $best = max($best, ++$run);
            } else {
                $run = 0;
            }
        }

        $done = count(array_intersect($occurrences, array_keys($completed)));

        return [
            'current' => $current,
            'best' => $best,
            'completed' => $done,
            'total' => count($occurrences),
            'rate' => count($occurrences) ? (int) round($done / count($occurrences) * 100) : 0,
        ];
    }

    /**
     * Compact habit metrics for the Day-page Habit Ring:
     * 30-day adherence rate, current streak and the last 7 squares.
     */
    public function habitMetrics(Carbon $date, int $ringDays = 30): array
    {
        $date = $date->copy()->startOfDay();

        $rate = $this->adherence($ringDays, $date)['rate'];
        $streak = $this->streakStats($date)['current'];

        $from = $date->copy()->subDays(6);
        $completedKeys = array_flip($this->completionDateKeys($from, $date));

        $last7 = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $date->copy()->subDays($i);
            $key = $day->toDateString();
            $occurs = $this->occursForStats($day);

            $state = 'na';
            if ($day->isFuture() || $day->isSameDay(now())) {
                $state = $day->isSameDay(now()) ? 'today' : 'future';
            } elseif ($occurs) {
                $state = isset($completedKeys[$key]) ? 'done' : 'missed';
            }

            $last7[] = [
                'date' => $key,
                'state' => $state,
            ];
        }

        return ['rate' => $rate, 'streak' => $streak, 'last7' => $last7];
    }

    /**
     * Adherence over the trailing number of days.
     */
    public function adherence(int $days = 30, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $from = $today->copy()->subDays($days - 1);

        $occurrences = $this->occurrenceDates($from, $today);
        $completed = array_flip($this->completionDateKeys($from, $today));

        if ($occurrences && end($occurrences) === $today->toDateString() && ! isset($completed[$today->toDateString()])) {
            array_pop($occurrences);
        }

        $done = count(array_intersect($occurrences, array_keys($completed)));

        return [
            'completed' => $done,
            'total' => count($occurrences),
            'rate' => count($occurrences) ? (int) round($done / count($occurrences) * 100) : 0,
        ];
    }

    /**
     * Per-day cell data (keyed by Y-m-d) for a contribution-style heatmap.
     */
    public function heatmapCells(Carbon $start, Carbon $end): array
    {
        $completed = array_flip($this->completionDateKeys($start, $end));
        $today = now()->startOfDay();

        $cells = [];
        $cursor = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $occurs = $this->occursForStats($cursor);

            $cells[$key] = [
                'date' => $cursor->copy(),
                'occurs' => $occurs,
                'completed' => $occurs && isset($completed[$key]),
                'future' => $cursor->gt($today),
            ];

            $cursor->addDay();
        }

        return $cells;
    }

    private function occursForStats(Carbon $date): bool
    {
        if ($this->created_at && $date->lt($this->created_at->copy()->startOfDay())) {
            return false;
        }

        return $this->occursOn($date);
    }

    private function completionDateEquals($completion, string $key): bool
    {
        $value = $completion->completed_date ?? null;

        if ($value instanceof Carbon) {
            return $value->toDateString() === $key;
        }

        if (is_string($value) && strlen($value) >= 10) {
            return substr($value, 0, 10) === $key;
        }

        return $completion->getAttribute('completed_date') == $key;
    }

    private function decode($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
