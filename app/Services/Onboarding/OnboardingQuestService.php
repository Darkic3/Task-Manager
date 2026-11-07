<?php

namespace App\Services\Onboarding;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Game-like onboarding engine (Phase 2: MVP discovery).
 *
 * - 4 player tracks picked at first entry (order = priority).
 * - Each MVP module has 1–4 small steps detected automatically from DB
 *   (plus optional manual completion stored in onboarding_state).
 * - Sidebar + dashboard use visual states: undiscovered (dim) /
 *   active quest (highlight) / in_progress / mastered (full color).
 */
final class OnboardingQuestService
{
    public const TRACKS = [
        'do' => [
            'icon' => 'bi-lightning-charge-fill',
            'color' => '#f59e0b',
            'modules' => ['planner', 'tasks', 'time_tracking'],
        ],
        'study' => [
            'icon' => 'bi-book-half',
            'color' => '#4f46e5',
            'modules' => ['projects', 'tasks', 'planner'],
        ],
        'habit' => [
            'icon' => 'bi-fire',
            'color' => '#ef4444',
            'modules' => ['routines', 'planner', 'track'],
        ],
        'fitness' => [
            'icon' => 'bi-heart-pulse-fill',
            'color' => '#16a34a',
            'modules' => ['workouts', 'planner', 'reports'],
        ],
    ];

    public const TRACK_ORDER = ['do', 'study', 'habit', 'fitness'];

    /** Default module discovery order when user has no priorities yet. */
    public const DEFAULT_MODULE_ORDER = [
        'planner', 'tasks', 'projects', 'routines',
        'workouts', 'time_tracking', 'notes', 'track', 'reports',
    ];

    public const MODULE_META = [
        'planner' => ['icon' => 'bi-sun-fill', 'route' => 'planner.index'],
        'tasks' => ['icon' => 'bi-check2-square', 'route' => 'tasks.index'],
        'projects' => ['icon' => 'bi-folder-fill', 'route' => 'projects.index'],
        'routines' => ['icon' => 'bi-arrow-repeat', 'route' => 'routines.index'],
        'workouts' => ['icon' => 'bi-heart-pulse-fill', 'route' => 'workouts.plans.index'],
        'track' => ['icon' => 'bi-graph-up-arrow', 'route' => 'track.index'],
        'time_tracking' => ['icon' => 'bi-stopwatch', 'route' => 'time.reports'],
        'notes' => ['icon' => 'bi-journal-text', 'route' => 'notes.index'],
        'reports' => ['icon' => 'bi-bar-chart-fill', 'route' => 'reports.overview'],
    ];

    /** @var array<int, array> per-request cache keyed by user id */
    protected static array $memo = [];

    public function getState(User $user): array
    {
        $raw = $user->onboarding_state ?? [];
        if (! is_array($raw)) {
            $raw = [];
        }

        return array_merge([
            'priorities' => [],
            'ordered_modules' => [],
            'completed_steps' => [],
            'seeded_templates' => [],
            'dismissed' => [],
        ], $raw);
    }

    public function saveState(User $user, array $state): void
    {
        $user->onboarding_state = $state;
        $user->save();
        unset(static::$memo[$user->id]);
    }

    /** Ordered module keys based on user's track priorities. */
    public function orderedModules(User $user): array
    {
        $state = $this->getState($user);
        if (! empty($state['ordered_modules'])) {
            return array_values(array_intersect($state['ordered_modules'], array_keys(self::MODULE_META)));
        }

        $priorities = array_values(array_intersect($state['priorities'] ?? [], self::TRACK_ORDER));
        if (empty($priorities)) {
            return self::DEFAULT_MODULE_ORDER;
        }

        $ordered = [];
        foreach ($priorities as $track) {
            foreach (self::TRACKS[$track]['modules'] as $mod) {
                if (! in_array($mod, $ordered, true)) {
                    $ordered[] = $mod;
                }
            }
        }
        foreach (self::DEFAULT_MODULE_ORDER as $mod) {
            if (! in_array($mod, $ordered, true)) {
                $ordered[] = $mod;
            }
        }

        return $ordered;
    }

