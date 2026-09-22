<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_project_names_clip_inside_cards(): void
    {
        $user = User::factory()->create();
        Project::factory()->create([
            'user_id' => $user->id,
            'name' => 'طیف اختلالات اسکیزوفرنی و سایر اختلالات سایکوتیک ' . str_repeat('خیلی طولانی ', 5),
            'status' => 'not_started',
        ]);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
        // The text wrapper must shrink inside the flex row with real CSS.
        // (Bootstrap 5 has no `min-w-0` utility — the old class did nothing
        // and long titles overflowed the card.)
        $response->assertSee('cu-card-text', false);
        $response->assertDontSee('min-w-0', false);
        $response->assertSee('cu-card-name', false);
    }
}
