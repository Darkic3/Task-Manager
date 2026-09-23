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

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'frequency',
        'time_period',
        'days',
        'weeks',
        'months',
        'month_days',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'days' => 'array',
        'weeks' => 'array',
        'months' => 'array',
        'month_days' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(RoutineCompletion::class);
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
     * Ordering key: exact times first, then periods, then unscheduled.
     */
    public function sortKey(): string
    {
        if ($this->start_time) {
            return '1'.$this->start_time;
        }

        if ($this->time_period) {
            $order = (int) config("routines.periods.{$this->time_period}.order", 99);

            return '2'.str_pad((string) $order, 2, '0', STR_PAD_LEFT);
        }

        return '9';
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
        }

        return false;
    }

    public function completedOn($date): bool
    {
        return $this->completionRecord($date) !== null;
    }

    public function completionRecord($date): ?RoutineCompletion
    {
        return $this->completions()
            ->where('completed_date', RoutineCompletion::dateKey($date))
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
     */
    public function completionDateKeys(Carbon $start, Carbon $end): array
    {
        return $this->completions()
            ->whereBetween('completed_date', [$start->toDateString(), $end->toDateString()])
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
