<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'linkable_type',
        'linkable_id',
        'label',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function linkable()
    {
        return $this->morphTo();
    }
}
