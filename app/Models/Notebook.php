<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Notebook extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parent_id',
        'title',
        'color',
        'icon',
        'description',
        'sort_order',
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(NoteRevision::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOfUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * This notebook plus every descendant id (breadth-first, cycle safe).
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];
        $guard = 0;

        while ($frontier !== [] && $guard++ < 20) {
            $children = self::whereIn('parent_id', $frontier)->pluck('id')->all();
            $children = array_values(array_diff($children, $ids));

            if ($children === []) {
                break;
            }

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    /**
     * All notebook ids under an optional root (null = every notebook).
     *
     * @return array<int, int>
     */
    public static function subtreeIds(int $userId, ?int $rootId = null): array
    {
        if ($rootId === null) {
            return self::ofUser($userId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $root = self::find($rootId);

        return $root && $root->user_id === $userId ? $root->descendantIds() : [];
    }

    /**
     * Full notebook tree for a user, ordered, each node carrying its children.
     */
    public static function treeFor(int $userId): Collection
    {
        $all = self::ofUser($userId)->orderBy('sort_order')->orderBy('title')->get();
        $byParent = $all->groupBy(fn ($nb) => $nb->parent_id ?? 0);
        $counts = Note::ofUser($userId)
            ->whereNotNull('notebook_id')
            ->where('status', '!=', Note::STATUS_ARCHIVED)
            ->selectRaw('notebook_id, count(*) as aggregate')
            ->groupBy('notebook_id')
            ->pluck('aggregate', 'notebook_id');

        $build = function ($parentId) use (&$build, $byParent, $counts, $all): Collection {
            return $byParent->get($parentId ?? 0, collect())->map(function ($nb) use (&$build, $counts) {
                $nb->direct_notes_count = (int) ($counts[$nb->id] ?? 0);
                $nb->children = $build($nb->id);

                return $nb;
            })->values();
        };

        $tree = $build(null);
        $attachTotals = function ($nodes) use (&$attachTotals, $counts): void {
            foreach ($nodes as $node) {
                $node->notes_count = (int) ($counts[$node->id] ?? 0);
                foreach ($node->children as $child) {
                    $node->notes_count += (int) $child->notes_count;
                }
                $attachTotals($node->children);
            }
        };
        $attachTotals($tree);

        return $tree;
    }

    /**
     * "Parent / Child" breadcrumb path from the root down to this notebook.
     */
    public function pathTitle(): string
    {
        $parts = [$this->title];
        $current = $this;
        $guard = 0;

        while ($current->parent_id && $guard++ < 20) {
            $current = $current->parent ?: self::find($current->parent_id);
            if (! $current) {
                break;
            }
            array_unshift($parts, $current->title);
        }

        return implode(' / ', $parts);
    }
}