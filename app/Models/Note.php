<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Note extends Model
{
    use HasFactory;

    public const KIND_GENERAL = 'general';

    public const KIND_DAILY = 'daily';

    public const KIND_EVENT = 'event';

    public const KIND_PERSON = 'person';

    public const KIND_TOPIC = 'topic';

    public const KIND_MEETING = 'meeting';

    public const KIND_DECISION = 'decision';

    public const KIND_REFERENCE = 'reference';

    public const KIND_IDEA = 'idea';

    public const KIND_LOG = 'log';

    /**
     * Note types. The first four cover the everyday split (daily journal,
     * something that happened, a person, a subject); the rest are opt-in
     * specialised shapes.
     */
    public const KINDS = [
        self::KIND_GENERAL,
        self::KIND_DAILY,
        self::KIND_EVENT,
        self::KIND_PERSON,
        self::KIND_TOPIC,
        self::KIND_MEETING,
        self::KIND_DECISION,
        self::KIND_REFERENCE,
        self::KIND_IDEA,
        self::KIND_LOG,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_DRAFT,
        self::STATUS_ARCHIVED,
    ];

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_SHARED = 'shared';

    public const VISIBILITIES = [
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_SHARED,
    ];

    protected $fillable = [
        'user_id',
        'notebook_id',
        'parent_id',
        'title',
        'content',
        'summary',
        'kind',
        'category',
        'tags',
        'is_favorite',
        'is_pinned',
        'mood',
        'energy',
        'visibility',
        'status',
        'occurred_at',
        'date',
        'time',
        'ai_metadata',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'is_pinned' => 'boolean',
        'tags' => 'array',
        'mood' => 'integer',
        'energy' => 'integer',
        'ai_metadata' => 'array',
        'date' => 'datetime',
        'occurred_at' => 'datetime',
    ];

    // ── relations ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }

    public function parentNote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function childNotes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(NoteLabel::class, 'label_note', 'note_id', 'note_label_id')
            ->withTimestamps();
    }

    public function links(): HasMany
    {
        return $this->hasMany(NoteLink::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NoteAttachment::class)->orderBy('sort_order');
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'note_attachments', 'note_id', 'file_id')
            ->withPivot(['caption', 'sort_order']);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(NoteRevision::class);
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(NoteRevision::class)->latestOfMany();
    }

    public function shares(): BelongsToMany
    {
        return $this->belongsToMany(NoteShare::class, 'note_share_note')->withTimestamps();
    }

    // ── scopes ─────────────────────────────────────────────────

    public function scopeOfUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Legacy free-text category filter (kept for pre-existing notes).
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOfKind($query, $kinds)
    {
        $kinds = array_values(array_intersect((array) $kinds, self::KINDS));

        return $kinds === [] ? $query : $query->whereIn('kind', $kinds);
    }

    public function scopeOfStatus($query, $statuses)
    {
        $statuses = array_values(array_intersect((array) $statuses, self::STATUSES));

        return $statuses === [] ? $query : $query->whereIn('status', $statuses);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeInNotebook($query, ?int $notebookId, int $userId)
    {
        if ($notebookId === null) {
            return $query->whereNull('notebook_id');
        }

        return $query->whereIn('notebook_id', Notebook::subtreeIds($userId, $notebookId));
    }

    public function scopeWithLabel($query, array $labelIds)
    {
        $labelIds = array_filter(array_map('intval', $labelIds));

        return $labelIds === []
            ? $query
            : $query->whereHas('labels', fn ($q) => $q->whereIn('note_labels.id', $labelIds));
    }

    public function scopeLinkedTo($query, string $type, int $id)
    {
        return $query->whereHas('links', fn ($q) => $q
            ->where('linkable_type', $type)
            ->where('linkable_id', $id));
    }

    public function scopeOnDate($query, $date)
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $query->where(function ($q) use ($date) {
            $q->whereDate('occurred_at', $date->toDateString())
                ->orWhere(function ($q) use ($date) {
                    $q->whereNull('occurred_at')->whereDate('date', $date->toDateString());
                });
        });
    }

    public function scopeBetween($query, $from, $to)
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : null;
        $to = $to ? Carbon::parse($to)->endOfDay() : null;

        return $query->where(function ($q) use ($from, $to) {
            if ($from) {
                $q->where(function ($q) use ($from) {
                    $q->where('occurred_at', '>=', $from)->orWhere(function ($q) use ($from) {
                        $q->whereNull('occurred_at')->where('date', '>=', $from->toDateString());
                    });
                });
            }
            if ($to) {
                $q->where(function ($q) use ($to) {
                    $q->where('occurred_at', '<=', $to)->orWhere(function ($q) use ($to) {
                        $q->whereNull('occurred_at')->where('date', '<=', $to->toDateString());
                    });
                });
            }
        });
    }

    /**
     * Keyword search across title, body, AI summary, curated labels,
     * linked entity names and the legacy category / tags columns.
     */
    public function scopeSearch($query, $search)
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';

        return $query->where(function ($q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('content', 'like', $like)
                ->orWhere('summary', 'like', $like)
                // legacy free-text facets
                ->orWhere('category', 'like', $like)
                ->orWhere('tags', 'like', $like)
                ->orWhereHas('labels', fn ($l) => $l->where('name', 'like', $like))
                ->orWhereHas('links', fn ($l) => $l->where('label', 'like', $like));
        });
    }

    /**
     * Chronological ordering on the effective date (occurred_at, then the
     * legacy date, then creation time).
     */
    public function scopeChronological(Builder $query, string $direction = 'desc')
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $query->orderByRaw('occurred_at is null')->orderBy('occurred_at', $direction)
            ->orderByRaw('date is null')->orderBy('date', $direction)
            ->orderBy('created_at', $direction);
    }

    // ── accessors ──────────────────────────────────────────────

    /**
     * The date the note is *about*, as opposed to created_at.
     */
    public function effectiveDate(): ?Carbon
    {
        return $this->occurred_at ?: $this->date ?: ($this->created_at ? $this->created_at->copy() : null);
    }

    public function getEffectiveDateAttribute(): ?Carbon
    {
        return $this->effectiveDate();
    }

    /**
     * The note body rendered as HTML. The show page, the editor preview and
     * the cards all go through App\Support\MarkdownRenderer, so the raw
     * Markdown in $note->content is never printed directly.
     */
    public function getContentHtmlAttribute(): string
    {
        return \App\Support\MarkdownRenderer::toHtml((string) $this->content);
    }

    /**
     * Same render, with the @person / #label tokens this note actually
     * resolved turned into links back into the notes index (filters).
     */
    public function renderedBody(): string
    {
        $map = [];

        if ($this->relationLoaded('labels')) {
            foreach ($this->labels as $label) {
                $token = '#'.$label->name;
                $map[$token] = '<a class="nt-token nt-token-lbl" href="'.e(route('notes.index', ['label' => $label->name])).'">'.e($token).'</a>';
            }
        }

        if ($this->relationLoaded('links')) {
            foreach ($this->links as $link) {
                if (! $link->linkable_type) {
                    continue;
                }
                $token = '@'.$link->label;
                if (isset($map[$token])) {
                    continue;
                }
                $url = route('notes.index', [
                    'linked_type' => $link->linkable_type,
                    'linked_id' => $link->linkable_id,
                ]);
                $map[$token] = '<a class="nt-token nt-token-psn" href="'.e($url).'">'.e($token).'</a>';
            }
        }

        return \App\Support\MarkdownRenderer::toHtmlLinked((string) $this->content, $map);
    }

    public function getExcerptAttribute($length = 160)
    {
        $text = \App\Support\MarkdownRenderer::toPlainText((string) $this->content);

        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > $length
            ? mb_substr($text, 0, $length).'…'
            : $text;
    }

    public function getWordCountAttribute(): int
    {
        $text = \App\Support\MarkdownRenderer::toPlainText((string) $this->content);

        if ($text === '') {
            return 0;
        }

        // Persian and Latin are both whitespace separated here.
        return count(preg_split('/\s+/u', $text) ?: []);
    }

    public function getReadingMinutesAttribute(): int
    {
        return (int) max(1, ceil($this->word_count / 200));
    }

    public function getFormattedDateAttribute(): ?string
    {
        $date = $this->effectiveDate();

        return $date ? app_date($date, app()->getLocale() === 'fa' ? 'Y/m/d' : 'M j, Y') : null;
    }

    public function getFormattedTimeAttribute(): ?string
    {
        if ($this->time) {
            return Carbon::parse($this->time)->format('H:i');
        }

        $date = $this->effectiveDate();

        return $date && ! $this->date ? $date->format('H:i') : null;
    }

    /**
     * Legacy free-text tags plus the curated labels, merged for display.
     */
    public function getAllTagsAttribute(): array
    {
        $out = [];

        foreach ((array) $this->tags as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $out[mb_strtolower(trim($tag))] = trim($tag);
            }
        }

        if ($this->relationLoaded('labels')) {
            foreach ($this->labels as $label) {
                $out[mb_strtolower($label->name)] = $label->name;
            }
        }

        return array_values($out);
    }

    /**
     * Notes whose body mentions this token (@name) — used for inline chips.
     */
    public function mentionsToken(string $token): bool
    {
        return $token !== '' && mb_stripos((string) $this->content, $token, 0, 'UTF-8') !== false;
    }

    public function linkedSubjects(): Collection
    {
        $ids = $this->links
            ->where('linkable_type', NoteSubject::class)
            ->pluck('linkable_id')
            ->all();

        return $ids === []
            ? collect()
            : NoteSubject::whereIn('id', $ids)->get();
    }

    public function isLegacy(): bool
    {
        return $this->kind === self::KIND_GENERAL
            && $this->notebook_id === null
            && $this->occurred_at === null;
    }
}