<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteRevision extends Model
{
    use HasFactory;

    public const SOURCE_USER = 'user';

    public const SOURCE_AI = 'ai';

    public const SOURCE_SYSTEM = 'system';

    protected $fillable = [
        'note_id',
        'user_id',
        'title',
        'content',
        'kind',
        'notebook_id',
        'change_source',
        'reason',
        'changed_fields',
    ];

    protected $casts = [
        'changed_fields' => 'array',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }

    /**
     * Human readable summary of what this revision changed.
     */
    public function summaryText(): string
    {
        $fields = $this->changed_fields ?: [];
        if ($fields === []) {
            return __('Note updated');
        }

        return implode(' · ', array_map(fn ($f) => (string) $f, $fields));
    }
}