    public function setPriorities(User $user, array $tracks): array
    {
        $tracks = array_values(array_intersect($tracks, self::TRACK_ORDER));
        $state = $this->getState($user);
        $state['priorities'] = $tracks;

        $ordered = [];
        foreach ($tracks as $track) {
            foreach (self::TRACKS[$track]['modules'] as $mod) {
                if (! in_array($mod, $ordered, true)) {
                    $ordered[] = $mod;
                }
            }
        }
        foreach (self::DEFAULT_MODULE_ORDER as $mod) {
            if (! in_array($mod, $ordered, true)) {
                $ordered[] = $mod;
            }
        }
        $state['ordered_modules'] = $ordered;
        $this->saveState($user, $state);

        return $ordered;
    }

    public function markStep(User $user, string $module, string $step): void
    {
        $state = $this->getState($user);
        $state['completed_steps'][$module] = array_values(array_unique(array_merge(
            $state['completed_steps'][$module] ?? [],
            [$step]
        )));
        $this->saveState($user, $state);
    }

    public function markSeeded(User $user, string $kind): void
    {
        $state = $this->getState($user);
        $state['seeded_templates'] = array_values(array_unique(array_merge(
            $state['seeded_templates'] ?? [],
            [$kind]
        )));
        $this->saveState($user, $state);
    }

    public function isSeeded(User $user, string $kind): bool
    {
        return in_array($kind, $this->getState($user)['seeded_templates'] ?? [], true);
    }

