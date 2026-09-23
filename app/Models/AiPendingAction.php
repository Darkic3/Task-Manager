<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AiPendingAction extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_EXPIRED = 'expired';

    public const EXPIRY_MINUTES = 15;

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
    ];

    protected $casts = [
        'args' => 'array',
        'preview' => 'array',
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
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
}
