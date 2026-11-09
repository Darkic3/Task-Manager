<?php

namespace App\Http\Controllers;

use App\Models\JournalBook;
use App\Models\JournalEntry;
use App\Models\JournalMood;
use App\Models\JournalTag;
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

        return view('journal.create', [
            'entry' => $entry,
            'books' => $user->journalBooks()->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => $user->journalTags()->orderBy('name')->get(),
            'moods' => $user->journalMoods()->orderBy('sort_order')->orderBy('name')->get(),
            'selectedTags' => [],
            'selectedMoods' => [],
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
        $journalEntry->load(['book', 'tags', 'moods', 'links']);

        return view('journal.show', ['entry' => $journalEntry]);
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

    public function calendar(Request $request)
    {
        $user = Auth::user();
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $start = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
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
