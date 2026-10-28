<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetAdministrator extends Command
{
    protected $signature = 'task-manager:admin {username : Existing account username} {--revoke : Remove administrator access}';

    protected $description = 'Grant or revoke administrator access for an existing account';

    public function handle(): int
    {
        $user = User::query()->where('username', $this->argument('username'))->first();

        if (! $user) {
            $this->components->error('No account exists with that username.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');

        if (! $grant && $user->is_admin && User::query()->where('is_admin', true)->count() <= 1) {
            $this->components->error('At least one administrator must remain.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => $grant])->save();

        $this->components->info($grant
            ? "Administrator access granted to @{$user->username}."
            : "Administrator access revoked from @{$user->username}.");

        return self::SUCCESS;
    }
}
