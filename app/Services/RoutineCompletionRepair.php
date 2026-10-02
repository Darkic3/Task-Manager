<?php

namespace App\Services;

use App\Models\Routine;
use App\Models\RoutineCheckitemCompletion;
use App\Models\RoutineCompletion;
use App\Models\RoutineLog;
use Carbon\Carbon;

/**
 * Removes "done" records that violate the tracked-routine invariant:
 * an input-based (value/sets) routine is only done when its numbers were
 * logged. Such rows could only have been created through the bare-tick
 * path (whole-routine checkbox syncing steps with no logged values).
 *
 * Skipped / rest rows are never touched, untracked routines are never
 * touched, and value-routine steps keep checkbox semantics.
 */
class RoutineCompletionRepair
{
    /**
     * @return array{steps: int, routines: int}
     */
    public static function run(): array
    {
        $removedSteps = 0;
        $removedRoutines = 0;

        $routines = Routine::whereIn('tracking_mode', [Routine::TRACKING_VALUE, Routine::TRACKING_SETS])->get();

        foreach ($routines as $routine) {
            if ($routine->tracking_mode === Routine::TRACKING_SETS) {
                $removedSteps += self::repairSetsSteps($routine);
                $removedRoutines += self::repairSetsRoutine($routine);
            } else {
                $removedRoutines += self::repairValueRoutine($routine);
            }
        }

        return ['steps' => $removedSteps, 'routines' => $removedRoutines];
    }

    /**
     * A sets-mode step is done only with ≥1 logged set for that item+date.
     */
    private static function repairSetsSteps(Routine $routine): int
    {
        $itemIds = $routine->checklistItems()->pluck('id');
        if ($itemIds->isEmpty()) {
            return 0;
        }

        $valid = RoutineLog::where('routine_id', $routine->id)
            ->whereIn('checklist_item_id', $itemIds)
            ->get(['checklist_item_id', 'completed_date'])
            ->map(fn ($l) => (int) $l->checklist_item_id.'|'.self::key($l->completed_date))
            ->flip();

        $bad = RoutineCheckitemCompletion::whereIn('checklist_item_id', $itemIds)
            ->where('status', RoutineCheckitemCompletion::STATUS_DONE)
            ->get()
            ->filter(fn ($c) => ! isset($valid[(int) $c->checklist_item_id.'|'.self::key($c->completed_date)]));

        $n = $bad->count();
        if ($n) {
            RoutineCheckitemCompletion::whereIn('id', $bad->pluck('id'))->delete();
        }

        return $n;
    }

    /**
     * A sets-mode routine (with steps) is done only when every step carries
     * ≥1 logged set that date. Routines without steps keep checkbox semantics.
     */
    private static function repairSetsRoutine(Routine $routine): int
    {
        $itemIds = $routine->checklistItems()->pluck('id');
        if ($itemIds->isEmpty()) {
            return 0;
        }

        $logsByItemDate = RoutineLog::where('routine_id', $routine->id)
            ->whereIn('checklist_item_id', $itemIds)
            ->get(['checklist_item_id', 'completed_date'])
            ->map(fn ($l) => (int) $l->checklist_item_id.'|'.self::key($l->completed_date))
            ->flip();

        $bad = RoutineCompletion::where('routine_id', $routine->id)
            ->where('status', RoutineCompletion::STATUS_DONE)
            ->get()
            ->filter(function ($c) use ($itemIds, $logsByItemDate) {
                $key = self::key($c->completed_date);
                foreach ($itemIds as $itemId) {
                    if (! isset($logsByItemDate[(int) $itemId.'|'.$key])) {
                        return true;
                    }
                }

                return false;
            });

        $n = $bad->count();
        if ($n) {
            RoutineCompletion::whereIn('id', $bad->pluck('id'))->delete();
        }

        return $n;
    }

    /**
     * A value-mode routine is done only with a routine-level log that date.
     */
    private static function repairValueRoutine(Routine $routine): int
    {
        $validDates = RoutineLog::where('routine_id', $routine->id)
            ->whereNull('checklist_item_id')
            ->pluck('completed_date')
            ->map(fn ($d) => self::key($d))
            ->flip();

        $bad = RoutineCompletion::where('routine_id', $routine->id)
            ->where('status', RoutineCompletion::STATUS_DONE)
            ->get()
            ->filter(fn ($c) => ! isset($validDates[self::key($c->completed_date)]));

        $n = $bad->count();
        if ($n) {
            RoutineCompletion::whereIn('id', $bad->pluck('id'))->delete();
        }

        return $n;
    }

    private static function key($date): string
    {
        return $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
    }
}
