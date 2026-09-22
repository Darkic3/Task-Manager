<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Routine extends Model
{
    use HasFactory;

    public const WEEK_DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'frequency',
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
        if (! $this->start_time || ! $this->end_time) {
            return '';
        }

        return Carbon::parse($this->start_time)->format('g:i A')
            .' – '.Carbon::parse($this->end_time)->format('g:i A');
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
        return $this->completions()
            ->where('completed_date', RoutineCompletion::dateKey($date))
            ->exists();
    }

    /**
     * Toggle completion for the given date. Returns true when now completed.
     */
    public function toggleOn($date): bool
    {
        $key = RoutineCompletion::dateKey($date);

        $existing = $this->completions()
            ->where('user_id', $this->user_id)
            ->where('completed_date', $key)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        RoutineCompletion::create([
            'user_id' => $this->user_id,
            'routine_id' => $this->id,
            'completed_date' => $key,
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
