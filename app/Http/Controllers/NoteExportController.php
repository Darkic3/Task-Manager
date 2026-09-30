<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\Notes\NoteQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class NoteExportController extends Controller
{
    public const MODES = ['full', 'summaries', 'decisions', 'timeline'];

    public function __construct(private NoteQueryService $queries) {}

    /**
     * JSON preview: size of the current selection before downloading.
     */
    public function preview(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        $filters = $this->queries->filtersFromRequest($request);
        $mode = $this->mode($request);

        $notes = $this->queries->build($userId, $filters)
            ->reorder()
            ->with(['labels'])
            ->limit(2000)
            ->get();

        if ($mode === 'decisions') {
            $notes = $notes->where('kind', Note::KIND_DECISION)->values();
        }

        $chars = $notes->sum(fn (Note $n) => mb_strlen((string) ($mode === 'summaries' ? ($n->summary ?: $n->excerpt) : $n->content)));
        $words = $notes->sum(fn (Note $n) => $n->word_count);
        $kinds = $notes->countBy('kind')->all();

        return response()->json([
            'total' => $notes->count(),
            'chars' => $chars,
            'words' => $words,
            'est_tokens' => (int) ceil($chars / 4),
            'kinds' => $kinds,
            'private' => $notes->where('visibility', Note::VISIBILITY_PRIVATE)->count(),
            'with_summary' => $notes->whereNotNull('summary')->count(),
            'mode' => $mode,
        ]);
    }

    /**
     * Stream the current filtered selection as a Markdown bundle (zip)
     * ready to paste into GPT: frontmatter + README + timeline + decisions.
     */
    public function markdown(Request $request): StreamedResponse
    {
        $userId = (int) Auth::id();
        $filters = $this->queries->filtersFromRequest($request);
        $mode = $this->mode($request);

        $notes = $this->queries->build($userId, $filters)
            ->reorder()
            ->with(['notebook', 'labels', 'links.linkable'])
            ->limit(2000)
            ->get();

        if ($mode === 'decisions') {
            $notes = $notes->where('kind', Note::KIND_DECISION)->values();
        }

        $filename = 'notes-'.$mode.'-'.now()->format('Ymd-His').'.zip';

        return response()->streamDownload(function () use ($notes, $filters, $mode) {
            $tmp = tempnam(sys_get_temp_dir(), 'notes');
            $zip = new ZipArchive;

            if ($zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
                return;
            }

            foreach ($notes as $note) {
                $zip->addFromString($this->fileNameFor($note), $this->markdownFor($note, $mode));
            }

            $zip->addFromString('README.md', $this->readmeFor($notes, $filters, $mode));
            $zip->addFromString('timeline.md', $this->timelineFor($notes));
            if ($mode !== 'decisions') {
                $zip->addFromString('decisions.md', $this->decisionsFor($notes));
            }
            $zip->addFromString('metadata.json', $this->metadataFor($notes, $filters, $mode));
            $zip->close();

            readfile($tmp);
            @unlink($tmp);
        }, $filename, ['Content-Type' => 'application/zip']);
    }

    private function mode(Request $request): string
    {
        $mode = (string) $request->query('mode', 'full');

        return in_array($mode, self::MODES, true) ? $mode : 'full';
    }

    private function fileNameFor(Note $note): string
    {
        $slug = Str::slug($note->title) ?: 'note';
        if ($slug === '') {
            $slug = 'note';
        }
        $slug = mb_substr($slug, 0, 60);

        $prefix = $note->notebook
            ? (Str::slug($note->notebook->title) ?: 'notebook')
            : (Str::slug(note_kind_label($note->kind)) ?: $note->kind);

        $date = $note->effectiveDate()?->format('Y-m-d') ?? 'undated';

        return $prefix.'/'.$date.'-'.$note->id.'-'.$slug.'.md';
    }

    private function frontmatterFor(Note $note): string
    {
        $lines = ['---'];
        $lines[] = 'title: "'.$this->yamlEscape($note->title).'"';
        $lines[] = 'id: '.$note->id;
        $lines[] = 'type: '.$note->kind;
        $lines[] = 'date: '.($note->effectiveDate()?->toDateString() ?? '');
        if ($note->occurred_at) {
            $lines[] = 'occurred: '.$note->occurred_at->format('Y-m-d H:i');
        }
        $lines[] = 'created: '.($note->created_at?->format('Y-m-d H:i') ?? '');
        if ($note->notebook) {
            $lines[] = 'notebook: "'.$this->yamlEscape($note->notebook->pathTitle()).'"';
        }
        if ($note->all_tags) {
            $tags = implode(', ', array_map(fn ($t) => '"'.$this->yamlEscape($t).'"', $note->all_tags));
            $lines[] = 'tags: ['.$tags.']';
        }
        if ($note->mood) {
            $lines[] = 'mood: '.$note->mood;
        }
        if ($note->energy) {
            $lines[] = 'energy: '.$note->energy;
        }
        $lines[] = 'visibility: '.($note->visibility ?? 'private');
        $lines[] = '---';

        return implode("\n", $lines);
    }

    private function markdownFor(Note $note, string $mode): string
    {
        $lines = [$this->frontmatterFor($note), '', '# '.$note->title, ''];

        $body = match ($mode) {
            'summaries' => $note->summary ?: $note->excerpt,
            'timeline' => $note->summary ?: $note->excerpt,
            default => (string) $note->content,
        };

        $lines[] = $body;
        $lines[] = '';

        if ($mode === 'full' && $note->summary) {
            $lines = array_merge($lines, ['> '.$note->summary, '']);
        }

        $linked = $note->relationLoaded('links') ? $note->links : $note->links()->with('linkable')->get();
        $linked = $linked->filter(fn ($l) => $l->linkable)->map(fn ($l) => '- '.$l->label)->values();

        if ($linked->isNotEmpty()) {
            $lines = array_merge($lines, ['', '## '.__('Linked'), ''], $linked->toArray(), ['']);
        }

        return implode("\n", $lines);
    }

    private function readmeFor($notes, array $filters, string $mode): string
    {
        $lines = ['# '.__('Notes export'), ''];
        $lines[] = '- generated: '.now()->toDateTimeString();
        $lines[] = '- mode: '.$mode.' (full | summaries | decisions | timeline)';
        $lines[] = '- count: '.$notes->count();
        $lines[] = '- kinds: '.$notes->countBy('kind')->map(fn ($c, $k) => $k.'='.$c)->implode(', ');
        if (! empty($filters['search'])) {
            $lines[] = '- search: '.$filters['search'];
        }
        if (! empty($filters['kind'])) {
            $lines[] = '- kinds filter: '.implode(', ', (array) $filters['kind']);
        }
        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $lines[] = '- range: '.($filters['from'] ?? '…').' → '.($filters['to'] ?? '…');
        }
        $lines[] = '';
        $lines[] = '## How to use with GPT';
        $lines[] = '';
        $lines[] = '1. Upload the whole zip or paste `timeline.md` first for context.';
        $lines[] = '2. Then paste the `notes/` files for the topic you want analysed.';
        $lines[] = '3. Ask for: summary, decisions, open actions, contradictions, next plan.';
        $lines[] = '';
        $lines[] = '## Files';
        $lines[] = '';
        foreach ($notes->sortBy(fn ($n) => $n->effectiveDate()?->timestamp ?? 0) as $note) {
            $lines[] = '- '.$note->effectiveDate()?->toDateString().' — **'.$note->title.'** ('.$note->kind.')';
        }

        return implode("\n", $lines)."\n";
    }

    private function timelineFor($notes): string
    {
        $lines = ['# Timeline', ''];
        foreach ($notes->sortBy(fn ($n) => $n->effectiveDate()?->timestamp ?? 0)->values() as $note) {
            $date = $note->effectiveDate()?->toDateString() ?? 'undated';
            $lines[] = '## '.$date.' — '.$note->title;
            $lines[] = '';
            $lines[] = '- type: '.$note->kind;
            $lines[] = '- summary: '.($note->summary ?: $note->excerpt);
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    private function decisionsFor($notes): string
    {
        $decisions = $notes->where('kind', Note::KIND_DECISION)->values();
        $lines = ['# Decisions & open actions', '', '- total decisions in this export: '.$decisions->count(), ''];

        foreach ($decisions as $note) {
            $lines[] = '## '.$note->title.' ('.($note->effectiveDate()?->toDateString() ?? '').')';
            $lines[] = '';
            $lines[] = (string) $note->content;
            $lines[] = '';
        }

        if ($decisions->isEmpty()) {
            $lines[] = '_No decision notes in this selection._';
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    private function metadataFor($notes, array $filters, string $mode): string
    {
        return json_encode([
            'generated_at' => now()->toIso8601String(),
            'mode' => $mode,
            'count' => $notes->count(),
            'filters' => $filters,
            'kinds' => $notes->countBy('kind')->all(),
            'notes' => $notes->map(fn (Note $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'kind' => $n->kind,
                'date' => $n->effectiveDate()?->toDateString(),
                'notebook' => $n->notebook?->title,
                'tags' => $n->all_tags,
            ])->values()->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function yamlEscape(string $value): string
    {
        return str_replace('"', "'", trim($value));
    }
}
