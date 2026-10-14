<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayLog extends Model
{
    use HasFactory;

    public const KIND_ACTIVITY = 'activity';
    public const KIND_JOURNAL = 'journal';

    public const UNITS = ['minute', 'hour', 'page', 'verse', 'count', 'km'];

    protected $fillable = [
        'user_id',
        'date',
        'kind',
        'title',
        'note',
        'period',
        'duration_seconds',
        'amount',
        'amount_unit',
        'start_time',
        'end_time',
        'logged_at',
        'mood',
        'mood_picked',
        'is_quit',
        'time_entry_id',
    ];

    protected $casts = [
        'date' => 'date',
        'duration_seconds' => 'integer',
        'amount' => 'decimal:2',
        'mood' => 'integer',
        'mood_picked' => 'boolean',
        'is_quit' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function timeEntry()
    {
        return $this->belongsTo(TimeEntry::class);
    }

    /**
     * Normalized title used for grouping repeats (case-insensitive, trimmed).
     */
    public static function normalizedTitle(?string $title): string
    {
        return mb_strtolower(trim((string) $title));
    }

    /**
     * Display mode derived from the stored fields (mirrors the planner UI).
     */
    public function mode(): string
    {
        if ($this->start_time && $this->end_time) {
            return 'range';
        }
        if ($this->duration_seconds || $this->amount !== null) {
            return 'duration';
        }
        if ($this->period) {
            return 'period';
        }

        return 'none';
    }

    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'kind' => $this->kind,
            'title' => $this->title,
            'note' => $this->note,
            'period' => $this->period,
            'mode' => $this->mode(),
            'duration_seconds' => $this->duration_seconds,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'amount_unit' => $this->amount_unit,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'logged_at' => $this->logged_at,
            'mood' => $this->mood,
            'mood_picked' => (bool) $this->mood_picked,
            'is_quit' => (bool) $this->is_quit,
            'time_entry_id' => $this->time_entry_id,
        ];
    }
}
