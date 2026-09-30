<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoteSubject extends Model
{
    use HasFactory;

    public const TYPE_PERSON = 'person';

    public const TYPE_PLACE = 'place';

    public const TYPE_TOPIC = 'topic';

    public const TYPE_ORG = 'org';

    public const TYPES = [
        self::TYPE_PERSON,
        self::TYPE_PLACE,
        self::TYPE_TOPIC,
        self::TYPE_ORG,
    ];

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'type',
        'description',
        'meta',
        'usage_count',
    ];

    protected $casts = [
        'meta' => 'array',
        'usage_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(NoteLink::class, 'linkable_id')
            ->where('linkable_type', self::class);
    }

    public function notes(): HasMany
    {
        return $this->links()->hasMany(Note::class, 'note_id');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}