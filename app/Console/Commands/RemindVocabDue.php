<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Vocabulary\VocabReminderService;
use Illuminate\Console\Command;

class RemindVocabDue extends Command
{
    protected $signature = 'vocab:remind-due';

    protected $description = 'Create or refresh the daily Leitner review reminder for users with due cards.';

    public function handle(): int
    {
        $synced = 0;

        User::where('vocab_remind_enabled', true)
            ->whereHas('vocabCards', fn ($q) => $q->due())
            ->chunkById(200, function ($users) use (&$synced) {
            foreach ($users as $user) {
                if (VocabReminderService::syncForUser($user)) {
                    $synced++;
                }
            }
        });

        $this->info("Vocab reminders synced for {$synced} user(s).");

        return self::SUCCESS;
    }
}
