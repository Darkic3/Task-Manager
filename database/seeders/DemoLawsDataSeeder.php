<?php

namespace Database\Seeders;

use App\Models\Law;
use App\Models\LawEvent;
use App\Models\LawLink;
use App\Models\Project;
use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Models\Task;
use App\Models\User;
use App\Services\LawEventService;
use Illuminate\Database\Seeder;

/**
 * Demo data to *see* the Laws module in action.
 *
 * Creates for the first user:
 *   - 3 laws (DEMO): ongoing, time-boxed, and one linked to a demo project
 *   - 1 demo project + 2 demo tasks (one completed → auto XP via inheritance)
 *   - 1 demo build routine, completed today → auto XP
 *   - a week of manual events so timeline / dashboard / reports have content
 *
 * Run:  php artisan db:seed --class=DemoLawsDataSeeder
 * Clean: delete the "(DEMO)" laws in the UI (events are removed via cascade);
 *        then delete the "سلامتی (DEMO)" project and the demo tasks/routine.
 *
 * Safe to re-run: skips when a DEMO law already exists.
 */
class DemoLawsDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DemoLawsDataSeeder is only available in local and testing environments.');
        }

        $user = User::first();
        if (! $user) {
            $user = User::factory()->create([
                'name' => env('ADMIN_NAME', 'Ahora'),
                'username' => 'ahora_demo',
                'email' => env('ADMIN_EMAIL', 'ahora@a.com'),
                'password' => bcrypt(env('ADMIN_PASSWORD', '123123')),
            ]);
        }

        if (Law::where('user_id', $user->id)->where('title', 'like', '%(DEMO)%')->exists()) {
            $this->command->info('Demo laws already exist — skipping.');

            return;
        }

        $service = app(LawEventService::class);
        $today = today();

        // ── Law 1: ongoing, scored manually ──────────────────────────
        $noPhone = Law::create([
            'user_id' => $user->id,
            'title' => 'گوشی بعد از ۲۳ ممنوع (DEMO)',
            'description' => 'شب‌ها گوشی دستم نباشه؛ کتاب یا خواب.',
            'icon' => 'bi-phone',
            'color' => '#ef4444',
            'coefficient' => 8,
            'starts_on' => $today->copy()->subDays(6),
            'ends_on' => null,
            'status' => Law::STATUS_ACTIVE,
            'sort_order' => 1,
        ]);

        // 4 clean nights (+) and 2 slips (−) across the past week.
        foreach ([6 => 1, 5 => 1, 4 => -1, 3 => 1, 2 => 1, 1 => -1] as $ago => $dir) {
            LawEvent::create([
                'law_id' => $noPhone->id,
                'user_id' => $user->id,
                'occurred_on' => $today->copy()->subDays($ago),
                'direction' => $dir,
                'xp_delta' => $dir * $noPhone->coefficient,
                'source' => LawEvent::SOURCE_MANUAL,
                'note' => $dir === 1 ? 'شب بدون گوشی (DEMO)' : 'اسکرول تا نصفه‌شب (DEMO)',
            ]);
        }

        // ── Law 2: scored automatically through a linked project ──────
        $reading = Law::create([
            'user_id' => $user->id,
            'title' => 'هر روز ۱۰ صفحه مطالعه (DEMO)',
            'description' => 'پروژه سلامتی دمو زیرمجموعه این قانون است.',
            'icon' => 'bi-book',
            'color' => '#0ea5e9',
            'coefficient' => 5,
            'starts_on' => $today->copy()->subDays(6),
            'ends_on' => $today->copy()->addMonth(),
            'status' => Law::STATUS_ACTIVE,
            'sort_order' => 2,
        ]);

        $health = Project::create([
            'user_id' => $user->id,
            'name' => 'سلامتی (DEMO)',
            'description' => 'Demo project linked to the reading law — delete me when done exploring.',
            'status' => 'in_progress',
        ]);

        LawLink::create([
            'law_id' => $reading->id,
            'linkable_type' => Project::class,
            'linkable_id' => $health->id,
            'effect' => LawLink::EFFECT_FOR,
        ]);

        $readTask = Task::create([
            'user_id' => $user->id,
            'project_id' => $health->id,
            'title' => 'خواندن ۱۰ صفحه (DEMO)',
            'status' => 'completed',
            'priority' => 'medium',
            'completed_at' => now(),
        ]);
        Task::create([
            'user_id' => $user->id,
            'project_id' => $health->id,
            'title' => 'خلاصه یک پاراگرافی (DEMO)',
            'status' => 'to_do',
            'priority' => 'low',
        ]);
        $service->syncTaskTree($readTask);

        // Two older manual reads so the timeline is not empty.
        foreach ([5 => 1, 2 => 1] as $ago => $dir) {
            LawEvent::create([
                'law_id' => $reading->id,
                'user_id' => $user->id,
                'occurred_on' => $today->copy()->subDays($ago),
                'direction' => $dir,
                'xp_delta' => $dir * $reading->coefficient,
                'source' => LawEvent::SOURCE_MANUAL,
                'note' => 'مطالعه آزاد (DEMO)',
            ]);
        }

        // ── Law 3: scored automatically through a linked routine ───────
        $walk = Law::create([
            'user_id' => $user->id,
            'title' => 'پیاده‌روی روزانه (DEMO)',
            'description' => 'روتین پیاده‌روی صبح وصل است؛ انجامش خودکار امتیاز می‌دهد.',
            'icon' => 'bi-person-walking',
            'color' => '#10b981',
            'coefficient' => 6,
            'starts_on' => $today->copy()->subDays(6),
            'ends_on' => null,
            'status' => Law::STATUS_ACTIVE,
            'sort_order' => 3,
        ]);

        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'پیاده‌روی صبح (DEMO)',
            'frequency' => 'daily',
            'behavior_type' => 'build',
            'is_active' => true,
        ]);
        LawLink::create([
            'law_id' => $walk->id,
            'linkable_type' => Routine::class,
            'linkable_id' => $routine->id,
            'effect' => LawLink::EFFECT_FOR,
        ]);
        RoutineCompletion::create([
            'user_id' => $user->id,
            'routine_id' => $routine->id,
            'completed_date' => $today->toDateString(),
            'completed_at' => now(),
            'status' => RoutineCompletion::STATUS_DONE,
        ]);
        $service->syncRoutineOutcome($routine->fresh(), $today->copy());

        $this->command->info('Demo laws seeded: 3 laws, 1 project, 2 tasks, 1 routine, week of events.');
    }
}
