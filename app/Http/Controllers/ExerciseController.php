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
        $duplicate = Exercise::findDuplicate((int) $request->user()->id, $payload['name'], $payload['aliases']);

        // Re-adding an archived movement restores it (same record, history kept)
        // instead of creating a second row with the same identity.
        if ($duplicate && $duplicate->trashed()) {
            $duplicate->restore();
            $duplicate->update($payload);

            return redirect()->route('workouts.exercises.index')->with('success', __('Exercise restored from your archive.'));
        }

        if ($duplicate) {
            return back()->withInput()->withErrors(['name' => __('This exercise already exists: :name.', ['name' => $duplicate->name])]);
        }

        $request->user()->exercises()->create($payload);

        return redirect()->route('workouts.exercises.index')->with('success', __('Exercise added to your library.'));
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
        $duplicate = Exercise::findDuplicate((int) $request->user()->id, $payload['name'], $payload['aliases'], (int) $exercise->id);

        if ($duplicate) {
            return back()->withInput()->withErrors(['name' => __('This exercise already exists: :name.', ['name' => $duplicate->name])]);
        }

        $exercise->update($payload);

        return redirect()->route('workouts.exercises.index')->with('success', __('Exercise updated.'));
    }

    public function destroy(Exercise $exercise): RedirectResponse
    {
        $this->authorizeExercise($exercise);
        $exercise->delete();

        return redirect()->route('workouts.exercises.index')->with('success', __('Exercise archived. Its future history remains safe.'));
    }

    private function authorizeExercise(Exercise $exercise): void
    {
        abort_if($exercise->user_id !== auth()->id(), 403);
    }
}
