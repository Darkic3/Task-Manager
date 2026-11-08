<?php

namespace App\Services\Vocabulary;

use App\Models\Reminder;
use App\Models\User;

/**
 * Daily "time to review" reminder for the Leitner box.
 *
 * One open reminder per user per day (category = Vocabulary): the scheduled
 * command refreshes its count, and grading the last due card completes it.
 * Completed reminders are never re-created — no nagging.
 */
class VocabReminderService
{
    public const CATEGORY = 'Vocabulary';

    public static function syncForUser(User $user): ?Reminder
    {
        if (! $user->vocab_remind_enabled) {
            return null;
        }

        $due = $user->vocabCards()->due()->count();
        if ($due === 0) {
            return null;
        }

        $today = today()->toDateString();
        app()->setLocale($user->locale ?? config('app.locale'));

        $existing = Reminder::where('user_id', $user->id)
            ->where('category', self::CATEGORY)
            ->whereDate('date', $today)
            ->first();

        if ($existing) {
            if ($existing->is_completed) {
                return null;
            }
            $existing->update(self::payload($due));

            return $existing->fresh();
        }

        return $user->reminders()->create(self::payload($due) + [
            'date' => $today,
            'time' => '',
            'is_recurring' => false,
            'is_completed' => false,
        ]);
    }

    public static function completeForUser(User $user): void
    {
        Reminder::where('user_id', $user->id)
            ->where('category', self::CATEGORY)
            ->whereDate('date', today()->toDateString())
            ->where('is_completed', false)
            ->get()
            ->each(fn (Reminder $r) => $r->markAsCompleted());
    }

    /**
     * Re-open today's reminder after an undone review puts a card back in the
     * queue — the day would otherwise look finished with work still due.
     */
    public static function reopenForUser(User $user): void
    {
        if (! $user->vocab_remind_enabled) {
            return;
        }

        if ($user->vocabCards()->due()->count() === 0) {
            return;
        }

        Reminder::where('user_id', $user->id)
            ->where('category', self::CATEGORY)
            ->whereDate('date', today()->toDateString())
            ->where('is_completed', true)
            ->get()
            ->each(fn (Reminder $reminder) => $reminder->update([
                'is_completed' => false,
                'completed_at' => null,
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(int $due): array
    {
        return [
            'title' => __('Review Leitner box'),
            'description' => __(':count cards due today — open the Leitner box.', ['count' => $due]),
            'priority' => $due >= 10 ? Reminder::PRIORITY_HIGH : Reminder::PRIORITY_MEDIUM,
            'category' => self::CATEGORY,
            'recurrence_type' => Reminder::RECURRENCE_NONE,
            'recurrence_interval' => 1,
        ];
    }
}