    /**
     * Full progress snapshot. Cheap: ~10 exists()/count() queries, memoized.
     *
     * @return array{needs_priorities:bool, ordered:array, active:string, total_done:int, total_steps:int, percent:int, modules:array}
     */
    public function progressForUser(User $user): array
    {
        if (isset(static::$memo[$user->id])) {
            return static::$memo[$user->id];
        }

        $state = $this->getState($user);
        $manual = $state['completed_steps'] ?? [];
        $today = today()->toDateString();

        $hasOnboardingColumn = true;
        try {
            $hasOnboardingColumn = Schema::hasColumn('users', 'onboarding_state');
        } catch (\Throwable $e) {
            $hasOnboardingColumn = true;
        }

        // ── Auto-detection (real DB signals) ──
        $taskCount = $user->tasks()->count();
        $hasTask = $taskCount > 0;
        $hasCompletedTask = $user->tasks()->where('status', 'completed')->exists();
        $hasTaskToday = $user->tasks()->whereDate('due_date', $today)->exists();
        $hasChecklist = \App\Models\ChecklistItem::whereHas('task', fn ($q) => $q->where('user_id', $user->id))->exists();
        $hasProject = $user->projects()->exists();
        $hasProjectGoal = $user->projects()->whereNotNull('daily_goal_value')->where('daily_goal_value', '>', 0)->exists();
        $hasProjectChild = \App\Models\Project::where('user_id', $user->id)->whereNotNull('parent_id')->exists();
        $hasRoutine = $user->routines()->exists();
        $hasRoutineCompletion = \App\Models\RoutineCompletion::where('user_id', $user->id)->exists();
        $hasDayLog = $user->dayLogs()->exists();
        $hasTimeEntry = $user->timeEntries()->exists();
        $hasWorkoutPlan = $user->workoutPlans()->exists();
        $hasWorkoutSession = $user->workoutSessions()->exists();
        $hasNote = $user->notes()->exists();
        $hasNotebook = $user->notebooks()->exists();

        $auto = [
            'planner' => [
                'add_today' => $hasTaskToday || $hasDayLog,
                'complete_item' => $hasCompletedTask || $hasRoutineCompletion,
                'nextup_focus' => $hasTaskToday || $hasTimeEntry,
                'daylog_mood' => $hasDayLog,
            ],
            'tasks' => [
                'create_first' => $hasTask,
                'add_checklist' => $hasChecklist,
                'send_to_day' => $hasTaskToday,
            ],
            'projects' => [
                'create_first' => $hasProject,
                'create_sub' => $hasProjectChild,
                'set_goal' => $hasProjectGoal,
            ],
            'routines' => [
                'create_first' => $hasRoutine,
                'log_today' => $hasRoutineCompletion,
                'view_stats' => $hasRoutine && ($hasRoutineCompletion || $hasDayLog),
            ],
            'track' => [
                'open_track' => $hasRoutineCompletion || $hasRoutine,
            ],
            'workouts' => [
                'create_plan' => $hasWorkoutPlan,
                'log_session' => $hasWorkoutSession,
            ],
            'time_tracking' => [
                'start_timer' => $hasTimeEntry,
                'view_report' => $hasTimeEntry,
            ],
            'notes' => [
                'quick_capture' => $hasNote,
                'make_notebook' => $hasNotebook,
            ],
            'reports' => [
                'open_reports' => $hasCompletedTask || $hasTimeEntry || $hasWorkoutSession || $hasRoutineCompletion,
            ],
        ];

        $ordered = $this->orderedModules($user);
        $modules = [];
        $totalDone = 0;
        $totalSteps = 0;

        foreach (self::MODULE_META as $mod => $meta) {
            $steps = $this->stepDefinitions($mod);
            $doneSteps = [];
            foreach ($steps as $s) {
                $key = $s['key'];
                $done = (bool) ($auto[$mod][$key] ?? false) || in_array($key, $manual[$mod] ?? [], true);
                if ($done) {
                    $doneSteps[] = $key;
                }
            }
            $total = count($steps);
            $done = count($doneSteps);
            $totalDone += $done;
            $totalSteps += $total;
            $modules[$mod] = [
                'meta' => $meta,
                'steps' => $steps,
                'done_steps' => $doneSteps,
                'done' => $done,
                'total' => $total,
                'percent' => $total ? (int) round($done / $total * 100) : 100,
                'mastered' => $done >= $total,
            ];
        }

        // Active quest = first non-mastered module in priority order.
        $active = 'planner';
        foreach ($ordered as $mod) {
            if (empty($modules[$mod]['mastered'])) {
                $active = $mod;
                break;
            }
        }
        if (! empty($modules[$active]['mastered'])) {
            $active = $ordered[0] ?? 'planner';
        }

        // Visual state per module for sidebar/journey UI.
        foreach ($modules as $mod => &$info) {
            if ($info['mastered']) {
                $info['visual'] = 'mastered';
            } elseif ($mod === $active) {
                $info['visual'] = 'active';
            } elseif ($info['done'] > 0) {
                $info['visual'] = 'in_progress';
            } else {
                $info['visual'] = 'undiscovered';
            }
        }
        unset($info);

        $snapshot = [
            'needs_priorities' => empty($state['priorities']) && $hasOnboardingColumn,
            'ordered' => $ordered,
            'active' => $active,
            'total_done' => $totalDone,
            'total_steps' => max($totalSteps, 1),
            'percent' => $totalSteps ? (int) round($totalDone / $totalSteps * 100) : 0,
            'modules' => $modules,
            'state' => $state,
        ];

        return static::$memo[$user->id] = $snapshot;
    }

