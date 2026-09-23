<?php

namespace App\Http\Controllers;

use App\Models\Routine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoutineController extends Controller
{
    private const WEEK_DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    public function index()
    {
        $user = Auth::user();
        $routines = $user->routines()->orderBy('title')->get();

        foreach ($routines as $routine) {
            $m = $routine->habitMetrics(now()->startOfDay());
            $routine->ringRate = $m['rate'];
            $routine->ringStreak = $m['streak'];
            $routine->ringLast7 = $m['last7'];
        }

        $weekly = $this->weeklyConsistency($routines, now()->startOfDay());

        return view('routines.index', compact('routines', 'weekly'));
    }

    public function create()
    {
        return view('routines.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $items = $request->input('items', []);

        $routine = Auth::user()->routines()->create($data);
        $this->syncItems($routine, $items);

        return redirect()->route('routines.index')->with('success', 'Routine created successfully.');
    }

    public function edit(Routine $routine)
    {
        $this->authorizeRoutine($routine);
        $routine->load('checklistItems');

        return view('routines.edit', compact('routine'));
    }

    public function update(Request $request, Routine $routine)
    {
        $this->authorizeRoutine($routine);

        $data = $this->validated($request);
        $routine->update($data);
        $this->syncItems($routine, $request->input('items', []));

        return redirect()->route('routines.index')->with('success', 'Routine updated successfully.');
    }

    public function destroy(Routine $routine)
    {
        $this->authorizeRoutine($routine);

        $routine->delete();

        return redirect()->route('routines.index')->with('success', 'Routine deleted successfully.');
    }

    public function stats(Routine $routine)
    {
        $this->authorizeRoutine($routine);

        $today = now()->startOfDay();
        $start = $today->copy()->startOfWeek(Carbon::SATURDAY)->subWeeks(15)->startOfDay();
        $end = $start->copy()->addDays(16 * 7 - 1)->startOfDay();

        $cells = $routine->heatmapCells($start, $end);

        $weeks = [];
        $monthSpans = [];
        $cursor = $start->copy();

        for ($w = 0; $w < 16; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $week[] = $cells[$cursor->toDateString()];
                $cursor->addDay();
            }
            $weeks[] = $week;

            $label = $week[0]['date']->format('M');
            if (empty($monthSpans) || end($monthSpans)['label'] !== $label) {
                $monthSpans[] = ['label' => $label, 'span' => 1];
            } else {
                $monthSpans[count($monthSpans) - 1]['span']++;
            }
        }

        $streak = $routine->streakStats($today);
        $adherence = $routine->adherence(30, $today);
        $tracker = $routine->completionTracker();

        return view('routines.stats', compact('routine', 'weeks', 'monthSpans', 'streak', 'adherence', 'tracker'));
    }

    public function showAll()
    {
        return redirect()->route('routines.index');
    }

    public function showDaily()
    {
        return redirect()->route('routines.index', ['filter' => 'daily']);
    }

    public function showWeekly()
    {
        return redirect()->route('routines.index', ['filter' => 'weekly']);
    }

    public function showMonthly()
    {
        return redirect()->route('routines.index', ['filter' => 'monthly']);
    }

    /**
     * Weekly consistency: occurrences vs completions over the last 7 days
     * across all routines (today's unchecked occurrences don't count as misses).
     */
    private function weeklyConsistency($routines, Carbon $today): array
    {
        $start = $today->copy()->subDays(6);
        $occ = 0;
        $done = 0;

        foreach ($routines as $routine) {
            $dates = $routine->occurrenceDates($start, $today);

            if ($dates && end($dates) === $today->toDateString() && ! $routine->completedOn($today)) {
                array_pop($dates);
            }

            $occ += count($dates);
            $done += count(array_intersect($dates, $routine->completionDateKeys($start, $today)));
        }

        return [
            'done' => $done,
            'total' => $occ,
            'rate' => $occ ? (int) round($done / $occ * 100) : 0,
        ];
    }

    private function validated(Request $request): array
    {
        $periodKeys = array_keys(config('routines.periods', []));

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'required|in:daily,weekly,monthly,every_n_days',
            'every_n_days' => 'nullable|required_if:frequency,every_n_days|integer|min:2|max:60',
            'time_period' => 'nullable|in:'.implode(',', $periodKeys),
            'start_time' => 'nullable|required_with:end_time',
            'end_time' => 'nullable|required_with:start_time|after_or_equal:start_time',
            'items' => 'nullable|array|max:20',
            'items.*.name' => 'required_with:items.*.id|string|max:255',
        ];

        if ($request->input('frequency') === 'weekly') {
            $rules['days'] = 'required|array|min:1';
            $rules['days.*'] = 'string|in:'.implode(',', self::WEEK_DAYS);
        } elseif ($request->input('frequency') === 'monthly') {
            $rules['month_days'] = 'required|array|min:1';
            $rules['month_days.*'] = 'integer|between:1,31';
        }

        $data = $request->validate($rules);

        $frequency = $data['frequency'];

        if ($frequency === 'weekly') {
            $data['days'] = array_values(array_unique(array_map('strtolower', $data['days'])));
            $data['month_days'] = null;
        } elseif ($frequency === 'monthly') {
            $monthDays = array_values(array_unique(array_map('intval', $data['month_days'])));
            sort($monthDays);
            $data['month_days'] = $monthDays;
            $data['days'] = null;
        } else {
            $data['days'] = null;
            $data['month_days'] = null;
        }

        if ($frequency === 'every_n_days') {
            $data['every_n_days'] = max(2, (int) $data['every_n_days']);
            $data['days'] = null;
            $data['month_days'] = null;
        } else {
            $data['every_n_days'] = null;
        }

        $data['weeks'] = null;
        $data['months'] = null;

        // A routine is scheduled either by a time-of-day period or by an exact
        // time window — never both.
        $data['time_period'] = $data['time_period'] ?? null;

        if ($data['time_period']) {
            $data['start_time'] = null;
            $data['end_time'] = null;
        } else {
            $data['start_time'] = $data['start_time'] ?? null;
            $data['end_time'] = $data['end_time'] ?? null;
        }

        return $data;
    }

    private function authorizeRoutine(Routine $routine): void
    {
        abort_if($routine->user_id !== Auth::id(), 403);
    }

    /**
     * Sync persistent checklist "steps" from form rows:
     * rows with an id update, new rows create, missing rows delete.
     */
    private function syncItems(Routine $routine, $items): void
    {
        $keep = [];

        foreach ((array) $items as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $id = isset($row['id']) ? (int) $row['id'] : null;

            if ($id) {
                $item = $routine->checklistItems()->whereKey($id)->first();
                if ($item) {
                    $item->update(['name' => $name, 'sort_order' => $i]);
                    $keep[] = $item->id;

                    continue;
                }
            }

            $item = $routine->checklistItems()->create([
                'user_id' => $routine->user_id,
                'name' => $name,
                'sort_order' => $i,
            ]);
            $keep[] = $item->id;
        }

        $routine->checklistItems()->whereNotIn('id', $keep ?: [0])->delete();
    }
}
