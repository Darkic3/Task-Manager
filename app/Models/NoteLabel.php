<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NoteLabel extends Model
{
    use HasFactory;

    protected $table = 'note_labels';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'color',
        'icon',
        'description',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class, 'label_note', 'note_label_id', 'note_id')
            ->withTimestamps();
    }

    public function scopeOfUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}