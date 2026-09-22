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

        $upcomingDailyRoutines = $user->routines()->where('frequency', 'daily')->latest()->get();
        $upcomingWeeklyRoutines = $user->routines()->where('frequency', 'weekly')->latest()->get();
        $upcomingMonthlyRoutines = $user->routines()->where('frequency', 'monthly')->latest()->get();

        return view('routines.index', compact('upcomingDailyRoutines', 'upcomingWeeklyRoutines', 'upcomingMonthlyRoutines'));
    }

    public function create()
    {
        return view('routines.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Auth::user()->routines()->create($data);

        return redirect()->route('routines.index')->with('success', 'Routine created successfully.');
    }

    public function edit(Routine $routine)
    {
        $this->authorizeRoutine($routine);

        return view('routines.edit', compact('routine'));
    }

    public function update(Request $request, Routine $routine)
    {
        $this->authorizeRoutine($routine);

        $routine->update($this->validated($request));

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

        return view('routines.stats', compact('routine', 'weeks', 'monthSpans', 'streak', 'adherence'));
    }

    public function showAll()
    {
        return redirect()->route('routines.index');
    }

    public function showDaily()
    {
        return $this->byFrequency('daily', 'routines.daily');
    }

    public function showWeekly()
    {
        return $this->byFrequency('weekly', 'routines.weekly');
    }

    public function showMonthly()
    {
        return $this->byFrequency('monthly', 'routines.monthly');
    }

    private function byFrequency(string $frequency, string $view)
    {
        $routines = Auth::user()->routines()
            ->when($frequency !== 'all', fn ($q) => $q->where('frequency', $frequency))
            ->latest()
            ->get();

        $dailyRoutines = $routines->where('frequency', 'daily')->values();
        $weeklyRoutines = $routines->where('frequency', 'weekly')->values();
        $monthlyRoutines = $routines->where('frequency', 'monthly')->values();

        return view($view, compact('dailyRoutines', 'weeklyRoutines', 'monthlyRoutines'));
    }

    private function validated(Request $request): array
    {
        $periodKeys = array_keys(config('routines.periods', []));

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'required|in:daily,weekly,monthly',
            'time_period' => 'nullable|in:'.implode(',', $periodKeys),
            'start_time' => 'nullable|required_with:end_time',
            'end_time' => 'nullable|required_with:start_time|after_or_equal:start_time',
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
}
