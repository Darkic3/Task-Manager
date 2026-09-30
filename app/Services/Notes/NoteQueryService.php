<?php

namespace App\Services\Notes;

use App\Models\Note;
use App\Models\NoteLabel;
use App\Models\Notebook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoteQueryService
{
    public const SORTS = ['recent', 'oldest', 'title'];

    /**
     * Whitelist-filtered filter set from an incoming request.
     */
    public function filtersFromRequest(Request $request): array
    {
        $kind = array_values(array_intersect(
            (array) $request->query('kind', []),
            Note::KINDS
        ));

        $labels = array_values(array_filter(array_map(
            'intval',
            (array) $request->query('label', [])
        )));

        return [
            'search' => trim((string) $request->query('search', '')) ?: null,
            'kind' => $kind,
            'notebook' => $request->filled('notebook') ? (int) $request->query('notebook') : null,
            'label' => $labels,
            'favorite' => $request->boolean('favorite'),
            'pinned' => $request->boolean('pinned'),
            'archived' => $request->boolean('archived'),
            'from' => $request->query('from') ?: null,
            'to' => $request->query('to') ?: null,
            'linked_type' => $request->query('linked_type') ?: null,
            'linked_id' => $request->filled('linked_id') ? (int) $request->query('linked_id') : null,
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'recent',
            'view' => $request->query('view') === 'timeline' ? 'timeline' : 'list',
        ];
    }

    /**
     * Build the note query for a user from a filter set.
     */
    public function build(int $userId, array $filters): Builder
    {
        $query = Note::ofUser($userId);

        $query->when(
            ! empty($filters['archived']),
            fn (Builder $q) => $q->archived(),
            fn (Builder $q) => $q->notArchived()
        );

        if (! empty($filters['kind'])) {
            $query->ofKind($filters['kind']);
        }

        if (! empty($filters['notebook'])) {
            $query->inNotebook((int) $filters['notebook'], $userId);
        }

        if (! empty($filters['label'])) {
            $query->withLabel($filters['label']);
        }

        if (! empty($filters['favorite'])) {
            $query->favorites();
        }

        if (! empty($filters['pinned'])) {
            $query->pinned();
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $query->between($filters['from'] ?? null, $filters['to'] ?? null);
        }

        if (! empty($filters['linked_type']) && ! empty($filters['linked_id'])) {
            $query->linkedTo($filters['linked_type'], (int) $filters['linked_id']);
        }

        return $this->sort($query, $filters['sort'] ?? 'recent');
    }

    private function sort(Builder $query, string $sort): Builder
    {
        $query->orderByDesc('is_pinned');

        return match ($sort) {
            'oldest' => $query->chronological('asc'),
            'title' => $query->orderBy('title'),
            default => $query->chronological('desc'),
        };
    }

    /**
     * Sidebar counts. Each facet is counted with its own filter removed so the
     * numbers show what would happen if you clicked them.
     */
    public function facets(int $userId, array $filters): array
    {
        $total = $this->build($userId, $filters)->toBase()->getCountForPagination();

        $kindCounts = $this->build($userId, array_merge($filters, ['kind' => []]))
            ->selectRaw('kind, count(*) as aggregate')
            ->groupBy('kind')
            ->pluck('aggregate', 'kind');

        $labelCounts = DB::table('label_note')
            ->join('notes', 'notes.id', '=', 'label_note.note_id')
            ->where('notes.user_id', $userId)
            ->where('notes.status', '!=', Note::STATUS_ARCHIVED)
            ->selectRaw('label_note.note_label_id as id, count(*) as aggregate')
            ->groupBy('label_note.note_label_id')
            ->pluck('aggregate', 'id');

        $notebookCounts = $this->build($userId, array_merge($filters, ['notebook' => null]))
            ->whereNotNull('notebook_id')
            ->selectRaw('notebook_id, count(*) as aggregate')
            ->groupBy('notebook_id')
            ->pluck('aggregate', 'notebook_id');

        return [
            'total' => (int) $total,
            'kind' => collect(Note::KINDS)
                ->mapWithKeys(fn ($k) => [$k => (int) ($kindCounts[$k] ?? 0)])
                ->all(),
            'labels' => $labelCounts->map(fn ($v) => (int) $v)->all(),
            'notebooks' => $notebookCounts->map(fn ($v) => (int) $v)->all(),
            'favorite' => $this->build($userId, array_merge($filters, ['favorite' => false]))->favorites()->toBase()->getCountForPagination(),
            'pinned' => $this->build($userId, array_merge($filters, ['pinned' => false]))->pinned()->toBase()->getCountForPagination(),
            'today' => $this->build($userId, array_merge($filters, ['from' => null, 'to' => null]))
                ->onDate(now())->toBase()->getCountForPagination(),
            'archived' => Note::ofUser($userId)->archived()->toBase()->getCountForPagination(),
            'unfiled' => $this->build($userId, array_merge($filters, ['notebook' => null]))
                ->whereNull('notebook_id')->toBase()->getCountForPagination(),
        ];
    }

    /**
     * Day => count map used by the timeline density strip.
     */
    public function dailyCounts(int $userId, array $filters, int $days = 140): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = $this->build($userId, array_merge($filters, ['from' => null, 'to' => null]))
            ->where(function ($q) use ($from) {
                $q->where('occurred_at', '>=', $from)
                    ->orWhere(function ($q) use ($from) {
                        $q->whereNull('occurred_at')->where('date', '>=', $from->toDateString());
                    });
            })
            ->toBase()
            ->get([
                DB::raw('COALESCE(date(occurred_at), date(date)) as d'),
                DB::raw('count(*) as aggregate'),
            ]);

        $out = [];
        foreach ($rows as $row) {
            if ($row->d) {
                $out[(string) $row->d] = (int) $row->aggregate;
            }
        }

        return $out;
    }

    /**
     * Notebook tree annotated with the note count of each branch.
     */
    public function notebookTree(int $userId): \Illuminate\Support\Collection
    {
        return Notebook::treeFor($userId);
    }

    /**
     * Curated labels with their live counts, most used first.
     */
    public function labelsWithCounts(int $userId, array $filters): \Illuminate\Support\Collection
    {
        $counts = $this->facets($userId, $filters)['labels'];

        return NoteLabel::ofUser($userId)
            ->withCount('notes')
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->get()
            ->map(function ($label) use ($counts) {
                $label->filtered_count = (int) ($counts[$label->id] ?? 0);

                return $label;
            });
    }
}