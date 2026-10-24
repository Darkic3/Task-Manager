<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AiStudyBuild extends Model
{
    public const STATUS_BUILDING = 'building';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    /** Chain-level window: the user may confirm one chunk per day. */
    public const EXPIRY_HOURS = 24;

    /** Hard cap: version history stays a small bounded JSON document. */
    public const MAX_VERSIONS = 10;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'title',
        'outline_json',
        'chunks_json',
        'plan_ids',
        'total_chunks',
        'done_chunks',
        'version',
        'approved_version',
        'approved_at',
        'approved_by',
        'versions',
        'book_project_id',
        'last_plan_id',
        'status',
        'last_error',
        'expires_at',
    ];

    protected $casts = [
        'outline_json' => 'array',
        'chunks_json' => 'array',
        'plan_ids' => 'array',
        'versions' => 'array',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function bookProject()
    {
        return $this->belongsTo(Project::class, 'book_project_id');
    }

    public function lastPlan()
    {
        return $this->belongsTo(AiPlan::class, 'last_plan_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->greaterThan($this->expires_at);
    }

    public function isActionable(): bool
    {
        return $this->status === self::STATUS_BUILDING && ! $this->isExpired();
    }

    /** 1-based index of the chunk awaiting confirmation, null when finished. */
    public function nextChunkIndex(): ?int
    {
        if ($this->done_chunks >= $this->total_chunks) {
            return null;
        }

        return $this->done_chunks + 1;
    }

    public function progress(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'done_chunks' => $this->done_chunks,
            'total_chunks' => $this->total_chunks,
            'book_project_id' => $this->book_project_id,
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
