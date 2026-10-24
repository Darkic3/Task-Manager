<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * P4 state machine (single source of truth for allowed transitions;
 * enforced in AiPlanController gates + tested in AiChainStabilityTest).
 *
 *   proposed   → confirmed | cancelled | expired | obsolete
 *   confirmed  → executing | cancelled | expired | failed*
 *   executing  → done | failed | cancelled | expired
 *   failed     → executing (retry) | cancelled | expired | obsolete
 *   done       → (terminal; rollback keeps status, cancel dedupes)
 *   cancelled  → (terminal; only gains superseded_by_plan_id at cancel time)
 *   obsolete   → (terminal)
 *   expired    → (terminal; recovery creates a NEW plan via reissue)
 *
 * *confirmed→failed happens when the first phase fails; the plan never
 * runs without confirmStructure first (not_confirmed guard).
 *
 * Stop vs Cancel vs Rollback: there is NO pause state (product decision —
 * not added without need). Cancel stops FUTURE execution (in-flight work
 * completes atomically under the plan row lock, then cancel applies to the
 * settled state). Cancel never deletes data; rollback deletes only
 * ledger-owned items after preview + token + explicit confirm.
 */
class AiPlan extends Model
{
    /**
     * Allowed transitions, enforced by controller gates. Terminal states
     * map to []. Kept in sync with the gates by AiChainStabilityTest.
     */
    public const TRANSITIONS = [
        self::STATUS_PROPOSED => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED, self::STATUS_EXPIRED, self::STATUS_OBSOLETE],
        self::STATUS_CONFIRMED => [self::STATUS_EXECUTING, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_EXECUTING => [self::STATUS_DONE, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_FAILED => [self::STATUS_EXECUTING, self::STATUS_CANCELLED, self::STATUS_EXPIRED, self::STATUS_OBSOLETE],
        self::STATUS_DONE => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_OBSOLETE => [],
        self::STATUS_EXPIRED => [],
    ];

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_EXECUTING = 'executing';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    /** P2: a package whose phase failed and awaits retry/replace. */
    public const STATUS_FAILED = 'failed';
    /** P2: a package retired by a (master or package) revision. */
    public const STATUS_OBSOLETE = 'obsolete';

    public const EXPIRY_MINUTES = 15;

    // Caps per plan: 5 root projects, 3 sub-projects each, 30 tasks,
    // 100 subtasks, 10 reminders, 10 notes.
    public const MAX_PROJECTS = 5;
    public const MAX_SUBPROJECTS = 3;
    public const MAX_TASKS = 30;
    public const MAX_SUBTASKS = 100;
    public const MAX_REMINDERS = 10;
    public const MAX_NOTES = 10;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'title',
        'structure',
        'created_items',
        'phases',
        'status',
        'current_phase',
        'expires_at',
        'executed_at',
        'last_error',
        'master_build_id',
        'master_version',
        'package_index',
        'idempotency_key',
        'superseded_by_plan_id',
    ];

    protected $casts = [
        'structure' => 'array',
        'created_items' => 'array',
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

    /** The replacement plan, when this one was cancelled as superseded. */
    public function supersededBy()
    {
        return $this->belongsTo(self::class, 'superseded_by_plan_id');
    }

    public function isSuperseded(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_OBSOLETE], true)
            && $this->superseded_by_plan_id !== null;
    }

    /** P2: retired by a restructure (master or package revision). */
    public function isObsolete(): bool
    {
        return $this->status === self::STATUS_OBSOLETE;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->greaterThan($this->expires_at);
    }

    public function isActionable(): bool
    {
        return in_array($this->status, [self::STATUS_PROPOSED, self::STATUS_CONFIRMED, self::STATUS_EXECUTING, self::STATUS_FAILED], true)
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
