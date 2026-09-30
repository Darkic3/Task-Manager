<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickCaptureRequest;
use App\Models\Note;
use App\Models\Notebook;
use App\Services\Notes\NoteLinkService;
use App\Services\Notes\NoteMentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoteQuickCaptureController extends Controller
{
    public function __construct(
        private NoteMentionService $mentions,
        private NoteLinkService $links,
    ) {}

    /**
     * Autocomplete for the quick-capture composer (@subjects, #labels,
     * projects and tasks).
     */
    public function mentions(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');

        return response()->json([
            'results' => $this->mentions->suggestions((int) Auth::id(), $q),
        ]);
    }

    public function store(QuickCaptureRequest $request): JsonResponse
    {
        $userId = (int) Auth::id();
        $body = trim((string) $request->input('body'));

        $notebookId = $request->input('notebook_id');
        if ($notebookId && ! Notebook::ofUser($userId)->whereKey((int) $notebookId)->exists()) {
            $notebookId = null;
        }

        $kind = $request->input('kind') ?: $this->mentions->guessKind($body);

        // Use the first sentence as the title, the rest as the body.
        [$title, $remainder] = $this->splitTitle($body);

        $note = Auth::user()->notes()->create([
            'title' => $title,
            'content' => $remainder,
            'kind' => $kind,
            'notebook_id' => $notebookId,
            'occurred_at' => $request->input('occurred_at') ?: now(),
            'status' => Note::STATUS_ACTIVE,
            'visibility' => Note::VISIBILITY_PRIVATE,
        ]);

        $this->links->syncFromText($note, $body, (array) $request->input('mentions', []));

        if ($request->boolean('redirect')) {
            return redirect()->route('notes.show', $note)->with('success', __('Note captured.'));
        }

        return response()->json([
            'success' => true,
            'note' => [
                'id' => $note->id,
                'title' => $note->title,
                'kind' => $note->kind,
                'kind_label' => note_kind_label($note->kind),
                'url' => route('notes.show', $note),
                'excerpt' => $note->excerpt,
                'occurred_at' => $note->occurred_at?->toIso8601String(),
                'labels' => $note->labels()->get(['note_labels.id', 'note_labels.name', 'note_labels.color'])->toArray(),
            ],
        ]);
    }

    /**
     * Split a quick capture into a short title plus the remaining body.
     *
     * @return array{0: string, 1: string}
     */
    private function splitTitle(string $body): array
    {
        $body = trim($body);

        // Prefer the first line, then the first sentence.
        $firstLine = trim(preg_split('/\R/u', $body)[0] ?? $body);
        $remainder = trim(mb_substr($body, mb_strlen($firstLine)));

        $titleSource = $firstLine !== '' ? $firstLine : $body;

        if (mb_strlen($titleSource) > 120) {
            $cut = mb_substr($titleSource, 0, 120);
            $space = mb_strrpos($cut, ' ');
            $title = $space ? mb_substr($cut, 0, $space) : $cut;
        } else {
            $title = $titleSource;
        }

        $title = trim(preg_replace('/[@#]/u', '', $title) ?? $title);
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);

        if ($title === '') {
            $title = mb_substr(strip_tags($body), 0, 80) ?: __('Untitled note');
        }

        if (mb_strlen($title) > 120) {
            $title = mb_substr($title, 0, 117).'...';
        }

        $content = $remainder !== ''
            ? $firstLine."\n\n".$remainder
            : $body;

        return [$title, trim($content) === '' ? $title : $content];
    }
}