<?php

namespace App\Console\Commands;

use App\Models\Note;
use App\Models\NoteLabel;
use App\Models\NoteSubject;
use App\Services\Notes\NoteMentionService;
use Illuminate\Console\Command;

class CleanupCodeMentions extends Command
{
    protected $signature = 'notes:cleanup-code-mentions
                            {--dry-run : Report what would be removed without changing anything}';

    protected $description = 'Detach labels/topics that were auto-created from @/# tokens inside code blocks';

    public function handle(NoteMentionService $mentions): int
    {
        $dry = (bool) $this->option('dry-run');
        $detachedLabels = 0;
        $detachedSubjects = 0;

        Note::query()
            ->with('labels:id,name,slug')
            ->chunkById(200, function ($notes) use ($mentions, $dry, &$detachedLabels, &$detachedSubjects) {
                foreach ($notes as $note) {
                    $refs = $mentions->referencedIn((int) $note->user_id, (string) $note->content);
                    $keepLabels = $refs->where('char', '#')->pluck('name')
                        ->map(fn ($name) => note_slug($name))->all();
                    $keepSubjects = $refs->where('char', '@')->pluck('name')
                        ->map(fn ($name) => note_slug($name))->all();

                    foreach ($note->labels as $label) {
                        if ($this->looksLikeCodeToken($label->name) && ! in_array($label->slug, $keepLabels, true)) {
                            $detachedLabels++;
                            if (! $dry) {
                                $note->labels()->detach($label->id);
                            }
                        }
                    }

                    $subjectLinks = $note->links()
                        ->ofType(NoteSubject::class)
                        ->with('linkable:id,name,slug')
                        ->get();

                    foreach ($subjectLinks as $link) {
                        $subject = $link->linkable;
                        if (! $subject) {
                            continue;
                        }
                        if ($this->looksLikeCodeToken($subject->name) && ! in_array($subject->slug, $keepSubjects, true)) {
                            $detachedSubjects++;
                            if (! $dry) {
                                $link->delete();
                            }
                        }
                    }
                }
            });

        $orphanLabels = 0;
        $orphanSubjects = 0;

        if (! $dry) {
            NoteLabel::withCount('notes')->get()->each(function (NoteLabel $label) use (&$orphanLabels) {
                if ($this->looksLikeCodeToken($label->name) && (int) $label->notes_count === 0) {
                    $orphanLabels++;
                    $label->delete();
                }
            });

            NoteSubject::withCount('links')->get()->each(function (NoteSubject $subject) use (&$orphanSubjects) {
                if ($this->looksLikeCodeToken($subject->name) && (int) $subject->links_count === 0) {
                    $orphanSubjects++;
                    $subject->delete();
                }
            });
        }

        $prefix = $dry ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sDetached %d labels and %d topics; deleted %d orphan labels and %d orphan topics.',
            $prefix,
            $detachedLabels,
            $detachedSubjects,
            $orphanLabels,
            $orphanSubjects
        ));

        return self::SUCCESS;
    }

    private function looksLikeCodeToken(string $name): bool
    {
        return (bool) preg_match('/^(?:[0-9a-f]{3,8}|[a-z0-9]+[-_])$/i', trim($name));
    }
}
