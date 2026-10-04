<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutPlanRequest;
use App\Models\Exercise;
use App\Models\WorkoutPlan;
use App\Services\WorkoutPlanService;
use Illuminate\Http\RedirectResponse;

class WorkoutPlanController extends Controller
{
    public function __construct(private readonly WorkoutPlanService $plans) {}

    public function index()
    {
        $plans = auth()->user()->workoutPlans()
            ->withCount('days')
            ->with(['days' => fn ($query) => $query->withCount('exercises')->orderBy('sort_order')])
            ->latest('start_date')
            ->latest()
            ->get();

        return view('workouts.plans.index', compact('plans'));
    }

    public function create()
    {
        $exercises = Exercise::forUser(auth()->id())->orderBy('name')->get();

        return view('workouts.plans.form', [
            'plan' => null,
            'exercises' => $exercises,
            'weekdays' => WorkoutPlan::WEEKDAYS,
        ]);
    }

    public function store(StoreWorkoutPlanRequest $request): RedirectResponse
    {
        $plan = $this->plans->save($request->payload(), $request->user()->id);

        return redirect()->route('workouts.plans.edit', $plan)->with('success', __('Workout plan created.'));
    }

    public function edit(WorkoutPlan $workoutPlan)
    {
        $this->authorizePlan($workoutPlan);
        $workoutPlan->load(['days.exercises.exercise', 'rules']);

        return view('workouts.plans.form', [
            'plan' => $workoutPlan,
            'exercises' => Exercise::forUser(auth()->id())->orderBy('name')->get(),
            'weekdays' => WorkoutPlan::WEEKDAYS,
        ]);
    }

    public function update(StoreWorkoutPlanRequest $request, WorkoutPlan $workoutPlan): RedirectResponse
    {
        $this->authorizePlan($workoutPlan);
        $this->plans->save($request->payload(), $request->user()->id, $workoutPlan);

        return redirect()->route('workouts.plans.edit', $workoutPlan)->with('success', __('Workout plan updated.'));
    }

    public function newCycle(WorkoutPlan $workoutPlan): RedirectResponse
    {
        $this->authorizePlan($workoutPlan);
        $copy = $this->plans->cloneCycle($workoutPlan);

        return redirect()->route('workouts.plans.edit', $copy)
            ->with('success', __('Cycle :num created. The previous plan and its history were left unchanged.', ['num' => $copy->cycle_no]));
    }

    public function destroy(WorkoutPlan $workoutPlan): RedirectResponse
    {
        $this->authorizePlan($workoutPlan);
        $workoutPlan->delete();

        return redirect()->route('workouts.plans.index')->with('success', __('Workout plan archived.'));
    }

    private function authorizePlan(WorkoutPlan $plan): void
    {
        abort_if($plan->user_id !== auth()->id(), 403);
    }
}
