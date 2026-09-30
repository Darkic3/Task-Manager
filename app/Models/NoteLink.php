<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NoteLink extends Model
{
    use HasFactory;

    public const SUBJECT = NoteSubject::class;

    protected $fillable = [
        'note_id',
        'linkable_type',
        'linkable_id',
        'label',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('linkable_type', $type);
    }

    public function scopeForType($query, string ...$types)
    {
        return $query->whereIn('linkable_type', $types);
    }
}