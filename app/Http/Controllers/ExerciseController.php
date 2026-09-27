<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExerciseRequest;
use App\Models\Exercise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExerciseController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $query = Exercise::forUser($userId)
            ->withCount('workoutExercises')
            ->orderBy('name');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('normalized_name', 'like', '%'.Str::lower($search).'%');
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        return view('workouts.exercises.index', [
            'exercises' => $query->paginate(24)->withQueryString(),
            'categories' => Exercise::forUser($userId)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create()
    {
        return view('workouts.exercises.form', ['exercise' => null]);
    }

    public function store(StoreExerciseRequest $request): RedirectResponse
    {
        $payload = $request->payload();
        $duplicate = Exercise::forUser($request->user()->id)
            ->where('normalized_name', $payload['normalized_name'])
            ->first();

        if ($duplicate) {
            return back()->withInput()->withErrors(['name' => "This exercise already exists: {$duplicate->name}."]);
        }

        $request->user()->exercises()->create($payload);

        return redirect()->route('workouts.exercises.index')->with('success', 'Exercise added to your library.');
    }

    public function edit(Exercise $exercise)
    {
        $this->authorizeExercise($exercise);

        return view('workouts.exercises.form', compact('exercise'));
    }

    public function update(StoreExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        $this->authorizeExercise($exercise);
        $payload = $request->payload();
        $duplicate = Exercise::forUser($request->user()->id)
            ->where('id', '!=', $exercise->id)
            ->where('normalized_name', $payload['normalized_name'])
            ->first();

        if ($duplicate) {
            return back()->withInput()->withErrors(['name' => "This exercise already exists: {$duplicate->name}."]);
        }

        $exercise->update($payload);

        return redirect()->route('workouts.exercises.index')->with('success', 'Exercise updated.');
    }

    public function destroy(Exercise $exercise): RedirectResponse
    {
        $this->authorizeExercise($exercise);
        $exercise->delete();

        return redirect()->route('workouts.exercises.index')->with('success', 'Exercise archived. Its future history remains safe.');
    }

    private function authorizeExercise(Exercise $exercise): void
    {
        abort_if($exercise->user_id !== auth()->id(), 403);
    }
}
