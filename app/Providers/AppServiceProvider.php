<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // ── Onboarding sidebar states (Phase 2): dim undiscovered,
        // highlight the active quest, full color when mastered.
        View::composer('layouts.app', function ($view) {
            $visual = [];
            $active = 'planner';
            try {
                $user = auth()->user();
                if ($user && Schema::hasColumn('users', 'onboarding_state')) {
                    $snap = app(\App\Services\Onboarding\OnboardingQuestService::class)->progressForUser($user);
                    $active = $snap['active'];
                    foreach ($snap['modules'] as $mod => $info) {
                        $visual[$mod] = $info['visual'];
                    }
                }
            } catch (\Throwable $e) {
                // Never break the shell for onboarding.
            }
            $view->with('onbVisual', $visual)->with('onbActive', $active);
        });

        // Safety guard: destructive DB commands (fresh/refresh/wipe/reset)
        // are REFUSED when the resolved database is the main dev database
        // (`task`). Real data was wiped before because `--env=testing` had
        // no .env.testing file and silently fell back to the main DB.
        // Tests and --env=testing resolve to `task_test` and stay allowed.
        // Escape hatch (explicit only): ALLOW_DESTRUCTIVE_DB=true.
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            static $destructive = ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'];

            if (! in_array($event->command, $destructive, true)) {
                return;
            }
            $flag = env('ALLOW_DESTRUCTIVE_DB', false);
            if ($flag === true || $flag === 'true' || $flag === 1 || $flag === '1') {
                return;
            }
            try {
                $db = DB::connection()->getDatabaseName();
            } catch (\Throwable $e) {
                return; // DB unreachable — nothing to protect.
            }
            if ($db === 'task') {
                throw new \RuntimeException(
                    "Refused: '{$event->command}' targets the main database `task` with real data. "
                    .'Use --env=testing (resolves to `task_test`) or set ALLOW_DESTRUCTIVE_DB=true explicitly.'
                );
            }
        });
    }
}
