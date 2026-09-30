<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Models\Note;
use App\Models\NoteLabel;
use App\Models\NoteRevision;
use App\Models\NoteSubject;
use App\Models\Notebook;
use App\Services\Notes\NoteLinkService;
use App\Services\Notes\NoteQueryService;
use App\Services\Notes\NoteRevisionService;
use App\Services\Notes\NoteTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    public function __construct(
        private NoteQueryService $queries,
        private NoteLinkService $links,
        private NoteRevisionService $revisions,
    ) {}

    public function index(Request $request)
    {
        $userId = (int) Auth::id();
        $filters = $this->queries->filtersFromRequest($request);
        $view = $filters['view'];

        $notes = $this->queries->build($userId, $filters)
            ->with(['notebook:id,title,color,icon', 'labels:id,name,slug,color'])
            ->paginate($view === 'timeline' ? 90 : 30)
            ->withQueryString();

        if ($request->boolean('partial')) {
            return response()->json($this->partialPayload($notes, $filters, $userId));
        }

        $facets = $this->queries->facets($userId, $filters);

        return view('notes.index', [
            'notes' => $notes,
            'filters' => $filters,
            'facets' => $facets,
            'notebooks' => Notebook::treeFor($userId),
            'labels' => $this->queries->labelsWithCounts($userId, $filters),
            'dailyCounts' => $this->queries->dailyCounts($userId, $filters),
            'kindMeta' => note_kind_meta(),
            'backlinkTarget' => $this->backlinkTarget($request),
            'collections' => \App\Models\NoteShare::ofUser($userId)->whereNull('ai_conversation_id')->latest()->limit(50)->get(),
        ]);
    }

    private function partialPayload($notes, array $filters, int $userId): array
    {
        return [
            'html' => view('notes.partials._results', [
                'notes' => $notes,
                'filters' => $filters,
                'kindMeta' => note_kind_meta(),
            ])->render(),
            'sidebar' => view('notes.partials._sidebar', [
                'notebooks' => Notebook::treeFor($userId),
                'labels' => $this->queries->labelsWithCounts($userId, $filters),
                'facets' => $this->queries->facets($userId, $filters),
                'filters' => $filters,
                'kindMeta' => note_kind_meta(),
                'collections' => \App\Models\NoteShare::ofUser($userId)->whereNull('ai_conversation_id')->latest()->limit(50)->get(),
            ])->render(),
            'total' => $notes->total(),
        ];
    }

    private function backlinkTarget(Request $request): ?array
    {
        $type = $request->query('linked_type');
        $id = (int) $request->query('linked_id');

        if (! $type || $id <= 0) {
            return null;
        }

        return ['type' => $type, 'id' => $id, 'label' => (string) $request->query('linked_label', '')];
    }

    public function create(Request $request)
    {
        $userId = (int) Auth::id();

        $kind = in_array($request->query('kind'), Note::KINDS, true) ? $request->query('kind') : Note::KIND_GENERAL;

        return view('notes.create', [
            'note' => new Note([
                'kind' => $kind,
                'occurred_at' => now(),
                'content' => NoteTemplateService::bodyFor($kind),
            ]),
            'notebooks' => Notebook::treeFor($userId),
            'labels' => NoteLabel::ofUser($userId)->orderBy('name')->get(),
            'selectedLabels' => [],
            'selectedFiles' => [],
            'kindMeta' => note_kind_meta(),
            'templates' => NoteTemplateService::all(),
        ]);
    }

    public function store(NoteRequest $request)
    {
        $note = Auth::user()->notes()->create($request->noteAttributes(true));

        $tags = $request->legacyTags();
        if ($tags !== null) {
            $note->tags = $tags;
            $note->save();
        }

        $this->links->syncFromText($note, (string) $note->content, $request->mentions());
        $this->syncLabels($note, $request->labelIds());
        $this->syncFiles($note, $request->fileIds());

        return redirect()
            ->route('notes.show', $note)
            ->with('success', __('Note created successfully.'));
    }

    public function show(Request $request, Note $note)
    {
        $this->authorize('view', $note);

        $note->load(['notebook', 'labels', 'links.linkable', 'attachments.file', 'parentNote:id,title']);

        return view('notes.show', [
            'note' => $note,
            'linkGroups' => $this->links->grouped($note),
            'backlinks' => $this->links->noteBacklinks($note),
            'subjectBacklinks' => $note->links
                ->where('linkable_type', NoteSubject::class)
                ->get()
                ->flatMap(fn ($l) => $this->links->backlinks($note, NoteSubject::class, $l->linkable_id))
                ->unique('id')
                ->values(),
            'revisions' => $note->revisions()->latest()->limit(20)->get(),
            'notebooks' => Notebook::treeFor((int) Auth::id()),
        ]);
    }

    public function edit(Note $note)
    {
        $this->authorize('update', $note);

        $userId = (int) Auth::id();
        $note->load(['labels', 'links.linkable', 'attachments.file']);

        return view('notes.edit', [
            'note' => $note,
            'notebooks' => Notebook::treeFor($userId),
            'labels' => NoteLabel::ofUser($userId)->orderBy('name')->get(),
            'templates' => NoteTemplateService::all(),
            'selectedLabels' => $note->labels->pluck('id')->all(),
            'selectedFiles' => $note->attachments->pluck('file_id')->all(),
            'mentionPicks' => $note->links
                ->filter(fn ($l) => $l->linkable_type !== NoteSubject::class)
                ->map(fn ($l) => ['type' => strtolower(class_basename($l->linkable_type)), 'id' => $l->linkable_id, 'name' => $l->label])
                ->values()
                ->all(),
            'kindMeta' => note_kind_meta(),
        ]);
    }

    public function update(NoteRequest $request, Note $note)
    {
        $this->authorize('update', $note);

        $changed = $this->revisions->changedFields($note, $request->noteAttributes(false));

        if ($changed !== []) {
            $this->revisions->snapshot($note, NoteRevision::SOURCE_USER, null, $changed);
        }

        $note->fill($request->noteAttributes(false));

        $tags = $request->legacyTags();
        if ($tags !== null) {
            $note->tags = $tags;
        }

        $note->save();

        $this->links->syncFromText($note, (string) $note->content, $request->mentions());
        $this->syncLabels($note, $request->labelIds());
        $this->syncFiles($note, $request->fileIds());

        return redirect()
            ->route('notes.show', $note)
            ->with('success', __('Note updated successfully.'));
    }

    public function destroy(Note $note)
    {
        $this->authorize('delete', $note);

        $note->delete();

        return redirect()->route('notes.index')->with('success', __('Note deleted successfully.'));
    }

    public function toggleFavorite(Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $note->update(['is_favorite' => ! $note->is_favorite]);

        return response()->json(['success' => true, 'is_favorite' => $note->is_favorite]);
    }

    public function togglePin(Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $note->update(['is_pinned' => ! $note->is_pinned]);

        return response()->json(['success' => true, 'is_pinned' => $note->is_pinned]);
    }

    public function toggleArchive(Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $note->update([
            'status' => $note->status === Note::STATUS_ARCHIVED ? Note::STATUS_ACTIVE : Note::STATUS_ARCHIVED,
        ]);

        return response()->json(['success' => true, 'status' => $note->status]);
    }

    public function duplicate(Note $note)
    {
        $this->authorize('view', $note);

        $copy = $note->replicate(['id', 'created_at', 'updated_at']);
        $copy->title = $note->title.' ('.__('Copy').')';
        $copy->is_pinned = false;
        $copy->is_favorite = false;
        $copy->save();

        $copy->labels()->sync($note->labels->pluck('id')->all());

        return redirect()->route('notes.show', $copy)->with('success', __('Note duplicated successfully.'));
    }

    public function revisions(Note $note)
    {
        $this->authorize('view', $note);

        return response()->json([
            'html' => view('notes.partials._revisions', [
                'note' => $note,
                'revisions' => $note->revisions()->latest()->limit(50)->get(),
            ])->render(),
        ]);
    }

    public function restoreRevision(Note $note, NoteRevision $revision): JsonResponse
    {
        $this->authorize('update', $note);

        if ((int) $revision->note_id !== (int) $note->id) {
            return response()->json(['success' => false, 'message' => __('Invalid revision.')], 422);
        }

        $this->revisions->restore($note, $revision);

        return response()->json(['success' => true, 'message' => __('Note restored.')]);
    }

    private function syncLabels(Note $note, array $labelIds): void
    {
        // Only replace the set when the form actually submitted it, otherwise a
        // partial request would silently wipe existing labels.
        if ($note->exists && ! request()->has('label_ids')) {
            return;
        }

        $note->labels()->sync($labelIds);
    }

    private function syncFiles(Note $note, array $fileIds): void
    {
        if ($note->exists && ! request()->has('file_ids')) {
            return;
        }

        $note->attachments()->sync($fileIds);
    }
}