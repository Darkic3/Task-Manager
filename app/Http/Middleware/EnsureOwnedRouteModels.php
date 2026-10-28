<?php

namespace App\Http\Middleware;

use App\Models\ChecklistItem;
use App\Models\Exercise;
use App\Models\NoteAttachment;
use App\Models\NoteLink;
use App\Models\WorkoutDay;
use App\Models\WorkoutExercise;
use App\Models\WorkoutExerciseLog;
use App\Models\WorkoutRule;
use App\Models\WorkoutSetLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce account ownership for every implicitly-bound user resource.
 * Controllers still scope collection queries and validate related IDs; this
 * middleware is a fail-closed guard for direct object references (IDOR).
 */
class EnsureOwnedRouteModels
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        foreach ($request->route()->parameters() as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            if (! $this->isAccessibleTo($parameter, $userId)) {
                abort(403);
            }
        }

        return $next($request);
    }

    private function isAccessibleTo(Model $model, int $userId): bool
    {
        if (array_key_exists('user_id', $model->getAttributes())) {
            $ownerId = $model->getAttribute('user_id');

            // The shared exercise library intentionally has null ownership.
            return $ownerId === null
                ? $model instanceof Exercise
                : (int) $ownerId === $userId;
        }

        $ownerId = match (true) {
            $model instanceof ChecklistItem => $model->task?->user_id,
            $model instanceof NoteAttachment => $model->note?->user_id,
            $model instanceof NoteLink => $model->note?->user_id,
            $model instanceof WorkoutDay => $model->plan?->user_id,
            $model instanceof WorkoutExercise => $model->day?->plan?->user_id,
            $model instanceof WorkoutRule => $model->plan?->user_id,
            $model instanceof WorkoutExerciseLog => $model->session?->user_id,
            $model instanceof WorkoutSetLog => $model->exerciseLog?->session?->user_id,
            default => null,
        };

        return $ownerId !== null && (int) $ownerId === $userId;
    }
}
