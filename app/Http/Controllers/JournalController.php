<?php

namespace App\Http\Controllers;

use App\Models\JournalBook;
use App\Models\JournalEntry;
use App\Models\JournalEntryLink;
use App\Models\JournalEntryRevision;
use App\Models\JournalMood;
use App\Models\JournalTag;
use App\Models\Law;
use App\Models\Routine;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $bookId = $request->filled('book_id') ? (int) $request->input('book_id') : null;
        $tagId = $request->filled('tag_id') ? (int) $request->input('tag_id') : null;
        $moodId = $request->filled('mood_id') ? (int) $request->input('mood_id') : null;
        $from = $request->input('from');
        $to = $request->input('to');
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status');

        $entries = $user->journalEntries()
            ->with(['book:id,name,color,icon', 'tags:id,name,color', 'moods:id,name,color,category'])
            ->when($bookId, fn ($q2) => $q2->where('book_id', $bookId))
            ->when($tagId, fn ($q2) => $q2->whereHas('tags', fn ($t) => $t->where('journal_tags.id', $tagId)))
            ->when($moodId, fn ($q2) => $q2->whereHas('moods', fn ($m) => $m->where('journal_moods.id', $moodId)))
            ->when($from || $to, fn ($q2) => $q2->between($from, $to))
            ->when($q !== '', fn ($q2) => $q2->search($q))
            ->when($status, fn ($q2) => $q2->where('status', $status))
            ->notArchived()
            ->chronological('desc')
            ->paginate(20)
            ->withQueryString();

        return view('journal.index', [
            'entries' => $entries,
            'books' => $user->journalBooks()->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => $user->journalTags()->orderBy('name')->get(),
            'moods' => $user->journalMoods()->orderBy('sort_order')->orderBy('name')->get(),
            'filters' => compact('bookId', 'tagId', 'moodId', 'from', 'to', 'q', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        $entry = new JournalEntry([
            'entry_date' => today()->toDateString(),
            'status' => JournalEntry::STATUS_ACTIVE,
        ]);

        if ($request->filled('date')) {
            try {
                $entry->entry_date = \Carbon\Carbon::parse($request->input('date'))->toDateString();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $prompts = [
            __('What made today different?'),
            __('What are you grateful for today?'),
            __('What challenged you today, and how did you respond?'),
            __('Describe one moment you want to remember.'),
            __('What did you learn today?'),
        ];
        $prompt = $prompts[now()->dayOfYear % count($prompts)];

        return view('journal.create', [
            'entry' => $entry,
            'books' => $user->journalBooks()->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => $user->journalTags()->orderBy('name')->get(),
            'moods' => $user->journalMoods()->orderBy('sort_order')->orderBy('name')->get(),
            'selectedTags' => [],
            'selectedMoods' => [],
            'prompt' => $prompt,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $this->validated($request, $user);

        $entry = $user->journalEntries()->create($data);
        $this->syncRelations($entry, $request);

        return redirect()->route('journal.show', $entry)->with('status', __('Journal entry created.'));
    }

    public function show(JournalEntry $journalEntry)
    {
        $this->authorizeEntry($journalEntry);
        $journalEntry->load(['book', 'tags', 'moods', 'links.linkable']);

        $user = Auth::user();

        return view('journal.show', [
            'entry' => $journalEntry,
            'tasks' => $user->tasks()->orderBy('title')->limit(100)->get(['id', 'title']),
            'routines' => $user->routines()->orderBy('title')->limit(100)->get(['id', 'title']),
            'laws' => $user->laws()->orderBy('title')->limit(100)->get(['id', 'title']),
        ]);
    }

    public function edit(JournalEntry $journalEntry)
    {
        $this->authorizeEntry($journalEntry);
        $user = Auth::user();
        $journalEntry->load(['tags', 'moods']);

        return view('journal.edit', [
            'entry' => $journalEntry,
            'books' => $user->journalBooks()->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => $user->journalTags()->orderBy('name')->get(),
            'moods' => $user->journalMoods()->orderBy('sort_order')->orderBy('name')->get(),
            'selectedTags' => $journalEntry->tags->pluck('id')->all(),
            'selectedMoods' => $journalEntry->moods->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, JournalEntry $journalEntry)
    {
        $this->authorizeEntry($journalEntry);
        $user = Auth::user();
        $data = $this->validated($request, $user, updating: true);

        // snapshot previous version
        \App\Models\JournalEntryRevision::create([
            'journal_entry_id' => $journalEntry->id,
            'title' => $journalEntry->title,
            'content' => $journalEntry->content,
            'mood_score' => $journalEntry->mood_score,
            'tags_snapshot' => $journalEntry->tags()->pluck('id')->all(),
            'edited_at' => now(),
        ]);

        $journalEntry->fill($data);
        $journalEntry->edited_at = now();
        $journalEntry->save();
        $this->syncRelations($journalEntry, $request);

        return redirect()->route('journal.show', $journalEntry)->with('status', __('Journal entry updated.'));
    }

    public function destroy(JournalEntry $journalEntry)
    {
        $this->authorizeEntry($journalEntry);
        $journalEntry->delete();

        return redirect()->route('journal.index')->with('status', __('Journal entry deleted.'));
    }

    public function storeLink(Request $request, JournalEntry $journalEntry)
    {
        $this->authorizeEntry($journalEntry);
        $data = $request->validate([
            'linkable_type' => ['required', 'string', 'in:task,routine,law'],
            'linkable_id' => ['required', 'integer', 'min:1'],
            'label' => ['nullable', 'string', 'max:191'],
        ]);

        $map = [
            'task' => Task::class,
            'routine' => Routine::class,
            'law' => Law::class,
        ];
        $class = $map[$data['linkable_type']];
        $model = $class::where('user_id', Auth::id())->find($data['linkable_id']);
        abort_if(! $model, 404);

        $journalEntry->links()->create([
            'linkable_type' => $class,
            'linkable_id' => $model->id,
            'label' => $data['label'] ?? null,
        ]);

        return redirect()->route('journal.show', $journalEntry)->with('status', __('Link added.'));
    }

    public function destroyLink(JournalEntry $journalEntry, JournalEntryLink $link)
    {
        $this->authorizeEntry($journalEntry);
        abort_if($link->journal_entry_id !== $journalEntry->id, 403);
        $link->delete();

        return redirect()->route('journal.show', $journalEntry)->with('status', __('Link removed.'));
    }

    public function revisions(JournalEntry $journalEntry)
    {
        $this->authorizeEntry($journalEntry);

        return view('journal.revisions', [
            'entry' => $journalEntry,
            'revisions' => $journalEntry->revisions()->orderByDesc('edited_at')->get(),
        ]);
    }

    public function restoreRevision(JournalEntry $journalEntry, JournalEntryRevision $revision)
    {
        $this->authorizeEntry($journalEntry);
        abort_if($revision->journal_entry_id !== $journalEntry->id, 403);

        // snapshot current before restore
        \App\Models\JournalEntryRevision::create([
            'journal_entry_id' => $journalEntry->id,
            'title' => $journalEntry->title,
            'content' => $journalEntry->content,
            'mood_score' => $journalEntry->mood_score,
            'tags_snapshot' => $journalEntry->tags()->pluck('id')->all(),
            'edited_at' => now(),
        ]);

        $journalEntry->title = $revision->title;
        $journalEntry->content = $revision->content;
        $journalEntry->mood_score = $revision->mood_score;
        $journalEntry->edited_at = now();
        $journalEntry->save();

        if (is_array($revision->tags_snapshot)) {
            $journalEntry->tags()->sync($revision->tags_snapshot);
        }

        return redirect()->route('journal.show', $journalEntry)->with('status', __('Revision restored.'));
    }

    public function insights()
    {
        $user = Auth::user();

        $entries = $user->journalEntries()->whereNull('deleted_at')->get(['entry_date', 'mood_score', 'word_count']);
        $dates = $entries->pluck('entry_date')->map(fn ($d) => $d->toDateString())->unique()->sort()->values();

        $longest = 0;
        $run = 0;
        $prev = null;
        foreach ($dates as $d) {
            if ($prev !== null && Carbon::parse($d)->diffInDays(Carbon::parse($prev)) === 1) {
                $run++;
            } else {
                $run = 1;
            }
            $longest = max($longest, $run);
            $prev = $d;
        }

        $today = today()->toDateString();
        $current = 0;
        $cursor = Carbon::parse($today);
        while ($dates->contains($cursor->toDateString())) {
            $current++;
            $cursor->subDay();
        }

        $since = today()->subDays(29)->toDateString();
        $recent = $entries->filter(fn ($e) => $e->entry_date->toDateString() >= $since);
        $moodCounts = array_fill(1, 10, 0);
        foreach ($recent as $e) {
            if ($e->mood_score) {
                $moodCounts[$e->mood_score]++;
            }
        }

        $tagCounts = [];
        $tagMood = [];
        $allEntries = $user->journalEntries()->whereNull('deleted_at')->with('tags')->get();
        foreach ($allEntries as $e) {
            foreach ($e->tags as $tag) {
                $tagCounts[$tag->name] = ($tagCounts[$tag->name] ?? 0) + 1;
                if ($e->mood_score !== null) {
                    $tagMood[$tag->name]['sum'] = ($tagMood[$tag->name]['sum'] ?? 0) + $e->mood_score;
                    $tagMood[$tag->name]['count'] = ($tagMood[$tag->name]['count'] ?? 0) + 1;
                }
            }
        }
        arsort($tagCounts);
        $topTags = array_slice($tagCounts, 0, 10, true);

        $tagCorrelation = [];
        foreach ($topTags as $name => $count) {
            $avg = null;
            if (isset($tagMood[$name]) && $tagMood[$name]['count'] > 0) {
                $avg = round($tagMood[$name]['sum'] / $tagMood[$name]['count'], 1);
            }
            $tagCorrelation[$name] = ['count' => $count, 'avg_mood' => $avg];
        }

        $onThisDay = $user->journalEntries()
            ->whereNull('deleted_at')
            ->whereMonth('entry_date', now()->month)
            ->whereDay('entry_date', now()->day)
            ->whereYear('entry_date', '<', now()->year)
            ->orderByDesc('entry_date')
            ->get(['id', 'title', 'entry_date']);

        $totalWords = (int) $entries->sum('word_count');

        return view('journal.insights', [
            'totalEntries' => $entries->count(),
            'totalWords' => $totalWords,
            'currentStreak' => $current,
            'longestStreak' => $longest,
            'moodCounts' => $moodCounts,
            'tagCorrelation' => $tagCorrelation,
            'onThisDay' => $onThisDay,
        ]);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        $format = $request->input('format', 'md');
        $from = $request->input('from');
        $to = $request->input('to');

        $entries = $user->journalEntries()
            ->whereNull('deleted_at')
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->with(['tags', 'book', 'moods'])
            ->chronological('asc')
            ->get();

        if ($format === 'json') {
            $payload = $entries->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'content' => $e->content,
                'entry_date' => $e->entry_date?->toDateString(),
                'mood_score' => $e->mood_score,
                'energy' => $e->energy,
                'sleep_hours' => $e->sleep_hours,
                'tags' => $e->tags->pluck('name')->all(),
                'book' => $e->book?->name,
                'location' => $e->location_name,
                'weather' => $e->weather_condition,
            ])->values();

            return response()->streamDownload(function () use ($payload) {
                echo $payload->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }, 'journal-export.json', ['Content-Type' => 'application/json']);
        }

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($entries) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['date', 'title', 'content', 'mood', 'energy', 'sleep', 'tags', 'book', 'location', 'weather']);
                foreach ($entries as $e) {
                    fputcsv($out, [
                        $e->entry_date?->toDateString(),
                        $e->title,
                        $e->content,
                        $e->mood_score,
                        $e->energy,
                        $e->sleep_hours,
                        $e->tags->pluck('name')->implode('; '),
                        $e->book?->name,
                        $e->location_name,
                        $e->weather_condition,
                    ]);
                }
                fclose($out);
            }, 'journal-export.csv', ['Content-Type' => 'text/csv']);
        }

        return response()->streamDownload(function () use ($entries) {
            foreach ($entries as $e) {
                echo '# '.($e->title ?: 'Untitled entry')."\n\n";
                echo '_Date: '.$e->entry_date?->toDateString()."_\n\n";
                if ($e->mood_score) {
                    echo '_Mood: '.$e->mood_score.'/10_'.($e->energy ? '  _Energy: '.$e->energy.'/5_' : '')."\n\n";
                }
                if ($e->tags->count()) {
                    echo 'Tags: '.$e->tags->pluck('name')->implode(', ')."\n\n";
                }
                echo $e->content."\n\n---\n\n";
            }
        }, 'journal-export.md', ['Content-Type' => 'text/markdown']);
    }

    public function calendar(Request $request)
    {
        $user = Auth::user();
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $rows = $user->journalEntries()
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->whereNull('deleted_at')
            ->get(['entry_date', 'mood_score']);

        $days = [];
        foreach ($rows as $row) {
            $key = $row->entry_date->toDateString();
            if (! isset($days[$key])) {
                $days[$key] = ['count' => 0, 'mood' => null];
            }
            $days[$key]['count']++;
            if ($days[$key]['mood'] === null && $row->mood_score !== null) {
                $days[$key]['mood'] = $row->mood_score;
            }
        }

        return response()->json(['ok' => true, 'month' => $month, 'year' => $year, 'days' => $days]);
    }

    protected function validated(Request $request, $user, bool $updating = false): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'book_id' => ['nullable', 'integer'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'mood_ids' => ['nullable', 'array'],
            'mood_ids.*' => ['integer'],
            'mood_score' => ['nullable', 'integer', 'between:1,10'],
            'energy' => ['nullable', 'integer', 'between:1,5'],
            'sleep_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'sleep_quality' => ['nullable', 'integer', 'between:1,5'],
            'weather_condition' => ['nullable', 'string', 'max:60'],
            'weather_temp' => ['nullable', 'numeric'],
            'location_name' => ['nullable', 'string', 'max:191'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['nullable', Rule::in(JournalEntry::STATUSES)],
            'is_pinned' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
        ]);

        // ownership checks
        if (! empty($data['book_id'])) {
            abort_unless(JournalBook::where('id', $data['book_id'])->where('user_id', $user->id)->exists(), 422);
        }
        if (! empty($data['tag_ids'])) {
            $valid = JournalTag::where('user_id', $user->id)->whereIn('id', $data['tag_ids'])->pluck('id')->all();
            $data['tag_ids'] = array_values(array_intersect($data['tag_ids'], $valid));
        }
        if (! empty($data['mood_ids'])) {
            $valid = JournalMood::where('user_id', $user->id)->whereIn('id', $data['mood_ids'])->pluck('id')->all();
            $data['mood_ids'] = array_values(array_intersect($data['mood_ids'], $valid));
        }

        $data['user_id'] = $user->id;
        $data['word_count'] = $this->wordCount($data['content']);
        $data['status'] = $data['status'] ?? JournalEntry::STATUS_ACTIVE;
        $data['is_pinned'] = (bool) ($data['is_pinned'] ?? false);
        $data['is_favorite'] = (bool) ($data['is_favorite'] ?? false);

        // strip relation-only keys before fill
        unset($data['tag_ids'], $data['mood_ids']);

        return $data;
    }

    protected function syncRelations(JournalEntry $entry, Request $request): void
    {
        if ($request->has('tag_ids')) {
            $entry->tags()->sync($request->input('tag_ids', []));
        }
        if ($request->has('mood_ids')) {
            $sync = [];
            foreach ($request->input('mood_ids', []) as $id) {
                $sync[$id] = ['intensity' => 3];
            }
            $entry->moods()->sync($sync);
        }
    }

    protected function authorizeEntry(JournalEntry $entry): void
    {
        abort_if($entry->user_id !== Auth::id(), 403);
    }

    protected function wordCount(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $text) ?: []);
    }
}
