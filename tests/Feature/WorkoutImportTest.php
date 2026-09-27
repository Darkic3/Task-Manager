<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutImport;
use App\Models\WorkoutPlan;
use App\Services\AiProviderService;
use App\Services\WorkoutImportService;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Mockery;
use Tests\TestCase;

class WorkoutImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_import_parses_json_previews_matches_and_creates_unknown_movements_on_confirm(): void
    {
        $user = User::factory()->create();
        $existing = Exercise::create([
            'user_id' => $user->id, 'name' => 'Pull-up', 'normalized_name' => 'pull-up', 'aliases' => ['Pull Up'],
        ]);
        $json = json_encode([
            'title' => 'Week 13 AI Plan', 'week_number' => 13, 'goal' => 'Strength',
            'rules' => ['Form over reps'],
            'days' => [[
                'weekday' => 'saturday', 'title' => 'Pull A', 'type' => 'training',
                'exercises' => [
                    ['name' => 'Pull Up', 'section' => 'main', 'target_sets' => 4, 'rep_min' => 6, 'rep_max' => 8],
                    ['name' => 'New Band Movement', 'section' => 'accessory', 'target_sets' => 2, 'rep_min' => 12, 'rep_max' => 15],
                ],
            ]],
        ]);

        $provider = Mockery::mock(AiProviderService::class);
        $provider->shouldReceive('resolve')->once()->andReturn([
            'key' => 'test-key', 'model' => 'test-model', 'type' => 'openai',
            'config' => ['base_url' => 'https://example.test/v1/chat/completions'],
        ]);
        $provider->shouldReceive('openAiPayload')->once()->andReturn(['messages' => []]);
        $provider->shouldReceive('endpointFor')->once()->andReturn('https://example.test/v1/chat/completions');
        $provider->shouldReceive('postJson')->once()->andReturn(new Response(new GuzzleResponse(200, [], json_encode([
            'choices' => [['message' => ['content' => $json]]],
        ]))));
        $this->app->instance(AiProviderService::class, $provider);

        $response = $this->actingAs($user)->post(route('workouts.imports.store'), [
            'source' => str_repeat('Workout plan text ', 2),
        ]);
        $import = WorkoutImport::firstOrFail();
        $response->assertRedirect(route('workouts.imports.show', $import));

        $this->actingAs($user)->get(route('workouts.imports.show', $import))
            ->assertOk()->assertSee('Matched: Pull-up')->assertSee('New movement');

        $this->actingAs($user)->post(route('workouts.imports.confirm', $import))
            ->assertRedirect();
        $plan = WorkoutPlan::where('title', 'Week 13 AI Plan')->firstOrFail();
        $this->assertSame($existing->id, $plan->days()->where('weekday', 'saturday')->first()->exercises()->orderBy('sort_order')->first()->exercise_id);
        $this->assertDatabaseHas('exercises', ['name' => 'New Band Movement', 'user_id' => $user->id]);
        $this->assertSame(WorkoutImport::CONFIRMED, $import->fresh()->status);
    }

    public function test_import_preview_cannot_be_viewed_or_confirmed_by_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $import = WorkoutImport::create([
            'user_id' => $owner->id,
            'source_text' => 'A workout plan with enough content.',
            'structure' => app(WorkoutImportService::class)->normalizeStructure(['title' => 'Private']),
            'status' => WorkoutImport::PREVIEW,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->actingAs($other)->get(route('workouts.imports.show', $import))->assertForbidden();
        $this->actingAs($other)->post(route('workouts.imports.confirm', $import))->assertForbidden();
    }
}
