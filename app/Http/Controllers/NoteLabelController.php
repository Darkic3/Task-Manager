<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteLabel;
use App\Services\Notes\NoteMentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NoteLabelController extends Controller
{
    public function __construct(private NoteMentionService $mentions) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $userId = (int) Auth::id();
        $name = trim($data['name']);
        $slug = note_slug($name);

        $label = NoteLabel::ofUser($userId)->firstOrNew(['slug' => $slug]);
        $label->fill([
            'user_id' => $userId,
            'name' => $name,
            'color' => $data['color'] ?: $this->mentions->pickColor($slug),
        ])->save();

        return response()->json([
            'success' => true,
            'label' => $this->payload($label),
        ], $label->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, NoteLabel $noteLabel): JsonResponse
    {
        $this->authorize('update', $noteLabel);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if (isset($data['name'])) {
            $data['slug'] = note_slug(trim($data['name']));
        }

        $noteLabel->update($data);

        return response()->json([
            'success' => true,
            'label' => $this->payload($noteLabel->refresh()),
        ]);
    }

    public function destroy(NoteLabel $noteLabel): JsonResponse
    {
        $this->authorize('delete', $noteLabel);

        $noteLabel->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Merge one label into another, moving every note across.
     */
    public function merge(Request $request, NoteLabel $noteLabel): JsonResponse
    {
        $this->authorize('delete', $noteLabel);

        $data = $request->validate([
            'target_id' => ['required', 'integer', 'different:'.$noteLabel->id],
        ]);

        $target = NoteLabel::ofUser((int) $noteLabel->user_id)->whereKey($data['target_id'])->first();

        if (! $target) {
            return response()->json(['success' => false, 'message' => __('Target label not found.')], 422);
        }

        $userId = (int) $noteLabel->user_id;

        DB::transaction(function () use ($noteLabel, $target, $userId) {
            $noteIds = $noteLabel->notes()->pluck('notes.id')->all();

            foreach ($noteIds as $noteId) {
                DB::table('label_note')->insertOrIgnore([
                    'note_id' => $noteId,
                    'note_label_id' => $target->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $noteLabel->delete();
            $target->touch();
        });

        $moved = Note::ofUser($userId)->whereHas('labels', fn ($q) => $q->whereKey($target->id))->count();

        return response()->json([
            'success' => true,
            'target' => $this->payload($target->refresh()),
            'message' => __('Label merged.'),
            'notes' => $moved,
        ]);
    }

    private function payload(NoteLabel $label): array
    {
        return [
            'id' => $label->id,
            'name' => $label->name,
            'slug' => $label->slug,
            'color' => $label->color,
            'notes_count' => $label->notes()->count(),
        ];
    }
}