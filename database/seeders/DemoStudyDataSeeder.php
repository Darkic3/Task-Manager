<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data to *see* the Chapters view in action.
 *
 * Creates a DEMO study hierarchy for the first user:
 *   کنکور ارشد (DEMO) → آسیب‌شناسی روانی DSM-5 (DEMO) → فصل‌ها (DEMO) → تسک‌های بخش‌ها
 *
 * Run:  php artisan db:seed --class=DemoStudyDataSeeder
 * Clean: delete the "کنکور ارشد (DEMO)" project in the UI
 *        (tasks are removed automatically via cascade).
 *
 * Safe to re-run: skips when the demo project already exists.
 */
class DemoStudyDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (! $user) {
            $user = User::factory()->create([
                'name' => env('ADMIN_NAME', 'Ahora'),
                'email' => env('ADMIN_EMAIL', 'ahora@a.com'),
                'password' => bcrypt(env('ADMIN_PASSWORD', '123123')),
            ]);
            $this->command->info('Admin user created: ahora@a.com / 123123');
        }

        if (Project::where('user_id', $user->id)->where('name', 'کنکور ارشد (DEMO)')->exists()) {
            $this->command->info('Demo data already exists — skipping.');

            return;
        }

        $konkur = Project::create([
            'user_id' => $user->id,
            'name' => 'کنکور ارشد (DEMO)',
            'description' => 'Demo parent project — delete me when done exploring.',
            'status' => 'in_progress',
        ]);

        $dsm = Project::create([
            'user_id' => $user->id,
            'parent_id' => $konkur->id,
            'name' => 'آسیب‌شناسی روانی DSM-5 (DEMO)',
            'description' => 'Demo course project.',
            'status' => 'in_progress',
        ]);

        $make = fn (array $attrs) => Task::create(array_merge([
            'user_id' => $user->id,
            'priority' => 'medium',
            'weight' => 1,
            'auto_weight' => true,
        ], $attrs));

        // ── Chapter 1: mixed progress ──────────────────────────────
        $ch1 = Project::create([
            'user_id' => $user->id,
            'parent_id' => $dsm->id,
            'name' => 'فصل ۱ — طیف اسکیزوفرنی (DEMO)',
            'status' => 'in_progress',
        ]);

        $root1 = $make([
            'project_id' => $ch1->id, 'title' => 'فصل ۱ — طیف اسکیزوفرنی و سایر اختلالات سایکوتیک',
            'status' => 'in_progress', 'priority' => 'high',
        ]);
        $make(['project_id' => $ch1->id, 'parent_id' => $root1->id, 'title' => 'اختلال هذیانی', 'status' => 'completed']);
        $make(['project_id' => $ch1->id, 'parent_id' => $root1->id, 'title' => 'اختلال سایکوتیک گذرا', 'status' => 'completed']);
        $t = $make(['project_id' => $ch1->id, 'parent_id' => $root1->id, 'title' => 'اسکیزوفرنی', 'status' => 'in_progress', 'priority' => 'high']);
        $make(['project_id' => $ch1->id, 'parent_id' => $t->id, 'title' => 'ملاک‌های تشخیصی A تا E', 'status' => 'completed']);
        $make(['project_id' => $ch1->id, 'parent_id' => $t->id, 'title' => 'تفاوت با اختلال اسکیزوافکتیو', 'status' => 'to_do']);
        $make(['project_id' => $ch1->id, 'parent_id' => $t->id, 'title' => 'درمان‌های دارویی خط اول', 'status' => 'to_do', 'priority' => 'high']);
        $make(['project_id' => $ch1->id, 'parent_id' => $root1->id, 'title' => 'اختلال اسکیزوافکتیو', 'status' => 'to_do']);
        $make(['project_id' => $ch1->id, 'parent_id' => $root1->id, 'title' => 'کاتاتونیا', 'status' => 'to_do', 'priority' => 'low']);

        // ── Chapter 2: fully done → renders collapsed by default ───
        $ch2 = Project::create([
            'user_id' => $user->id,
            'parent_id' => $dsm->id,
            'name' => 'فصل ۲ — اختلالات خلقی (DEMO)',
            'status' => 'in_progress',
        ]);

        $root2 = $make([
            'project_id' => $ch2->id, 'title' => 'فصل ۲ — اختلالات دوقطبی و افسردگی',
            'status' => 'completed',
        ]);
        $make(['project_id' => $ch2->id, 'parent_id' => $root2->id, 'title' => 'دوقطبی نوع I', 'status' => 'completed']);
        $make(['project_id' => $ch2->id, 'parent_id' => $root2->id, 'title' => 'دوقطبی نوع II', 'status' => 'completed']);
        $make(['project_id' => $ch2->id, 'parent_id' => $root2->id, 'title' => 'افسردگی اساسی', 'status' => 'completed']);

        // ── A standalone task (lands in "Other tasks") ─────────────
        $make(['project_id' => $ch1->id, 'title' => 'مرور فلش‌کارت‌های فصل ۱', 'status' => 'to_do', 'priority' => 'low']);

        $this->command->info('Demo study data created for user: ' . $user->email);
        $this->command->info('Open: /projects/' . $ch1->slug . '/tasks  then click the "Chapters" toggle.');
    }
}
