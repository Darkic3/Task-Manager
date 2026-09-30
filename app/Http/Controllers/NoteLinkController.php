<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteLink;
use App\Models\NoteSubject;
use App\Services\Notes\NoteLinkService;
use App\Services\Notes\NoteMentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteLinkController extends Controller
{
    public function __construct(private NoteLinkService $links) {}

    public function store(Request $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $data = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $class = array_search($data['type'], array_flip([
            'subject' => NoteSubject::class,
            'note' => Note::class,
            'project' => \App\Models\Project::class,
            'task' => \App\Models\Task::class,
        ]), true);

        if (! $class) {
            return response()->json(['success' => false, 'message' => __('Unsupported link type.')], 422);
        }

        $link = $this->links->attach($note, $class, (int) $data['id']);

        if (! $link) {
            return response()->json(['success' => false, 'message' => __('Item not found or not yours.')], 422);
        }

        return response()->json([
            'success' => true,
            'link' => [
                'id' => $link->id,
                'type' => $data['type'],
                'type_label' => class_basename($class),
                'target_id' => $link->linkable_id,
                'name' => $link->label,
            ],
            'html' => view('notes.partials._linked', [
                'note' => $note->refresh(),
                'linkGroups' => $this->links->grouped($note),
            ])->render(),
        ]);
    }

    public function destroy(Note $note, NoteLink $link): JsonResponse
    {
        $this->authorize('update', $note);

        if ((int) $link->note_id !== (int) $note->id) {
            return response()->json(['success' => false], 404);
        }

        $this->links->detach($note, (int) $link->id);

        return response()->json([
            'success' => true,
            'html' => view('notes.partials._linked', [
                'note' => $note->refresh(),
                'linkGroups' => $this->links->grouped($note),
            ])->render(),
        ]);
    }

    /**
     * Everything linking TO this entity — used by the backlinks panel.
     */
    public function backlinks(Request $request, string $type): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $class = array_search($type, [
            'subject' => NoteSubject::class,
            'note' => Note::class,
            'project' => \App\Models\Project::class,
            'task' => \App\Models\Task::class,
        ], true);

        if (! $class) {
            return response()->json(['success' => false], 422);
        }

        $model = $class::find($data['id']);

        if (! $model || $model->user_id !== $request->user()->id) {
            return response()->json(['success' => false], 404);
        }

        $nameColumn = NoteMentionService::LINKABLE[$class] ?? 'name';

        $notes = Note::with(['notebook:id,title,color,icon'])
            ->whereHas('links', fn ($q) => $q->where('linkable_type', $class)->where('linkable_id', $model->id))
            ->ofUser($request->user()->id)
            ->notArchived()
            ->chronological('desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'html' => view('notes.partials._backlinks', [
                'notes' => $notes,
                'heading' => __('Notes mentioning :name', ['name' => $model->{$nameColumn}]),
            ])->render(),
        ]);
    }
}