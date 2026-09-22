<?php

namespace App\Http\Controllers;

use App\Models\Routine;
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
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'required|in:daily,weekly,monthly',
            'start_time' => 'required',
            'end_time' => 'required',
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

        return $data;
    }

    private function authorizeRoutine(Routine $routine): void
    {
        abort_if($routine->user_id !== Auth::id(), 403);
    }
}
