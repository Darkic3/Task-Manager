<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BetaFeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_management_and_ai_endpoints_are_unavailable_during_beta(): void
    {
        config(['beta.features.files' => false, 'beta.features.ai' => false]);
        $this->actingAs(User::factory()->create());

        $this->get('/files')->assertNotFound();
        $this->post('/files', [])->assertNotFound();
        $this->get('/ai')->assertNotFound();
        $this->post('/ai/stream', [])->assertNotFound();
        $this->post('/planner/ai-optimize', [])->assertNotFound();
        $this->post('/notes/send-to-ai', [])->assertNotFound();
    }
}
