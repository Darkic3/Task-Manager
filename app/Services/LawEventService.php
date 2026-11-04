<?php

namespace App\Services;

use App\Models\DayLog;
use App\Models\Law;
use App\Models\LawEvent;
use App\Models\LawLink;
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
}
