<?php

namespace App\Http\Controllers;

use App\Models\Law;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LawController extends Controller
{
    /**
     * Laws hub: tabs for active / paused / expired / archived.
     * Expired is virtual (active laws whose end date passed) — no renewal,
     * the user creates a new law instead.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tab = in_array($request->query('tab'), ['active', 'paused', 'expired', 'archived', 'all'], true)
            ? $request->query('tab')
            : 'active';

        $laws = $user->laws()
            ->withCount('events')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $today = today()->toDateString();

        $buckets = [
            'active' => $laws->filter(fn (Law $l) => $l->status === Law::STATUS_ACTIVE && ! $l->isExpired())->values(),
            'paused' => $laws->filter(fn (Law $l) => $l->status === Law::STATUS_PAUSED)->values(),
            'expired' => $laws->filter(fn (Law $l) => $l->isExpired())->values(),
            'archived' => $laws->filter(fn (Law $l) => $l->status === Law::STATUS_ARCHIVED)->values(),
        ];

        $visible = $tab === 'all' ? $laws : ($buckets[$tab] ?? collect());

        // One XP query for every law on the page (no N+1).
        $xpByLaw = $user->lawEvents()
            ->selectRaw('law_id, COALESCE(SUM(xp_delta), 0) as total, SUM(direction = 1) as plus, SUM(direction = -1) as minus')
            ->whereIn('law_id', $visible->pluck('id')->all() ?: [0])
            ->groupBy('law_id')
            ->get()
            ->keyBy('law_id');

        return view('laws.index', [
            'tab' => $tab,
            'laws' => $visible,
            'counts' => [
                'active' => $buckets['active']->count(),
                'paused' => $buckets['paused']->count(),
                'expired' => $buckets['expired']->count(),
                'archived' => $buckets['archived']->count(),
                'all' => $laws->count(),
            ],
            'xpByLaw' => $xpByLaw,
            'today' => $today,
        ]);
    }

    public function create()
    {
        return view('laws.create', ['law' => new Law([
            'coefficient' => 1,
            'starts_on' => today()->toDateString(),
            'status' => Law::STATUS_ACTIVE,
            'icon' => 'bi-shield-check',
            'color' => '#7c3aed',
        ])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $maxOrder = (int) (Auth::user()->laws()->max('sort_order') ?? 0);

        $law = Auth::user()->laws()->create($data + ['sort_order' => $maxOrder + 1]);

        return redirect()->route('laws.show', $law)
            ->with('success', __('Law created.'));
    }

    public function show(Law $law, \App\Services\LawLinkService $links)
    {
        $this->owned($law);

        $law->load(['links', 'events' => fn ($q) => $q->orderByDesc('occurred_on')->orderByDesc('id')->limit(100)]);

        $stats = $law->xpStats();

        $weekStart = today()->subDays(6)->toDateString();
        $week = [
            'plus' => $law->events()->where('direction', 1)->where('occurred_on', '>=', $weekStart)->count(),
            'minus' => $law->events()->where('direction', -1)->where('occurred_on', '>=', $weekStart)->count(),
        ];

        $grouped = $links->grouped($law);

        // Project inheritance preview: open tasks of each linked project tree.
        $projectTasks = [];
        foreach (($grouped[\App\Models\Project::class] ?? collect()) as $link) {
            if ($link->linkable) {
                $projectTasks[$link->id] = $links->projectTasksForLaw($link->linkable, $law);
            }
        }

        return view('laws.show', [
            'law' => $law,
            'stats' => $stats,
            'week' => $week,
            'grouped' => $grouped,
            'projectTasks' => $projectTasks,
        ]);
    }

    public function edit(Law $law)
    {
        $this->owned($law);

        return view('laws.edit', ['law' => $law]);
    }

    public function update(Request $request, Law $law)
    {
        $this->owned($law);

        $data = $request->validate($this->rules());

        $law->update($data);

        return redirect()->route('laws.show', $law)
            ->with('success', __('Law updated.'));
    }

    public function destroy(Law $law)
    {
        $this->owned($law);

        // DayLog entries keep their text but lose the law reference;
        // law_events cascade-delete via FK.
        \App\Models\DayLog::where('law_id', $law->id)
            ->update(['law_id' => null, 'law_direction' => null]);

        $law->delete();

        return redirect()->route('laws.index')
            ->with('success', __('Law deleted.'));
    }

    /**
     * Pause a law: it stays visible under "paused" but accepts no new events.
     */
    public function pause(Law $law)
    {
        $this->owned($law);
        abort_if($law->isArchived(), 422);

        $law->update(['status' => Law::STATUS_PAUSED]);

        return back()->with('success', __('Law paused.'));
    }

    /**
     * Resume a paused law (expired laws resume as expired history view).
     */
    public function resume(Law $law)
    {
        $this->owned($law);
        abort_if($law->isArchived(), 422);

        $law->update(['status' => Law::STATUS_ACTIVE]);

        return back()->with('success', __('Law resumed.'));
    }

    /**
     * Archive a law: read-only history, kept for reports.
     */
    public function archive(Law $law)
    {
        $this->owned($law);

        $law->update(['status' => Law::STATUS_ARCHIVED]);

        return back()->with('success', __('Law archived.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|string|max:60',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'coefficient' => 'required|integer|min:1|max:10',
            'starts_on' => 'required|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
        ];
    }

    private function owned(Law $law): void
    {
        abort_if($law->user_id !== Auth::id(), 403);
    }
}
