<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VocabReview extends Model
{
    use HasFactory;

    public const CREATED_AT = 'reviewed_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'vocab_card_id',
        'deck_id',
        'remembered',
        'box_before',
        'box_after',
        'reversed',
        'reviewed_at',
    ];

    protected $casts = [
        'remembered' => 'boolean',
        'box_before' => 'integer',
        'box_after' => 'integer',
        'reversed' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(VocabCard::class, 'vocab_card_id');
    }

    public function deck()
    {
        return $this->belongsTo(VocabDeck::class, 'deck_id');
    }

    public function scopeInRange($query, string $from, string $to)
    {
        return $query->whereDate('reviewed_at', '>=', $from)->whereDate('reviewed_at', '<=', $to);
    }

    public function scopeRemembered($query)
    {
        return $query->where('remembered', true);
    }

    public function scopeByDeck($query, int $deckId)
    {
        return $query->where('deck_id', $deckId);
    }
}
