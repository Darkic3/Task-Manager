<?php

namespace App\Services\Notes;

use App\Models\Note;
use App\Models\NoteLabel;
use App\Models\NoteSubject;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NoteMentionService
{
    /**
     * Linkable note target types and the column holding their display name.
     */
    public const LINKABLE = [
        NoteSubject::class => 'name',
        Note::class => 'title',
        Project::class => 'name',
        Task::class => 'title',
    ];

    /**
     * Pull @name and #label tokens out of free text.
     *
     * @return array<int, array{char: string, token: string, offset: int}>
     */
    public function extract(string $text): array
    {
        $pattern = '/(?<![\w\p{L}\p{N}])([@#])([\p{L}\p{N}_][\p{L}\p{N}_\-\x{200C}]*)/u';

        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

        $out = [];
        foreach ($matches[0] as $i => $whole) {
            $out[] = [
                'char' => $matches[1][$i][0],
                'token' => $matches[2][$i][0],
                'offset' => $whole[1],
            ];
        }

        return $out;
    }

    /**
     * Autocomplete candidates for the quick-capture composer.
     *
     * @return array<int, array{type: string, id: int, name: string, icon: string, hint: ?string}>
     */
    public function suggestions(int $userId, string $query, int $limit = 8): array
    {
        $raw = trim($query);
        $sign = null;

        if (str_starts_with($raw, '@') || str_starts_with($raw, '#')) {
            $sign = $raw[0];
            $needle = trim(substr($raw, 1));
        } else {
            $needle = $raw;
        }

        if ($needle === '') {
            return $this->recents($userId, $sign, $limit);
        }

        $key = note_search_key($needle);
        $results = [];

        if ($sign !== '#') {
            foreach ($this->matchSubjects($userId, $key, $limit) as $row) {
                $results[] = $row;
            }
        }

        if ($sign !== '@') {
            foreach ($this->matchLabels($userId, $key, $limit) as $row) {
                $results[] = $row;
            }
        }

        if ($sign === null) {
            foreach ($this->matchProjects($userId, $key, 4) as $row) {
                $results[] = $row;
            }
            foreach ($this->matchTasks($userId, $key, 4) as $row) {
                $results[] = $row;
            }
        }

        return array_slice($results, 0, $limit);
    }

    private function recents(int $userId, ?string $sign, int $limit): array
    {
        $out = [];

        if ($sign !== '#') {
            $out = array_merge($out, NoteSubject::ofUser($userId)
                ->orderByDesc('usage_count')->orderByDesc('updated_at')
                ->limit($limit)->get()
                ->map(fn ($s) => [
                    'type' => 'subject', 'id' => $s->id, 'name' => $s->name,
                    'icon' => $s->type === NoteSubject::TYPE_PERSON ? 'bi-person' : 'bi-collection',
                    'hint' => __('Person').' / '.__('Topic'),
                ])->all());
        }

        if ($sign !== '@') {
            $out = array_merge($out, NoteLabel::ofUser($userId)
                ->orderByDesc('usage_count')->orderByDesc('updated_at')
                ->limit($limit)->get()
                ->map(fn ($l) => [
                    'type' => 'label', 'id' => $l->id, 'name' => $l->name,
                    'icon' => 'bi-tag', 'hint' => $l->color,
                ])->all());
        }

        return array_slice($out, 0, $limit);
    }

    private function matchSubjects(int $userId, string $key, int $limit): array
    {
        if ($key === '') {
            return [];
        }

        return NoteSubject::ofUser($userId)
            ->where('name', 'like', '%'.$this->escapeLike(Str::substr($key, 0, 40)).'%')
            ->orderByDesc('usage_count')
            ->limit($limit)->get()
            ->map(fn ($s) => [
                'type' => 'subject', 'id' => $s->id, 'name' => $s->name,
                'icon' => $s->type === NoteSubject::TYPE_PERSON ? 'bi-person' : 'bi-collection',
                'hint' => $s->type,
            ])->all();
    }

    private function matchLabels(int $userId, string $key, int $limit): array
    {
        if ($key === '') {
            return [];
        }

        return NoteLabel::ofUser($userId)
            ->where('name', 'like', '%'.$this->escapeLike(Str::substr($key, 0, 40)).'%')
            ->orderByDesc('usage_count')
            ->limit($limit)->get()
            ->map(fn ($l) => [
                'type' => 'label', 'id' => $l->id, 'name' => $l->name,
                'icon' => 'bi-tag', 'hint' => $l->color,
            ])->all();
    }

    private function matchProjects(int $userId, string $key, int $limit): array
    {
        if ($key === '' || ! class_exists(Project::class)) {
            return [];
        }

        return Project::where('user_id', $userId)
            ->where('name', 'like', '%'.$this->escapeLike(Str::substr($key, 0, 40)).'%')
            ->limit($limit)->get()
            ->map(fn ($p) => [
                'type' => 'project', 'id' => $p->id, 'name' => $p->name,
                'icon' => 'bi-folder', 'hint' => __('Project'),
            ])->all();
    }

    private function matchTasks(int $userId, string $key, int $limit): array
    {
        if ($key === '') {
            return [];
        }

        return Task::where('user_id', $userId)
            ->where('title', 'like', '%'.$this->escapeLike(Str::substr($key, 0, 40)).'%')
            ->limit($limit)->get()
            ->map(fn ($t) => [
                'type' => 'task', 'id' => $t->id, 'name' => $t->title,
                'icon' => 'bi-check2-square', 'hint' => __('Task'),
            ])->all();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /**
     * Find or create a subject for a typed @token.
     */
    public function findOrCreateSubject(int $userId, string $name, string $type = NoteSubject::TYPE_TOPIC): NoteSubject
    {
        $name = trim($name);
        $slug = note_slug($name);

        $subject = NoteSubject::ofUser($userId)->where('slug', $slug)->first();

        if ($subject) {
            return $subject;
        }

        return NoteSubject::create([
            'user_id' => $userId,
            'name' => $name,
            'slug' => $slug,
            'type' => in_array($type, NoteSubject::TYPES, true) ? $type : NoteSubject::TYPE_TOPIC,
        ]);
    }

    /**
     * Find or create a curated label for a typed #token.
     */
    public function findOrCreateLabel(int $userId, string $name, ?string $color = null): NoteLabel
    {
        $name = trim($name);
        $slug = note_slug($name);

        $label = NoteLabel::ofUser($userId)->where('slug', $slug)->first();

        if ($label) {
            return $label;
        }

        return NoteLabel::create([
            'user_id' => $userId,
            'name' => $name,
            'slug' => $slug,
            'color' => $color ?: $this->pickColor(Str::lower($name)),
        ]);
    }

    /**
     * Deterministic pleasant colour from a label name.
     */
    public function pickColor(string $seed): string
    {
        $palette = ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#0ea5e9', '#64748b'];

        return $palette[abs(crc32($seed)) % count($palette)];
    }

    /**
     * Guesses a sensible note type from a quick-captured sentence.
     */
    public function guessKind(string $text, ?string $fallback = Note::KIND_GENERAL): string
    {
        $hay = note_search_key($text);

        $rules = [
            Note::KIND_DAILY => ['امروز', 'امشب', 'فردا', 'دیروز', 'today', 'tonight'],
            Note::KIND_EVENT => ['اتفاق', 'رویداد', 'حادثه', 'دیدم', 'رفتم', 'happened', 'event'],
            Note::KIND_MEETING => ['جلسه', 'ملاقات', 'گفتگو', 'meeting', 'call with', 'talked'],
            Note::KIND_DECISION => ['تصمیم', 'تصویب', 'قطعی', 'decision', 'decided'],
            Note::KIND_IDEA => ['ایده', 'پیشنهاد', 'فکر', 'idea', 'maybe we should'],
            Note::KIND_REFERENCE => ['لینک', 'منبع', 'مقاله', 'کتاب', 'reference', 'link', 'article'],
            Note::KIND_PERSON => ['شخص', 'آقای', 'خانم', 'دکتر'],
        ];

        foreach ($rules as $kind => $needles) {
            foreach ($needles as $needle) {
                if ($hay !== '' && mb_strpos($hay, note_search_key($needle)) !== false) {
                    return $kind;
                }
            }
        }

        return $fallback;
    }

    /**
     * Distinct subject/label candidates referenced by a body of text.
     *
     * @return Collection<int, array{char: string, name: string}>
     */
    public function referencedIn(int $userId, string $text): Collection
    {
        return collect($this->extract($text))
            ->map(fn ($t) => ['char' => $t['char'], 'name' => trim($t['token'])])
            ->filter(fn ($t) => $t['name'] !== '')
            ->unique('name')
            ->values();
    }
}