    /** Step definitions with UI copy (fa/en via __()). */
    public function stepDefinitions(string $module): array
    {
        return match ($module) {
            'planner' => [
                ['key' => 'add_today', 'label' => __('Add your first task to today'), 'desc' => __('Pull a task into today or create one in 10 seconds.'), 'cta' => __('Open My Day'), 'route' => 'planner.index'],
                ['key' => 'complete_item', 'label' => __('Complete one task or routine'), 'desc' => __('Tick something off and watch the day bar move.'), 'cta' => __('Open My Day'), 'route' => 'planner.index'],
                ['key' => 'nextup_focus', 'label' => __('Use Next Up to focus'), 'desc' => __('Let the hero box guide your single most important task.'), 'cta' => __('Open My Day'), 'route' => 'planner.index'],
                ['key' => 'daylog_mood', 'label' => __('Log your first mood or activity'), 'desc' => __('One line in the timeline is enough to start.'), 'cta' => __('Open My Day'), 'route' => 'planner.index'],
            ],
            'tasks' => [
                ['key' => 'create_first', 'label' => __('Create your first task'), 'desc' => __('Just a title + Enter — details can wait.'), 'cta' => __('Open Tasks'), 'route' => 'tasks.index'],
                ['key' => 'add_checklist', 'label' => __('Break it into a checklist'), 'desc' => __('Open the task drawer and add one small step.'), 'cta' => __('Open Tasks'), 'route' => 'tasks.index'],
                ['key' => 'send_to_day', 'label' => __('Send a task to My Day'), 'desc' => __('Use ☀️ to schedule it for today.'), 'cta' => __('Open Tasks'), 'route' => 'tasks.index'],
            ],
            'projects' => [
                ['key' => 'create_first', 'label' => __('Create your first project'), 'desc' => __('Or spawn the sample project with 1 click.'), 'cta' => __('Open Projects'), 'route' => 'projects.index'],
                ['key' => 'create_sub', 'label' => __('Add a chapter or sub-project'), 'desc' => __('Open the structure tree and add one node.'), 'cta' => __('Open Projects'), 'route' => 'projects.index'],
                ['key' => 'set_goal', 'label' => __('Set a daily goal'), 'desc' => __('e.g. 20 pages or 60 minutes per day.'), 'cta' => __('Open Projects'), 'route' => 'projects.index'],
            ],
            'routines' => [
                ['key' => 'create_first', 'label' => __('Create your first routine'), 'desc' => __('Pick a type card — build, measurable or avoid.'), 'cta' => __('Open Routines'), 'route' => 'routines.index'],
                ['key' => 'log_today', 'label' => __('Check in today'), 'desc' => __('Tick the routine or log its value once.'), 'cta' => __('Open My Day'), 'route' => 'planner.index'],
                ['key' => 'view_stats', 'label' => __('See your streak & stats'), 'desc' => __('Open a routine and peek at its stats tab.'), 'cta' => __('Open Routines'), 'route' => 'routines.index'],
            ],
            'track' => [
                ['key' => 'open_track', 'label' => __('Open Track once'), 'desc' => __('See build / measurable / avoid in one wall.'), 'cta' => __('Open Track'), 'route' => 'track.index'],
            ],
            'workouts' => [
                ['key' => 'create_plan', 'label' => __('Create a training plan'), 'desc' => __('Or spawn the 3-day sample plan with 1 click.'), 'cta' => __('Open Workouts'), 'route' => 'workouts.plans.index'],
                ['key' => 'log_session', 'label' => __('Finish your first session'), 'desc' => __('Start today’s workout and log one set.'), 'cta' => __('Open Workouts'), 'route' => 'workouts.plans.index'],
            ],
            'time_tracking' => [
                ['key' => 'start_timer', 'label' => __('Start your first timer'), 'desc' => __('Hit ▶️ on any task or the floating widget.'), 'cta' => __('Open Time'), 'route' => 'time.reports'],
                ['key' => 'view_report', 'label' => __('See where time went'), 'desc' => __('Stop the timer and open Time reports.'), 'cta' => __('Open Time'), 'route' => 'time.reports'],
            ],
            'notes' => [
                ['key' => 'quick_capture', 'label' => __('Capture your first note'), 'desc' => __('Use the quick box at the top of Notes.'), 'cta' => __('Open Notes'), 'route' => 'notes.index'],
                ['key' => 'make_notebook', 'label' => __('Make a notebook'), 'desc' => __('Group notes so they stay findable.'), 'cta' => __('Open Notes'), 'route' => 'notes.index'],
            ],
            'reports' => [
                ['key' => 'open_reports', 'label' => __('Open Reports'), 'desc' => __('Your analytics light up as you do the work.'), 'cta' => __('Open Reports'), 'route' => 'reports.overview'],
            ],
            default => [],
        };
    }

    public static function flushMemo(): void
    {
        static::$memo = [];
    }
}
