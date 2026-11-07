<?php

namespace App\Http\Controllers;

use App\Services\Onboarding\OnboardingQuestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function selectPriorities(Request $request, OnboardingQuestService $quests)
    {
        $data = $request->validate([
            'tracks' => ['required', 'array', 'min:1', 'max:4'],
            'tracks.*' => ['in:do,study,habit,fitness'],
        ]);

        // Keep the user's click order (first = highest priority).
        $ordered = array_values(array_unique($data['tracks']));
        $quests->setPriorities(Auth::user(), $ordered);

        return redirect()->route('dashboard')->with('success', __('Your journey is ready — start with the highlighted quest! ✨'));
    }

    public function seedTemplate(Request $request, OnboardingQuestService $quests)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:task,routine,project,workout,note'],
        ]);

        $user = Auth::user();
        $kind = $data['kind'];
        $created = $this->seed($user, $kind);
        if ($created) {
            $quests->markSeeded($user, $kind);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'created' => (bool) $created]);
        }

        return back()->with('success', __('Sample created — now make it yours! ✨'));
    }

    public function completeStep(Request $request, OnboardingQuestService $quests)
    {
        $data = $request->validate([
            'module' => ['required', 'string', 'max:40'],
            'step' => ['required', 'string', 'max:60'],
        ]);

        $quests->markStep(Auth::user(), $data['module'], $data['step']);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    /**
     * One-click starter content so empty states feel alive instantly.
     * Idempotent: returns false when the user already has real data.
     */
    protected function seed($user, string $kind): bool
    {
        return match ($kind) {
            'task' => $this->seedTask($user),
            'routine' => $this->seedRoutine($user),
            'project' => $this->seedProject($user),
            'note' => $this->seedNote($user),
            'workout' => $this->seedWorkout($user),
            default => false,
        };
    }

    protected function seedTask($user): bool
    {
        if ($user->tasks()->exists()) {
            return false;
        }
        $task = $user->tasks()->create([
            'title' => __('Plan my day in 10 minutes'),
            'description' => __('Sample quest task — edit me or tick me off!'),
            'priority' => 'high',
            'status' => 'to_do',
            'due_date' => today()->toDateString(),
        ]);
        $task->checklistItems()->create(['name' => __('Write down 3 priorities')]);
        $task->checklistItems()->create(['name' => __('Pick today’s Next Up')]);

        return true;
    }

    protected function seedRoutine($user): bool
    {
        if ($user->routines()->exists()) {
            return false;
        }
        $user->routines()->create([
            'title' => __('Wake up at 7:00'),
            'frequency' => 'daily',
            'behavior_type' => \App\Models\Routine::BEHAVIOR_BUILD,
            'tracking_mode' => \App\Models\Routine::TRACKING_NONE,
            'is_active' => true,
        ]);

        return true;
    }

    protected function seedProject($user): bool
    {
        if ($user->projects()->exists()) {
            return false;
        }
        $project = $user->projects()->create([
            'name' => __('My big goal'),
            'slug' => 'my-big-goal-'.$user->id.'-'.(time() % 100000),
            'type' => 'project',
            'status' => 'in_progress',
            'daily_goal_value' => 3,
            'daily_goal_unit' => 'tasks',
        ]);
        \App\Models\Task::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => __('Break this goal into 3 tasks'),
            'priority' => 'medium',
            'status' => 'to_do',
        ]);

        return true;
    }

    protected function seedNote($user): bool
    {
        if ($user->notes()->exists()) {
            return false;
        }
        $user->notes()->create([
            'title' => __('My first captured idea'),
            'content' => __('Sample note — replace me with anything on your mind.'),
            'category' => 'general',
        ]);

        return true;
    }

    protected function seedWorkout($user): bool
    {
        if ($user->workoutPlans()->exists()) {
            return false;
        }
        $plan = $user->workoutPlans()->create([
            'title' => __('Starter 3-day plan'),
            'goal' => __('Learn the workout loop'),
            'status' => 'active',
            'start_date' => today()->toDateString(),
        ]);
        $exercise = $user->exercises()->firstOrCreate(
            ['name' => __('Push-up')],
            ['category' => 'strength', 'muscle_groups' => ['chest'], 'equipment' => ['bodyweight']]
        );
        foreach (['saturday', 'monday', 'wednesday'] as $i => $day) {
            $wd = $plan->days()->create([
                'weekday' => $day,
                'title' => __('Training').' '.($i + 1),
                'type' => 'training',
                'sort_order' => $i,
            ]);
            $wd->exercises()->create([
                'exercise_id' => $exercise->id,
                'target_sets' => 3,
                'rep_min' => 8,
                'rep_max' => 10,
                'sort_order' => 0,
            ]);
        }

        return true;
    }
}
