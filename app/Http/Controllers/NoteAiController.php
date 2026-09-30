<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Note;
use App\Models\NoteLink;
use App\Models\NoteShare;
use App\Models\NoteSubject;
use App\Models\Task;
use App\Services\Notes\NoteAiService;
use App\Services\Notes\NoteQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoteAiController extends Controller
{
    public function __construct(
        private NoteAiService $ai,
        private NoteQueryService $queries,
    ) {}

    /**
     * Send a saved collection (or the current filter) to the internal AI:
     * creates a conversation seeded with the notes + the analysis reply.
     */
    public function sendCollection(Request $request)
    {
        $request->validate([
            'collection_id' => ['nullable', 'integer', 'exists:note_shares,id'],
            'mode' => ['nullable', 'string'],
        ]);

        $user = Auth::user();
        $mode = in_array($request->input('mode'), NoteAiService::MODES, true)
            ? $request->input('mode') : 'summary';

        if ($request->filled('collection_id')) {
            $collection = NoteShare::ofUser((int) $user->id)->findOrFail($request->input('collection_id'));
            $filters = (array) $collection->filters;
            $label = $collection->name;
        } else {
            $collection = null;
            $filters = $this->queries->filtersFromRequest($request);
            $label = __('Notes analysis').' — '.$mode;
        }

        $notes = $this->queries->build((int) $user->id, $filters)
            ->reorder()
            ->with(['notebook', 'labels'])
            ->limit(200)
            ->get();

        if ($notes->isEmpty()) {
            return back()->withErrors(['collection' => __('No notes match this selection.')]);
        }

        if (! $this->ai->isConfigured($user)) {
            return redirect()->route('ai.settings')
                ->withErrors(['ai' => __('No AI provider is configured. Add an API key first.')]);
        }

        $context = $this->ai->contextFor($notes);
        $userText = $this->promptForMode($mode, $label)."\n\n".$context;

        try {
            $reply = $this->ai->analyzeCollection($user, $notes, $mode);
        } catch (\Exception $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'label' => mb_substr($label.' · '.$mode, 0, 120),
        ]);

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userText,
            'model' => $this->ai->resolvedLabel($user),
        ]);
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply,
            'model' => $this->ai->resolvedLabel($user),
        ]);

        if ($collection) {
            $collection->update(['ai_conversation_id' => $conversation->id]);
        }

        return redirect()->route('ai.index', ['conversation' => $conversation->id])
            ->with('success', __('Analysis is ready in the AI chat.'));
    }

    /**
     * Preview extraction for one note (AJAX).
     */
    public function extractPreview(Note $note)
    {
        $this->authorize('view', $note);

        return response()->json(
            $this->ai->extractFromNote(Auth::user(), $note)
        );
    }

    /**
     * Apply extraction: create tasks, link them, optionally save summary.
     */
    public function extractApply(Request $request, Note $note)
    {
        $this->authorize('update', $note);

        $request->validate([
            'tasks' => ['nullable', 'array', 'max:20'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.due_date' => ['nullable', 'date'],
            'tasks.*.priority' => ['nullable', 'in:low,medium,high'],
            'save_summary' => ['nullable', 'boolean'],
            'summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $created = [];
        foreach ((array) $request->input('tasks', []) as $t) {
            $task = Task::create([
                'user_id' => (int) Auth::id(),
                'title' => $t['title'],
                'description' => __('From note:').' '.$note->title,
                'due_date' => $t['due_date'] ?? null,
                'priority' => $t['priority'] ?? 'medium',
                'status' => 'pending',
            ]);
            NoteLink::create([
                'note_id' => $note->id,
                'linkable_type' => Task::class,
                'linkable_id' => $task->id,
                'label' => $task->title,
            ]);
            $created[] = $task->id;
        }

        if ($request->boolean('save_summary') && $request->filled('summary')) {
            $note->update(['summary' => $request->input('summary')]);
        }

        return response()->json(['success' => true, 'created' => $created]);
    }

    /**
     * (Re)generate the living summary of a subject from its linked notes.
     */
    public function summarizeSubject(Request $request, NoteSubject $subject)
    {
        abort_unless((int) $subject->user_id === (int) Auth::id(), 403);

        $user = Auth::user();

        if (! $this->ai->isConfigured($user)) {
            return response()->json([
                'success' => false,
                'message' => __('No AI provider is configured.'),
            ], 422);
        }

        $noteIds = NoteLink::where('linkable_type', NoteSubject::class)
            ->where('linkable_id', $subject->id)
            ->pluck('note_id');

        $notes = Note::ofUser((int) $user->id)
            ->whereIn('id', $noteIds)
            ->chronological('asc')
            ->limit(200)
            ->get();

        if ($notes->isEmpty()) {
            return response()->json(['success' => false, 'message' => __('No notes linked to this subject yet.')], 422);
        }

        try {
            $summary = $this->ai->summarizeSubject($user, $subject, $notes);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $meta = $subject->meta ?? [];
        $meta['ai_summary'] = $summary;
        $meta['ai_summary_at'] = now()->toDateTimeString();
        $meta['ai_summary_note_count'] = $notes->count();
        $subject->update(['meta' => $meta]);

        return response()->json(['success' => true, 'summary' => $summary, 'note_count' => $notes->count()]);
    }

    private function promptForMode(string $mode, string $label): string
    {
        return match ($mode) {
            'decisions' => "Analyse the following notes about \"{$label}\" and list decisions + open questions.",
            'tasks' => "Extract every actionable task from the following notes about \"{$label}\".",
            'timeline' => "Reconstruct a chronological timeline from the following notes about \"{$label}\".",
            'report' => "Write a structured report from the following notes about \"{$label}\".",
            default => "Summarise the following notes about \"{$label}\".",
        };
    }
}
