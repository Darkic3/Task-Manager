<?php

namespace App\Support;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Http\Request;

class FeatureAvailability
{
    public function isEnabled(string $feature, ?User $user = null): bool
    {
        if ($user?->is_admin) {
            return true;
        }

        return $this->published($feature);
    }

    /** Current publication state, without the administrator preview override. */
    public function published(string $feature): bool
    {
        if (! array_key_exists($feature, config('modules', []))) {
            return false;
        }

        $stored = FeatureFlag::query()->whereKey($feature)->value('enabled');
        if ($stored !== null) {
            return (bool) $stored;
        }

        return (bool) config(
            "beta.features.{$feature}",
            config("modules.{$feature}.default", false),
        );
    }

    /** Resolve the module which owns a URL; null means always available. */
    public function featureForRequest(Request $request): ?string
    {
        $routeName = $request->route()?->getName();
        if ($routeName && (
            str_starts_with($routeName, 'ai.')
            || str_starts_with($routeName, 'notes.ai.')
            || str_starts_with($routeName, 'notes.collections.')
            || in_array($routeName, ['planner.ai-optimize', 'note-subjects.summarize'], true)
        )) {
            return 'ai';
        }

        $path = trim($request->path(), '/');
        $first = explode('/', $path, 2)[0] ?? '';

        return match ($first) {
            'projects' => 'projects',
            'tasks', 'checklist-items' => 'tasks',
            'planner', 'day-logs' => 'planner',
            'notes', 'notebooks', 'note-labels', 'note-subjects' => 'notes',
            'routines' => 'routines',
            'workouts' => 'workouts',
            'track' => 'track',
            'time' => 'time_tracking',
            'reports' => 'reports',
            'reminders', 'reminders-calendar' => 'reminders',
            'files' => 'files',
            'ai' => 'ai',
            default => null,
        };
    }

    /** @return array<string, array{label:string, description:string, default:bool, published:bool}> */
    public function catalog(): array
    {
        $catalog = [];

        foreach (config('modules', []) as $key => $module) {
            $catalog[$key] = [
                'label' => $module['label'],
                'description' => $module['description'],
                'default' => (bool) config("beta.features.{$key}", $module['default']),
                'published' => $this->published($key),
            ];
        }

        return $catalog;
    }
}
