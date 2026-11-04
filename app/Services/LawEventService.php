<?php

namespace App\Services;

use App\Models\DayLog;
use App\Models\Law;
use App\Models\LawEvent;
use App\Models\LawLink;
use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Models\RoutineViolation;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for manual law events coming from free DayLog
 * activities: one entry links to at most one law, and the direction is
 * chosen at log time (unlike static task/routine links, whose direction
 * is preset on the link itself).
 *
 * XP is frozen on the event (direction × law coefficient at log time),
 * so later coefficient edits never rewrite history.
 */
class LawEventService
{
    /**
     * Fail-fast guard shared by store/update: the law exists, belongs to
     * the user, is live, and the direction is present and valid.
     * Returns null when no law was requested.
     *
     * @throws ValidationException
     */
    public function resolveLaw(int $userId, ?int $lawId, ?int $direction): ?Law
    {
        if ($lawId === null) {
            return null;
        }

        $law = Law::find($lawId);

        if (! $law || (int) $law->user_id !== $userId) {
            throw ValidationException::withMessages(['law_id' => __('Law not found.')]);
        }

        if (! $law->acceptsEvents()) {
            throw ValidationException::withMessages(['law_id' => __('This law is paused, archived or expired.')]);
        }

        if (! in_array($direction, [LawLink::EFFECT_FOR, LawLink::EFFECT_AGAINST], true)) {
            throw ValidationException::withMessages([
                'law_direction' => __('Choose whether this counts for or against the law.'),
            ]);
        }

        return $law;
    }

    /**
     * (Re)sync the manual event of a DayLog entry.
     *
     * @throws ValidationException
     */
    public function syncForDayLog(DayLog $log, ?int $lawId, ?int $direction): ?LawEvent
    {
        $this->removeForDayLog($log);

        $law = $this->resolveLaw((int) $log->user_id, $lawId, $direction);

        if ($law === null) {
            $log->forceFill(['law_id' => null, 'law_direction' => null])->save();

            return null;
        }

        $event = LawEvent::create([
            'law_id' => $law->id,
            'user_id' => $log->user_id,
            'occurred_on' => $log->date->toDateString(),
            'direction' => $direction,
            'xp_delta' => $direction * (int) $law->coefficient,
            'source' => LawEvent::SOURCE_MANUAL,
            'linkable_type' => DayLog::class,
            'linkable_id' => $log->id,
            'note' => mb_substr(trim((string) $log->title), 0, 255) ?: null,
        ]);

        $log->forceFill(['law_id' => $law->id, 'law_direction' => $direction])->save();

        return $event;
    }

    /**
     * Drop every manual event tied to the entry (unlink, delete, merge).
     */
    public function removeForDayLog(DayLog $log): void
    {
        LawEvent::where('linkable_type', DayLog::class)
            ->where('linkable_id', $log->id)
            ->delete();
    }

    /**
     * Guard used by controllers: the current user owns the law.
     */
    public function ownedLaw(int $lawId): Law
    {
        $law = Law::find($lawId);

        abort_if(! $law || (int) $law->user_id !== (int) Auth::id(), 422, __('Law not found.'));

        return $law;
    }

    // ── Automatic outcomes (phase 4) ──────────────────────────────
    //
    // A link's direction describes DOING the subject: completing a linked
    // task / build routine scores the link's effect, failing or skipping it
    // scores the opposite. Avoid routines only ever slip (the slip IS the
    // occurrence), so each slip scores the link's effect directly.
    // Undoing an outcome retracts its events. XP stays frozen per event.

    /**
     * Recompute a task's automatic events from its current state.
     * Completed → effect; failed (and not completed) → opposite; anything
     * else → no auto events. All-dates scope: a task holds one outcome.
     */
    public function syncTaskOutcome(Task $task): void
    {
        $this->clearAutoEvents(Task::class, (int) $task->id);

        $polarity = null;
        $date = today();
        if ($task->status === 'completed') {
            $polarity = 1;
            if ($task->completed_at) {
                $date = Carbon::parse($task->completed_at)->startOfDay();
            }
        } elseif ($task->failed_at !== null) {
            $polarity = -1;
            $date = Carbon::parse($task->failed_at)->startOfDay();
        }

        if ($polarity === null) {
            return;
        }

        foreach (app(LawLinkService::class)->liveLawsForTask($task) as $row) {
            $this->recordAuto($row['law'], $polarity * $row['effect'], Task::class, (int) $task->id, $date, $task->title);
        }
    }

