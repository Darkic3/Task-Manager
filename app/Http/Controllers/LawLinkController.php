<?php

namespace App\Http\Controllers;

use App\Models\Law;
use App\Models\LawLink;
use App\Models\Task;
use App\Services\LawLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LawLinkController extends Controller
{
    /**
     * Attach a task / routine / project to the law with a preset direction.
     * Re-attaching the same entity only retargets its direction.
     */
    public function store(Request $request, Law $law, LawLinkService $links)
    {
        $this->owned($law);

        $data = $request->validate([
            'type' => 'required|in:task,routine,project',
            'id' => 'required|integer|min:1',
            'effect' => 'required|integer|in:1,-1',
        ]);

        $type = $links->typeFromAlias($data['type']);
        $link = $links->attach($law, $type, (int) $data['id'], (int) $data['effect']);

        if (! $link) {
            return back()->withErrors(['id' => __('Could not attach. The item was not found.')]);
        }

        return back()->with('success', __('Activity linked.'));
    }

    /**
     * Flip the preset direction of an existing link (for ↔ against).
     */
    public function update(Request $request, Law $law, LawLink $link)
    {
        $this->owned($law);
        $this->scoped($law, $link);

        $data = $request->validate([
            'effect' => 'required|integer|in:1,-1',
        ]);

        $link->update(['effect' => (int) $data['effect'], 'is_excluded' => false]);

        return back()->with('success', __('Direction updated.'));
    }

    public function destroy(Law $law, LawLink $link)
    {
        $this->owned($law);
        $this->scoped($law, $link);

        $link->delete();

        return back()->with('success', __('Link removed.'));
    }

    /**
     * Opt a single task out of its projects' inheritance for this law
     * (or bring it back in).
     */
    public function toggleExclusion(Request $request, Law $law, Task $task, LawLinkService $links)
    {
        $this->owned($law);
        abort_if($task->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'excluded' => 'required|boolean',
        ]);

        if ($data['excluded']) {
            $links->excludeTask($law, $task);

            return back()->with('success', __('Task excluded from inheritance.'));
        }

        LawLink::where('law_id', $law->id)
            ->where('linkable_type', Task::class)
            ->where('linkable_id', $task->id)
            ->where('is_excluded', true)
            ->delete();

        return back()->with('success', __('Task follows project inheritance again.'));
    }

    /**
     * JSON search for the link picker (only not-yet-linked items).
     */
    public function candidates(Request $request, Law $law, LawLinkService $links)
    {
        $this->owned($law);

        $data = $request->validate([
            'type' => 'required|in:task,routine,project',
            'q' => 'nullable|string|max:100',
        ]);

        $type = $links->typeFromAlias($data['type']);

        $linkedIds = $law->links()
            ->where('linkable_type', $type)
            ->pluck('linkable_id')
            ->all();

        $items = $links->candidates(Auth::user(), $type, $data['q'] ?? '')
            ->reject(fn ($item) => in_array($item['id'], $linkedIds, true))
            ->values();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    private function owned(Law $law): void
    {
        abort_if($law->user_id !== Auth::id(), 403);
    }

    private function scoped(Law $law, LawLink $link): void
    {
        abort_if((int) $link->law_id !== (int) $law->id, 404);
    }
}
