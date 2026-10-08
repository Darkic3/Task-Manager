<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Notebook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotebookController extends Controller
{
    public function index()
    {
        return view('notes.notebooks.index', [
            'notebooks' => Notebook::treeFor((int) Auth::id()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = (int) Auth::id();

        $notebook = Notebook::create($data);

        return response()->json([
            'success' => true,
            'notebook' => $this->payload($notebook),
        ], 201);
    }

    public function update(Request $request, Notebook $notebook): JsonResponse
    {
        $this->authorize('update', $notebook);

        $notebook->update($this->validated($request, true));

        return response()->json([
            'success' => true,
            'notebook' => $this->payload($notebook->refresh()),
        ]);
    }

    public function destroy(Notebook $notebook): JsonResponse
    {
        $this->authorize('delete', $notebook);

        $userId = (int) $notebook->user_id;
        $children = $notebook->descendantIds();

        // Notes and nested notebooks are released, never destroyed.
        Note::ofUser($userId)->whereIn('notebook_id', $children)->update(['notebook_id' => null]);
        Notebook::ofUser($userId)->whereIn('id', array_slice($children, 1))->update(['parent_id' => null]);

        $notebook->delete();

        return response()->json([
            'success' => true,
            'tree' => view('notes.partials._notebook_tree', [
                'notebooks' => Notebook::treeFor($userId),
                'filters' => ['notebook' => null],
            ])->render(),
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'max:500'],
            'order.*' => ['integer', 'distinct'],
        ]);

        $userId = (int) Auth::id();
        $ids = array_map('intval', $data['order']);

        $owned = Notebook::ofUser($userId)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach (array_values($owned) as $index => $id) {
            Notebook::ofUser($userId)->whereKey($id)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => ($partial ? 'sometimes' : 'required').'|string|max:120',
            'parent_id' => 'nullable|integer',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:60',
            'description' => 'nullable|string|max:1000',
            'is_archived' => 'nullable|boolean',
        ];

        $data = $request->validate($rules);
        $userId = (int) $request->user()->id;

        if (array_key_exists('parent_id', $data) && $data['parent_id']) {
            $parentId = (int) $data['parent_id'];
            $parent = Notebook::ofUser($userId)->whereKey($parentId)->first();

            if (! $parent) {
                unset($data['parent_id']);
            }
        }

        // Never let a notebook become its own parent.
        if ($partial && $request->has('parent_id') && $request->integer('parent_id') === (int) $request->route('notebook')?->id) {
            $data['parent_id'] = null;
        }

        if (array_key_exists('is_archived', $data)) {
            $data['is_archived'] = $request->boolean('is_archived');
        }

        return $data;
    }

    private function payload(Notebook $notebook): array
    {
        return [
            'id' => $notebook->id,
            'title' => $notebook->title,
            'parent_id' => $notebook->parent_id,
            'color' => $notebook->color,
            'icon' => $notebook->icon,
            'path' => $notebook->pathTitle(),
            'notes_count' => $notebook->notes()->count(),
        ];
    }
}