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
