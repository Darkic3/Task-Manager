<?php

namespace App\Http\Requests;

use App\Models\Note;
use App\Models\NoteLabel;
use App\Models\Notebook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('note');

        return $note
            ? $this->user()->can('update', $note)
            : $this->user()->can('create', Note::class);
    }

    public function rules(): array
    {
        $userId = (int) $this->user()->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'summary' => ['nullable', 'string', 'max:2000'],

            'kind' => ['nullable', 'string', Rule::in(Note::KINDS)],
            'status' => ['nullable', 'string', Rule::in(Note::STATUSES)],
            'visibility' => ['nullable', 'string', Rule::in(Note::VISIBILITIES)],

            'notebook_id' => [
                'nullable', 'integer',
                Rule::exists('notebooks', 'id')->where('user_id', $userId),
            ],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('notes', 'id')->where('user_id', $userId),
            ],

            'occurred_at' => ['nullable', 'date'],
            'mood' => ['nullable', 'integer', 'between:1,5'],
            'energy' => ['nullable', 'integer', 'between:1,5'],

            'is_favorite' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],

            // legacy free-text tags (comma separated)
            'tags' => ['nullable', 'string', 'max:1000'],

            // curated labels
            'label_ids' => ['nullable', 'array', 'max:40'],
            'label_ids.*' => [
                'integer',
                Rule::exists('note_labels', 'id')->where('user_id', $userId),
            ],

            // composer picks for polymorphic links
            'mentions' => ['nullable', 'array', 'max:50'],
            'mentions.*.type' => ['required', 'string', Rule::in(['subject', 'label', 'project', 'task', 'note'])],
            'mentions.*.id' => ['required', 'integer', 'min:1'],

            'file_ids' => ['nullable', 'array', 'max:20'],
            'file_ids.*' => [
                'integer',
                Rule::exists('files', 'id')->where('user_id', $userId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'label_ids.*.exists' => __('One of the selected labels does not exist.'),
            'notebook_id.exists' => __('The selected notebook does not exist.'),
            'mentions.*.type.in' => __('Unsupported linked item type.'),
        ];
    }

    /**
     * Attributes ready for the model, with sane defaults for new notes.
     */
    public function noteAttributes(bool $creating): array
    {
        $data = $this->safe()->except(['label_ids', 'mentions', 'file_ids', 'tags']);

        $data['kind'] = $this->input('kind') ?: Note::KIND_GENERAL;
        $data['status'] = $this->input('status') ?: Note::STATUS_ACTIVE;
        $data['visibility'] = $this->input('visibility') ?: Note::VISIBILITY_PRIVATE;

        foreach (['is_favorite', 'is_pinned'] as $flag) {
            $data[$flag] = $this->boolean($flag);
        }

        foreach (['notebook_id', 'parent_id', 'mood', 'energy'] as $nullable) {
            $data[$nullable] = $this->input($nullable) !== null && $this->input($nullable) !== ''
                ? $this->input($nullable)
                : null;
        }

        $data['occurred_at'] = $this->input('occurred_at') ?: ($creating ? now() : null);

        return $data;
    }

    public function legacyTags(): ?array
    {
        if (! $this->has('tags')) {
            return null;
        }

        $tags = array_filter(array_map('trim', explode(',', (string) $this->input('tags'))));

        return array_values($tags);
    }

    public function labelIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('label_ids', []))));
    }

    public function mentions(): array
    {
        return (array) $this->input('mentions', []);
    }

    public function fileIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('file_ids', []))));
    }

    /**
     * Notebook suggestions for the "move to" picker.
     */
    public function notebookOptions(): array
    {
        return Notebook::ofUser((int) $this->user()->id)
            ->orderBy('title')
            ->get(['id', 'title', 'parent_id', 'color', 'icon'])
            ->map(fn ($nb) => [
                'id' => $nb->id,
                'title' => $nb->title,
                'path' => $nb->pathTitle(),
                'color' => $nb->color,
                'icon' => $nb->icon,
            ])
            ->all();
    }

    public function labelOptions()
    {
        return NoteLabel::ofUser((int) $this->user()->id)->orderBy('name')->get();
    }
}