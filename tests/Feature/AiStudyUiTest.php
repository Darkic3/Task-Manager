<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiStudyUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_has_study_entry_point_with_agent_switch(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();

        // B1: study cap card + chip, both carrying data-mode="agent".
        $this->assertStringContainsString('Study structure', $html);
        $this->assertStringContainsString('Build my Konkur book structure', $html);
        $this->assertStringContainsString('data-mode="agent"', $html);
        // Ready-made first-chunk prompt is embedded for one-tap start.
        $this->assertStringContainsString('بسته اول', $html);
    }

    public function test_plan_card_has_study_hooks_and_next_chunk_button(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();

        // B2: forward-compatible hooks (rendered once the server sends them).
        $this->assertStringContainsString('totals.pages', $html);
        $this->assertStringContainsString('totals.goals', $html);
        $this->assertStringContainsString('daily_goal_value', $html);
        $this->assertStringContainsString('t.weight', $html);
        // Existing attach markers still rendered.
        $this->assertStringContainsString('موجود از قبل', $html);
        // B3: next-chunk button staged in the done state (P0: API-driven
        // continuation from the stored chain, not a prefilled prompt).
        $this->assertStringContainsString('📦 بسته بعدی', $html);
        // Stage C: the package-list approval action is staged on the card.
        $this->assertStringContainsString('تأیید فهرست بسته‌ها', $html);
        // Phase 3: conversation open rebuilds staged cards from the server,
        // with a Persian retry affordance when recovery fails.
        $this->assertStringContainsString('restoreStudyBuilds', $html);
        $this->assertStringContainsString('study-builds', $html);
        $this->assertStringContainsString('تلاش مجدد برای بازیابی', $html);
        // Phase 4: bounded waits (AbortController), Persian timeout
        // recovery, and never-stranded action buttons.
        $this->assertStringContainsString('AbortController', $html);
        $this->assertStringContainsString('مهلت درخواست تمام شد', $html);
        $this->assertStringContainsString('دوباره ارسال نکن', $html);
        // 1-Click run-all stays the primary action (expiry safety).
        $this->assertStringContainsString('ساخت و اجرای کامل پروژه', $html);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('ai.index'))->assertRedirect(route('login'));
    }
}
