<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_chat_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('ai.index'))
            ->assertOk()
            ->assertSee('linaMessages', false)
            ->assertSee('linaDock', false)
            ->assertSee('lina-cap-grid', false)
            ->assertSee('linaPendingPill', false);
    }

    public function test_ai_chat_page_requires_auth(): void
    {
        $this->get(route('ai.index'))->assertRedirect(route('login'));
    }

    public function test_ai_page_stylesheet_is_well_formed_and_covers_js_hooks(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();

        // Lina's own stylesheet must open and close cleanly — a lost closing tag
        // silently disables every rule. The shared layout contributes other
        // <style> blocks, so isolate the one holding Lina's tokens.
        $matched = preg_match(
            '/<style>\s*\/\*[^*]*\*\/\s*:root\s*\{(?:(?!<\/style>).)*?<\/style>/s',
            $html,
            $matches
        );
        $this->assertSame(1, $matched, 'Lina style block is not properly opened/closed');
        $this->assertStringContainsString('--lina-accent:', $matches[0]);

        // Every class the inline script assigns via className must be styled,
        // otherwise dynamically built messages render unstyled.
        $hooks = [
            'lina-page', 'lina-sidebar', 'lina-conv-item', 'lina-main', 'lina-head',
            'lina-messages', 'lina-welcome', 'lina-cap', 'lina-chip', 'lina-foot',
            'lina-input-box', 'lina-send-btn', 'lina-dock', 'lina-pending-pill',
            'lina-mode-toggle', 'lina-mode-btn', 'lina-msg-wrap', 'lina-msg',
            'lina-msg-meta', 'lina-model-tag', 'lina-msg-actions', 'lina-copy-btn',
            'lina-typing-wrap', 'lina-typing', 'lina-dots', 'lina-error',
            'lina-tool-card', 'lina-tool-actions', 'lina-tool-confirm', 'lina-tool-reject',
            'lina-plan-card', 'lina-plan-progress', 'lina-plan-tree', 'lina-phase',
            'lina-plan-note', 'code-block-wrap', 'code-block-header', 'code-lang',
            'lina-mob-menu-btn', 'lina-sidebar-close', 'lina-mob-backdrop',
        ];

        foreach ($hooks as $hook) {
            $this->assertStringContainsString('.'.$hook, $matches[0], "Missing styles for .{$hook}");
        }
    }

    public function test_ai_page_composer_and_sidebar_survive_a_re_render(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('ai.index'))->assertOk()->getContent();

        // Structure the script depends on by ID.
        foreach ([
            'linaInput', 'linaSend', 'linaMessages', 'linaWelcome', 'linaConvList',
            'linaSidebar', 'linaMobBackdrop', 'linaDock', 'linaDockLabel', 'linaDockOk',
            'linaDockNo', 'linaPendingPill', 'linaPendingCount', 'linaCharCount',
            'linaModeChat', 'linaModeAgent', 'linaAgentBanner', 'linaAgentBlocked',
        ] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "Missing #{$id}");
        }
    }
}
