<?php

namespace App\Http\Controllers;

use App\Models\WorkoutDay;
use App\Models\WorkoutSession;

class WorkoutDayController extends Controller
{
    public function show(WorkoutDay $workoutDay)
    {
        abort_if($workoutDay->plan()->where('user_id', auth()->id())->doesntExist(), 403);

        $workoutDay->load(['plan', 'exercises.exercise']);

        $todaySession = WorkoutSession::where('user_id', auth()->id())
            ->where('workout_day_id', $workoutDay->id)
            ->whereDate('workout_date', now()->toDateString())
            ->first();

        return view('workouts.days.show', [
            'day' => $workoutDay,
            'todaySession' => $todaySession,
        ]);
    }
}
