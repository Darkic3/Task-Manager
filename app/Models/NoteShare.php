<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NoteShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ai_conversation_id',
        'name',
        'slug',
        'description',
        'filters',
        'note_count',
        'char_count',
    ];

    protected $casts = [
        'filters' => 'array',
        'note_count' => 'integer',
        'char_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class, 'note_share_note')->withTimestamps();
    }

    public function scopeOfUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}