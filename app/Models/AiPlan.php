<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AiPlan extends Model
{
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_EXECUTING = 'executing';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const EXPIRY_MINUTES = 15;

    // Caps per plan: 1 project, 3 sub-projects, 30 tasks, 100 subtasks.
    public const MAX_SUBPROJECTS = 3;
    public const MAX_TASKS = 30;
    public const MAX_SUBTASKS = 100;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'title',
        'structure',
        'phases',
        'status',
        'current_phase',
        'expires_at',
        'executed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'structure' => 'array',
        'phases' => 'array',
        'expires_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->greaterThan($this->expires_at);
    }

    public function isActionable(): bool
    {
        return in_array($this->status, [self::STATUS_PROPOSED, self::STATUS_CONFIRMED, self::STATUS_EXECUTING], true)
            && ! $this->isExpired();
    }

    public function touchExpiry(): void
    {
        $this->expires_at = now()->addMinutes(self::EXPIRY_MINUTES);
        $this->save();
    }

    public function currentPhase(): ?array
    {
        return $this->phases[$this->current_phase] ?? null;
    }
}