    /**
     * Drop every automatic event of a task (used on delete).
     */
    public function removeTaskEvents(Task $task): void
    {
        $this->clearAutoEvents(Task::class, (int) $task->id);
    }

    /**
     * Recompute a task plus every ancestor above it. reopenCompletedAncestors
     * can flip completed parents back to open in several flows (uncomplete,
     * reparent, edit); syncing the chain keeps their law scores truthful.
     * Completing a task never changes ancestors, and re-syncing an unchanged
     * ancestor is a harmless delete + identical recreate.
     */
    public function syncTaskTree(Task $task): void
    {
        $current = $task->fresh();
        $guard = 0;
        while ($current && $guard++ < 100) {
            $this->syncTaskOutcome($current);
            $current = $current->parent;
        }
    }

    /**
     * Recompute a build routine's automatic events for one date.
     * Done → effect; skipped (sanctioned postpones excluded) → opposite;
     * otherwise no auto events. Avoid routines never score here — their
     * slips are recorded per-violation via recordSlip().
     */
    public function syncRoutineOutcome(Routine $routine, Carbon $date): void
    {
        $this->clearAutoEvents(Routine::class, (int) $routine->id, $date->toDateString());

        if ($routine->isAvoid()) {
            return;
        }

        $polarity = null;
        if ($routine->completedOn($date)) {
            $polarity = 1;
        } else {
            $skip = $routine->completions()
                ->where('user_id', $routine->user_id)
                ->where('completed_date', $date->toDateString())
                ->where('status', RoutineCompletion::STATUS_SKIPPED)
                ->first();
            if ($skip && ($skip->skip_reason ?? null) !== 'postponed') {
                $polarity = -1;
            }
        }

        if ($polarity === null) {
            return;
        }

        foreach (app(LawLinkService::class)->liveLawsForRoutine($routine) as $row) {
            $this->recordAuto($row['law'], $polarity * $row['effect'], Routine::class, (int) $routine->id, $date, $routine->title);
        }
    }

    /**
     * Score one avoid-routine slip (routine-level or step-level).
     * Idempotent per violation: re-logging paths that return an existing
     * violation must not double-score.
     */
    public function recordSlip(RoutineViolation $violation): void
    {
        $exists = LawEvent::where('linkable_type', RoutineViolation::class)
            ->where('linkable_id', $violation->id)
            ->where('source', LawEvent::SOURCE_AUTO)
            ->exists();

        if ($exists) {
            return;
        }

        $routine = $violation->routine;
        if (! $routine) {
            return;
        }

        $date = $violation->occurred_date instanceof Carbon
            ? $violation->occurred_date->copy()->startOfDay()
            : Carbon::parse($violation->occurred_date)->startOfDay();

        foreach (app(LawLinkService::class)->liveLawsForRoutine($routine) as $row) {
            $this->recordAuto(
                $row['law'],
                $row['effect'],
                RoutineViolation::class,
                (int) $violation->id,
                $date,
                $violation->note ?: $routine->title,
            );
        }
    }

    /**
     * Retract a slip's events (undo window).
     */
    public function removeSlipEvents(RoutineViolation $violation): void
    {
        LawEvent::where('linkable_type', RoutineViolation::class)
            ->where('linkable_id', $violation->id)
            ->where('source', LawEvent::SOURCE_AUTO)
            ->delete();
    }

    /**
     * Delete automatic events of an entity, optionally scoped to one date.
     */
    public function clearAutoEvents(string $type, int $id, ?string $date = null): void
    {
        LawEvent::where('linkable_type', $type)
            ->where('linkable_id', $id)
            ->where('source', LawEvent::SOURCE_AUTO)
            ->when($date !== null, fn ($q) => $q->where('occurred_on', $date))
            ->delete();
    }

    private function recordAuto(Law $law, int $direction, string $type, int $id, Carbon $date, ?string $title): void
    {
        LawEvent::create([
            'law_id' => $law->id,
            'user_id' => $law->user_id,
            'occurred_on' => $date->toDateString(),
            'direction' => $direction,
            'xp_delta' => $direction * (int) $law->coefficient,
            'source' => LawEvent::SOURCE_AUTO,
            'linkable_type' => $type,
            'linkable_id' => $id,
            'note' => $title !== null ? mb_substr(trim((string) $title), 0, 255) ?: null : null,
        ]);
    }
}
