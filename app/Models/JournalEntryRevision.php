<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'title',
        'content',
        'mood_score',
        'tags_snapshot',
        'edited_at',
    ];

    protected $casts = [
        'tags_snapshot' => 'array',
        'edited_at' => 'datetime',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
