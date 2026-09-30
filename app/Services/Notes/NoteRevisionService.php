<?php

namespace App\Services\Notes;

use App\Models\Note;
use App\Models\NoteRevision;

class NoteRevisionService
{
    /**
     * Fields whose changes are worth recording.
     */
    public const TRACKED = [
        'title', 'content', 'kind', 'notebook_id', 'occurred_at', 'summary', 'mood', 'energy', 'status',
    ];

    /**
     * Snapshot the note as it stands right now (call BEFORE mutating).
     */
    public function snapshot(Note $note, string $source = NoteRevision::SOURCE_USER, ?string $reason = null, array $changed = []): NoteRevision
    {
        return NoteRevision::create([
            'note_id' => $note->id,
            'user_id' => $note->user_id,
            'title' => $note->title,
            'content' => $note->content,
            'kind' => $note->kind,
            'notebook_id' => $note->notebook_id,
            'change_source' => $source,
            'reason' => $reason,
            'changed_fields' => array_values($changed),
        ]);
    }

    /**
     * Which tracked fields actually differ between the note and $input.
     *
     * @return array<int, string>
     */
    public function changedFields(Note $note, array $input): array
    {
        $changed = [];

        foreach (self::TRACKED as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $before = $this->comparable($note->{$field});
            $after = $this->comparable($input[$field]);

            if ($before !== $after) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    private function comparable($value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    /**
     * Restore a note to a previous revision. Returns false when nothing to do.
     */
    public function restore(Note $note, NoteRevision $revision): bool
    {
        if ((int) $revision->note_id !== (int) $note->id) {
            return false;
        }

        // Keep the current state recoverable before overwriting it.
        $this->snapshot($note, NoteRevision::SOURCE_SYSTEM, __('Restore'));

        $note->forceFill([
            'title' => $revision->title,
            'content' => $revision->content,
            'kind' => $revision->kind ?: Note::KIND_GENERAL,
            'notebook_id' => $revision->notebook_id,
        ])->save();

        return true;
    }
}