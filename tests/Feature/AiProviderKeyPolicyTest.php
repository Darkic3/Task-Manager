<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\User;
use App\Services\AiProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHARACTERISATION test — documents the provider-key resolution order as it
 * exists today. It deliberately changes nothing.
 *
 * The env-fallback step (a keyless user may silently consume a global
 * `.env` key) is a deliberate, documented product behaviour:
 *
 *   - config/ai.php:10-11  "env is fallback for global / legacy config"
 *   - README.md:69-81      "Option B - Global env fallback"
 *   - docs/AI_SYSTEM.md:17 BYOK, env fallback for single-user mode
 *
 * ...and simultaneously flagged as a cost/abuse risk by
 * docs/AI_TECH_SPEC.md (S2/P4), which recommends gating it behind a flag.
 *
 * Because the product intent is genuinely ambiguous, no policy change was
 * made. This test pins the CURRENT order so that whichever way the decision
 * goes, the diff of consequences is explicit.
 */
class AiProviderKeyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AiProviderService
    {
        return new AiProviderService;
    }

    public function test_user_key_wins_over_env(): void
    {
        config()->set('services.openai.key', 'env-global-key');
        $user = User::factory()->create();
        AiSetting::create([
            'user_id' => $user->id,
            'openai_key' => 'user-personal-key',
        ]);

        $this->assertSame('user-personal-key', $this->service()->getKey($user, 'openai'));
    }

    public function test_env_key_is_used_when_user_has_none(): void
    {
        config()->set('services.openai.key', 'env-global-key');
        $user = User::factory()->create();

        // Documents the CURRENT behaviour: silent fallback to the global key.
        $this->assertSame('env-global-key', $this->service()->getKey($user, 'openai'));
    }

    public function test_no_key_anywhere_means_not_configured(): void
    {
        config()->set('services.openai.key', null);
        $user = User::factory()->create();

        $this->assertNull($this->service()->getKey($user, 'openai'));
        $this->assertFalse($this->service()->isConfigured($user));
        $this->assertNull($this->service()->resolve($user));
    }

    public function test_env_fallback_provider_is_selectable_for_resolution(): void
    {
        config()->set('services.openai.key', 'env-global-key');
        config()->set('ai.default_provider', 'openai');
        $user = User::factory()->create();

        $resolved = $this->service()->resolve($user);

        $this->assertNotNull($resolved, 'a keyless user currently resolves to the env key');
        $this->assertSame('openai', $resolved['provider']);
        $this->assertSame('env-global-key', $resolved['key']);
    }

    public function test_providers_without_any_key_are_never_resolved(): void
    {
        foreach (['openai', 'gemini', 'anthropic', 'deepseek', 'meta'] as $provider) {
            config()->set("services.{$provider}.key", null);
        }
        $user = User::factory()->create();

        $this->assertSame([], $this->service()->enabledProviders($user));
        $this->assertNull($this->service()->resolve($user));
    }
}
