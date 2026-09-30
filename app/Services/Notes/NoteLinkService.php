<?php

namespace App\Services\Notes;

use App\Models\Note;
use App\Models\NoteLabel;
use App\Models\NoteLink;
use App\Models\NoteSubject;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

class NoteLinkService
{
    public function __construct(private NoteMentionService $mentions) {}

    /**
     * Attach a polymorphic link, verifying the target is owned by the user.
     * Returns null when the target does not exist or is not theirs.
     */
    public function attach(Note $note, string $type, int $id, ?string $label = null): ?NoteLink
    {
        if (! isset(NoteMentionService::LINKABLE[$type])) {
            return null;
        }

        $model = $type::find($id);

        if (! $model || (int) $model->user_id !== (int) $note->user_id) {
            return null;
        }

        if ($type === Note::class && (int) $model->id === (int) $note->id) {
            return null; // no self links
        }

        $nameColumn = NoteMentionService::LINKABLE[$type];

        return NoteLink::updateOrCreate(
            [
                'note_id' => $note->id,
                'linkable_type' => $type,
                'linkable_id' => $id,
            ],
            ['label' => $label ?: (string) $model->{$nameColumn}]
        );
    }

    public function detach(Note $note, int $linkId): void
    {
        $note->links()->whereKey($linkId)->delete();
    }

    /**
     * Re-sync the @subject / #label facets of a note from its body text plus
     * any explicit picks made in the composer. Non-subject links (project,
     * task, note-to-note) are preserved.
     *
     * @param  array<int, array{type: string, id: int}>  $picks
     * @return array{subjects: Collection, labels: Collection, links: int}
     */
    public function syncFromText(Note $note, string $text, array $picks = []): array
    {
        $userId = (int) $note->user_id;
        $referenced = $this->mentions->referencedIn($userId, $text);

        $subjectIds = [];
        $labelIds = [];

        foreach ($referenced as $ref) {
            if ($ref['char'] === '@') {
                $subject = $this->mentions->findOrCreateSubject($userId, $ref['name']);
                $subjectIds[] = $subject->id;
                $this->attach($note, NoteSubject::class, $subject->id);
            } else {
                $label = $this->mentions->findOrCreateLabel($userId, $ref['name']);
                $labelIds[] = $label->id;
            }
        }

        // Explicit picks from the composer (typed project/task/note, or an
        // existing subject/label chosen from the autocomplete).
        $map = [
            'project' => Project::class,
            'task' => Task::class,
            'note' => Note::class,
            'subject' => NoteSubject::class,
            'label' => NoteLabel::class,
        ];

        foreach ($picks as $pick) {
            $type = $map[$pick['type'] ?? ''] ?? null;
            $id = (int) ($pick['id'] ?? 0);

            if (! $type || $id <= 0) {
                continue;
            }

            if ($type === NoteLabel::class) {
                $labelIds[] = $id;

                continue;
            }

            if ($type === NoteSubject::class) {
                $subjectIds[] = $id;
            }

            $this->attach($note, $type, $id);
        }

        // Drop subject links that the body no longer mentions, so renaming or
        // deleting a token detaches it.
        $note->links()
            ->ofType(NoteSubject::class)
            ->when($subjectIds !== [], fn ($q) => $q->whereNotIn('linkable_id', $subjectIds))
            ->delete();

        $note->labels()->sync(array_values(array_unique($labelIds)));

        foreach (array_unique($subjectIds) as $id) {
            NoteSubject::whereKey($id)->increment('usage_count');
        }
        foreach (array_unique($labelIds) as $id) {
            NoteLabel::whereKey($id)->increment('usage_count');
        }

        return [
            'subjects' => $subjectIds === [] ? collect() : NoteSubject::whereIn('id', array_unique($subjectIds))->get(),
            'labels' => $labelIds === [] ? collect() : NoteLabel::whereIn('id', array_unique($labelIds))->get(),
            'links' => $note->links()->count(),
        ];
    }

    /**
     * Notes that link TO a given entity (the backlinks panel).
     */
    public function backlinks(Note $note, string $type, int $id): Collection
    {
        return Note::with(['labels', 'notebook:id,title,color,icon'])
            ->whereHas('links', fn ($q) => $q->where('linkable_type', $type)->where('linkable_id', $id))
            ->where('notes.id', '!=', $note->id)
            ->ofUser($note->user_id)
            ->notArchived()
            ->chronological('desc')
            ->get();
    }

    /**
     * Notes linking to this note (note-to-note edges).
     */
    public function noteBacklinks(Note $note): Collection
    {
        return Note::ofUser($note->user_id)
            ->whereHas('links', fn ($q) => $q->where('linkable_type', Note::class)->where('linkable_id', $note->id))
            ->where('notes.id', '!=', $note->id)
            ->notArchived()
            ->chronological('desc')
            ->get();
    }

    /**
     * Everything this note points at, grouped for display.
     */
    public function grouped(Note $note): Collection
    {
        $note->loadMissing('links.linkable');

        $meta = NoteMentionService::LINKABLE;

        return $note->links->filter(fn ($l) => $l->linkable)
            ->groupBy('linkable_type')
            ->map(fn ($links, $type) => [
                'type' => $type,
                'label' => $meta[$type] ?? class_basename($type),
                'nameColumn' => $meta[$type] ?? 'name',
                'items' => $links->map(fn ($l) => [
                    'link_id' => $l->id,
                    'id' => $l->linkable_id,
                    'name' => $l->label ?: (string) $l->linkable->{($meta[$type] ?? 'name')},
                    'model' => class_basename($type),
                ])->values(),
            ])
            ->values();
    }
}