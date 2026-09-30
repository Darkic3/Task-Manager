<?php

namespace App\Http\Controllers;

use App\Models\NoteShare;
use App\Services\Notes\NoteQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NoteCollectionController extends Controller
{
    public function __construct(private NoteQueryService $queries) {}

    /**
     * Save the current filtered selection as a reusable collection.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = (int) Auth::id();
        $filters = $this->queries->filtersFromRequest($request);

        $notesQuery = $this->queries->build($userId, $filters)->reorder();
        $noteIds = (clone $notesQuery)->limit(2000)->pluck('notes.id')->all();
        $charCount = 0;
        if ($noteIds !== []) {
            $charCount = (int) \App\Models\Note::ofUser($userId)->whereIn('id', $noteIds)
                ->selectRaw('COALESCE(SUM(CHAR_LENGTH(content)),0) as c')->value('c');
        }

        $share = NoteShare::create([
            'user_id' => $userId,
            'ai_conversation_id' => null,
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name')) ?: 'collection-'.time(),
            'description' => $request->input('description'),
            'filters' => $filters,
            'note_count' => count($noteIds),
            'char_count' => $charCount,
        ]);

        // Make slug unique per user.
        if (NoteShare::ofUser($userId)->where('slug', $share->slug)->where('id', '!=', $share->id)->exists()) {
            $share->update(['slug' => $share->slug.'-'.$share->id]);
        }

        $share->notes()->sync($noteIds);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $share->id, 'name' => $share->name]);
        }

        return redirect()->route('notes.index', $this->filtersToQuery($filters))
            ->with('success', __('Collection saved.'));
    }

    /**
     * Apply a saved collection → redirect to the notes list with its filters.
     */
    public function apply(NoteShare $collection)
    {
        abort_unless((int) $collection->user_id === (int) Auth::id(), 403);

        return redirect()->route('notes.index', $this->filtersToQuery((array) $collection->filters));
    }

    public function destroy(NoteShare $collection)
    {
        abort_unless((int) $collection->user_id === (int) Auth::id(), 403);

        $collection->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('notes.index')->with('success', __('Collection deleted.'));
    }

    private function filtersToQuery(array $filters): array
    {
        $out = [];
        foreach (['search', 'kind', 'notebook', 'label', 'favorite', 'pinned', 'archived', 'from', 'to', 'linked_type', 'linked_id', 'sort', 'view'] as $key) {
            $value = $filters[$key] ?? null;
            if ($value === null || $value === [] || $value === false || $value === '') {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
