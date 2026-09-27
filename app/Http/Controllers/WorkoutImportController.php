<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutImportRequest;
use App\Models\WorkoutImport;
use App\Services\WorkoutImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WorkoutImportController extends Controller
{
    public function __construct(private readonly WorkoutImportService $imports) {}

    public function create()
    {
        return view('workouts.imports.create');
    }

    public function store(StoreWorkoutImportRequest $request): RedirectResponse
    {
        try {
            $structure = $this->imports->parseWithAi($request->user(), $request->validated('source'));
            $import = $request->user()->workoutImports()->create([
                'source_text' => $request->validated('source'),
                'structure' => $structure,
                'status' => WorkoutImport::PREVIEW,
                'expires_at' => now()->addMinutes(30),
            ]);

            return redirect()->route('workouts.imports.show', $import)->with('success', 'AI parsed the plan. Review the matches before importing.');
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['source' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            Log::error('workout.import.failed', ['user_id' => $request->user()->id, 'error' => $exception->getMessage()]);

            return back()->withInput()->withErrors(['source' => 'The workout could not be parsed right now. '.($exception->getMessage() ?: 'Check your AI provider and try again.')]);
        }
    }

    public function show(WorkoutImport $workoutImport)
    {
        $this->authorizeImport($workoutImport);
        abort_if($workoutImport->isExpired() && $workoutImport->status === WorkoutImport::PREVIEW, 410, 'This import preview has expired.');

        return view('workouts.imports.show', [
            'import' => $workoutImport,
            'days' => $this->imports->preview($workoutImport, auth()->id()),
        ]);
    }

    public function confirm(WorkoutImport $workoutImport): RedirectResponse
    {
        $this->authorizeImport($workoutImport);
        abort_if($workoutImport->status !== WorkoutImport::PREVIEW || $workoutImport->isExpired(), 422, 'This import is no longer available.');

        $plan = $this->imports->confirm($workoutImport, auth()->id());

        return redirect()->route('workouts.plans.edit', $plan)->with('success', 'Workout imported. New movements were added to your exercise library.');
    }

    private function authorizeImport(WorkoutImport $import): void
    {
        abort_if($import->user_id !== auth()->id(), 403);
    }
}
