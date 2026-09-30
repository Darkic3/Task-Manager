<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Notebook;
use App\Services\Notes\NoteQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class NoteExportController extends Controller
{
    public function __construct(private NoteQueryService $queries) {}

    /**
     * Stream the current filtered selection as a Markdown bundle (zip).
     */
    public function markdown(Request $request): StreamedResponse
    {
        $userId = (int) Auth::id();
        $filters = $this->queries->filtersFromRequest($request);

        $notes = $this->queries->build($userId, $filters)
            ->with(['notebook', 'labels'])
            ->limit(2000)
            ->get();

        $filename = 'notes-'.now()->format('Ymd-His').'.zip';

        return response()->streamDownload(function () use ($notes, $filename) {
            $tmp = tempnam(sys_get_temp_dir(), 'notes');
            $zip = new ZipArchive;

            if ($zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
                return;
            }

            foreach ($notes as $note) {
                $zip->addFromString($this->fileNameFor($note), $this->markdownFor($note));
            }

            $zip->addFromString('_index.md', $this->indexFor($notes));
            $zip->close();

            readfile($tmp);
            @unlink($tmp);
        }, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    private function fileNameFor(Note $note): string
    {
        $slug = Str::slug($note->title) ?: 'note';
        $slug = mb_substr($slug, 0, 60);

        $prefix = $note->notebook
            ? Str::slug($note->notebook->title).'/'
            : Str::slug(note_kind_label($note->kind)).'/';

        return $prefix.$note->id.'-'.$slug.'.md';
    }

    private function markdownFor(Note $note): string
    {
        $lines = ['# '.$note->title, ''];

        $meta = [];
        $meta[] = '- type: '.note_kind_label($note->kind);
        if ($note->notebook) {
            $meta[] = '- notebook: '.$note->notebook->pathTitle();
        }
        if ($note->occurred_at) {
            $meta[] = '- occurred: '.app_date($note->occurred_at, 'Y-m-d H:i');
        }
        $meta[] = '- created: '.app_date($note->created_at, 'Y-m-d H:i');

        if ($allTags = $note->all_tags) {
            $meta[] = '- tags: '.implode(', ', $allTags);
        }

        $lines = array_merge($lines, $meta, ['', (string) $note->content, '']);

        $linked = $note->links()->with('linkable')->get()
            ->filter(fn ($l) => $l->linkable)
            ->map(fn ($l) => '- '.$l->label)
            ->values();

        if ($linked->isNotEmpty()) {
            $lines = array_merge($lines, ['', '## '.__('Linked'), ''], $linked->toArray(), ['']);
        }

        return implode("\n", $lines);
    }

    private function indexFor($notes): string
    {
        $lines = ['# '.__('Notes export'), '', '- generated: '.now()->toDateTimeString(), '- count: '.$notes->count(), ''];

        foreach ($notes as $note) {
            $lines[] = '- '.$note->effectiveDate()?->toDateString().' — **'.$note->title.'** ('.note_kind_label($note->kind).')';
        }

        return implode("\n", $lines)."\n";
    }
}