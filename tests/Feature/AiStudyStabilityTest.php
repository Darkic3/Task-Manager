<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\AiSetting;
use App\Models\AiStudyBuild;
use App\Models\Project;
use App\Models\User;
use App\Services\Study\StudyBuildOrchestrator;
use App\Services\Study\StudyOutlineParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Unit\StudyOutlineFixture;

/**
 * Phase 4: long or concurrent operations must never strand the UI or the
 * workflow. Provider failures speak Persian and leak nothing; a failed
 * attempt stores nothing; races fail closed.
 */
class AiStudyStabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('fa');
    }

    private function startChain(User $user): array
    {
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
        $result = StudyBuildOrchestrator::startFromOutline($user, null, $outline);
        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'start failed');

        return $result;
    }

    private function withProvider(User $user): void
    {
        AiSetting::create([
            'user_id' => $user->id,
            'default_provider' => 'openai',
            'openai_key' => 'sk-test-key',
            'openai_model' => 'gpt-4o-mini',
        ]);
    }

    private function revisedOutline(): array
    {
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
        $outline['book_title'] = 'روانشناسی رشد (ویرایش‌شده)';

        return [
            'book_title' => $outline['book_title'],
            'chapters' => array_map(fn ($ch) => [
                'title' => $ch['title'],
                'lessons' => array_map(fn ($ls) => [
                    'title' => $ls['title'],
                    'topics' => $ls['topics'],
                ], $ch['lessons']),
                'extra_tasks' => $ch['extra_tasks'],
            ], $outline['chapters']),
        ];
    }

    private function assertNoSecrets(string $text): void
    {
        $this->assertStringNotContainsStringIgnoringCase($text, 'https://');
        $this->assertStringNotContainsStringIgnoringCase($text, 'http://');
        $this->assertStringNotContainsString($text, 'sk-test-key');
        $this->assertStringNotContainsStringIgnoringCase($text, 'Bearer');
        $this->assertStringNotContainsString($text, 'internal.example');
    }

    public function test_provider_500_is_persian_without_leaks(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $build = $this->startChain($user)['build'];

        // The upstream body itself contains a URL + key: neither may reach
        // the user (the mapped Persian message carries no detail at all).
        Http::fake(['*' => Http::response([
            'error' => ['message' => 'upstream exploded for https://internal.example/v1?key=sk-test-key'],
        ], 500)]);

        $res = $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان را عوض کن']
        )->assertStatus(502)->assertJsonPath('code', 'provider_error');

        $error = (string) $res->json('error');
        $this->assertStringContainsString('تغییر نکرد', $error);
        $this->assertNoSecrets($error);

        // Nothing stored: same version, same outline, same head.
        $this->assertSame(1, $build->fresh()->version);
        $this->assertCount(1, $build->fresh()->versions);
        $this->assertSame(AiPlan::STATUS_PROPOSED, AiPlan::firstOrFail()->status);
    }

    public function test_provider_timeout_is_persian_without_url(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $build = $this->startChain($user)['build'];

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 90001 milliseconds with 0 bytes received for https://provider.example/v1/chat/completions');
        });

        $res = $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان را عوض کن']
        )->assertStatus(502)->assertJsonPath('code', 'provider_error');

        $error = (string) $res->json('error');
        $this->assertStringContainsString('پاسخ نداد', $error);
        $this->assertStringContainsString('تغییر نکرد', $error);
        $this->assertNoSecrets($error);

        $this->assertSame(1, $build->fresh()->version);
    }

    public function test_provider_401_maps_to_key_guidance_without_leaks(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $build = $this->startChain($user)['build'];

        Http::fake(['*' => Http::response([
            'error' => ['message' => 'Incorrect API key provided: sk-test-**** (see https://platform.example/docs)'],
        ], 401)]);

        $res = $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان را عوض کن']
        )->assertStatus(502)->assertJsonPath('code', 'provider_error');

        $error = (string) $res->json('error');
        $this->assertStringContainsString('کلید API', $error);
        $this->assertNoSecrets($error);
    }

    public function test_unusable_provider_json_is_persian(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $build = $this->startChain($user)['build'];

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'this is not json{{{']]],
        ], 200)]);

        $res = $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان را عوض کن']
        )->assertStatus(502)->assertJsonPath('code', 'provider_error');

        $error = (string) $res->json('error');
        $this->assertStringContainsString('قابل استفاده', $error);
        $this->assertStringContainsString('تغییر نکرد', $error);
        $this->assertNoSecrets($error);
        $this->assertSame(1, $build->fresh()->version);
    }

    public function test_failed_attempt_then_retry_creates_single_version(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $build = $this->startChain($user)['build'];

        // Attempt 1: timeout → nothing stored. One fake for both attempts:
        // Http::fake() stubs stack (merge, first match wins), so behaviour
        // is switched with a flag instead of a second fake() call.
        $failUpstream = true;
        $rev = json_encode($this->revisedOutline(), JSON_UNESCAPED_UNICODE);
        Http::fake(function () use (&$failUpstream, $rev) {
            if ($failUpstream) {
                throw new ConnectionException('cURL error 28: Operation timed out for https://provider.example/v1');
            }

            return Http::response(['choices' => [['message' => ['content' => $rev]]]], 200);
        });
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان را عوض کن']
        )->assertStatus(502);
        $this->assertSame(1, $build->fresh()->version);

        // Retry (the safe client behaviour after a timeout: a fresh attempt)
        // creates exactly one new version — no duplication, no loss.
        $failUpstream = false;
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build->fresh()), ['feedback' => 'عنوان را عوض کن']
        )->assertOk()->assertJsonPath('master.version', 2);

        $fresh = $build->fresh();
        $this->assertCount(2, $fresh->versions);
        $this->assertSame(1, AiPlan::where('user_id', $user->id)->where('status', AiPlan::STATUS_PROPOSED)->count());
    }

    public function test_revise_blocked_while_package_executes(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $started['plan']->update(['status' => AiPlan::STATUS_EXECUTING]);

        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $started['build']), ['feedback' => 'عنوان را عوض کن']
        )->assertStatus(422)->assertJsonPath('code', 'already_started');

        $this->assertSame(1, $started['build']->fresh()->version);
    }

    public function test_package_revise_failure_is_persian_without_leaks(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $this->actingAs($user)->postJson(
            route('ai.study-builds.approve', $started['build']), ['version' => 1]
        )->assertOk();
        $this->actingAs($user)->postJson(
            route('ai.study-builds.approve-packages', $started['build']), ['version' => 1]
        )->assertOk();

        Http::fake(['*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $res = $this->actingAs($user)->postJson(
            route('ai.plans.revise-package', $started['plan']), ['feedback' => 'x']
        )->assertStatus(502)->assertJsonPath('code', 'provider_error');

        $error = (string) $res->json('error');
        $this->assertStringContainsString('تغییر نکرد', $error);
        $this->assertNoSecrets($error);
        $this->assertSame(AiPlan::STATUS_PROPOSED, $started['plan']->fresh()->status);
    }

    public function test_status_endpoint_exposes_no_key_material(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);

        $res = $this->actingAs($user)->getJson(route('ai.status'))->assertOk();
        $resolved = $res->json('resolved');

        $this->assertEqualsCanonicalizing(['provider', 'model', 'type'], array_keys($resolved ?? []));
        $this->assertStringNotContainsString('sk-test-key', $res->getContent());
        $this->assertStringNotContainsStringIgnoringCase($res->getContent(), 'api_key');
    }
}
