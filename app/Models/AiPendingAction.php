<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AiPendingAction extends Model
{
    /**
     * Canonical state model (stored strings kept stable for the UI dock):
     *
     *   OPEN:      pending    — awaiting user confirmation (only actionable state)
     *   EXECUTING: executing  — claimed by exactly one confirmer, running now
     *   EXECUTED:  executed   — terminal success (undoable while undone_at is null)
     *   FAILED:    failed     — terminal failure, error column holds the reason
     *   CANCELLED: rejected   — user cancelled (kept string for UI compat)
     *   EXPIRED:   expired    — past expires_at without confirmation
     *
     * Transitions are always single-row atomic updates guarded by the current
     * status (optimistic claim) inside a row-locked transaction, so double
     * click / retry / concurrent confirm can never double-execute.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXECUTING = 'executing';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    /** @deprecated Kept for backward compatibility; the claim now targets EXECUTING. */
    public const STATUS_CONFIRMED = 'confirmed';

    /** Terminal states: the action will never run (again). */
    public const TERMINAL_STATUSES = [
        self::STATUS_EXECUTED,
        self::STATUS_FAILED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
    ];

    public const EXPIRY_MINUTES = 15;

    /** Minutes after execution during which a reversible action can be undone. */
    public const UNDO_WINDOW_MINUTES = 10;

    // Max unconfirmed tool actions kept per user. Bulk requests (e.g. "add
    // 10 tasks/routines") arrive as many single tool calls, so this needs
    // enough headroom to hold the whole batch before the user confirms.
    public const MAX_OPEN = 30;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'tool',
        'args',
        'preview',
        'status',
        'expires_at',
        'executed_at',
        'idempotency_key',
        'error',
        'undo_data',
        'undone_at',
    ];

    protected $casts = [
        'args' => 'array',
        'preview' => 'array',
        'expires_at' => 'datetime',
        'executed_at' => 'datetime',
        'undo_data' => 'array',
        'undone_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->greaterThan($this->expires_at);
    }

    public function isActionable(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }

    public function markExpired(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->status = self::STATUS_EXPIRED;
        $this->save();

        return true;
    }

    public function markFailed(string $error): bool
    {
        if ($this->isTerminal()) {
            return false;
        }

        $this->status = self::STATUS_FAILED;
        $this->error = mb_substr($error, 0, 2000);
        $this->save();

        return true;
    }

    /** Reversible inside the undo window: executed, not undone, undo data present. */
    public function isUndoable(): bool
    {
        if ($this->status !== self::STATUS_EXECUTED || $this->isUndone() || empty($this->undo_data)) {
            return false;
        }
        if (! $this->executed_at) {
            return false;
        }

        return $this->executed_at->gt(now()->subMinutes(self::UNDO_WINDOW_MINUTES));
    }

    public function isUndoExpired(): bool
    {
        if (! $this->executed_at) {
            return true;
        }

        return $this->executed_at->lte(now()->subMinutes(self::UNDO_WINDOW_MINUTES));
    }
}
