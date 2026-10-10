<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimerStoreSyncTest extends TestCase
{
    use RefreshDatabase;

    private function plannerHtml(User $user): string
    {
        return $this->actingAs($user)
            ->get(route('planner.index'))
            ->assertOk()
            ->getContent();
    }

    public function test_layout_loads_single_store_before_widget(): void
    {
        $user = User::factory()->create();
        $html = $this->plannerHtml($user);

        $storePos = strpos($html, 'window.TM = {');
        $widgetPos = strpos($html, 'id="tt-root"');
        $barPos = strpos($html, 'id="plFloatingFocusBar"');

        $this->assertNotFalse($storePos, 'global TM timer store is missing');
        $this->assertNotFalse($widgetPos);
        $this->assertNotFalse($barPos);
        $this->assertLessThan($widgetPos, $storePos, 'store must load before the tt-panel widget');
    }

    public function test_both_timer_surfaces_subscribe_to_the_store(): void
    {
        $user = User::factory()->create();
        $html = $this->plannerHtml($user);

        $this->assertStringContainsString('TM.subscribe(render)', $html);
        $this->assertStringContainsString('TM.subscribe(plRenderFloatingBar)', $html);
    }

    public function test_no_legacy_dual_timer_state_remains(): void
    {
        $user = User::factory()->create();
        $html = $this->plannerHtml($user);

        foreach (['plActiveTimeEntry', 'renderFloatingBarState', 'plInitActiveTimer', 'setInterval(refresh'] as $ghost) {
            $this->assertStringNotContainsString($ghost, $html, "legacy timer state ($ghost) must be gone");
        }
    }

    public function test_active_endpoint_serializes_wall_clock_data_for_the_store(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $started = $this->actingAs($user)->postJson(route('time.start'), ['project_id' => $project->id]);
        $started->assertCreated()->assertJsonPath('active.status', 'running');
        $entryId = $started->json('active.id');

        $this->actingAs($user)->getJson(route('time.active'))
            ->assertOk()
            ->assertJsonPath('active.id', $entryId)
            ->assertJsonStructure(['active' => ['id', 'status', 'started_at', 'elapsed']]);

        $this->actingAs($user)->postJson(route('time.pause', $entryId))
            ->assertOk()
            ->assertJsonPath('active.status', 'paused');

        $this->actingAs($user)->postJson(route('time.resume', $entryId))
            ->assertOk()
            ->assertJsonPath('active.status', 'running');

        $this->actingAs($user)->postJson(route('time.stop', $entryId))
            ->assertOk()
            ->assertJsonPath('active', null);
    }
}
