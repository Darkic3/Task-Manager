<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeatureManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    public function test_regular_accounts_cannot_open_or_change_the_admin_panel(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.features.index'))->assertForbidden();
        $this->put(route('admin.features.update', 'ai'), ['enabled' => true])->assertForbidden();

        $this->assertDatabaseMissing('feature_flags', ['key' => 'ai']);
    }

    public function test_admin_can_use_locked_modules_while_regular_accounts_are_blocked(): void
    {
        config(['beta.features.ai' => false, 'beta.features.files' => false]);
        $admin = $this->administrator();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/ai')->assertNotFound();
        $this->actingAs($user)->get('/files')->assertNotFound();

        $this->actingAs($admin)->get('/ai')->assertOk();
        $this->actingAs($admin)->get('/files')->assertOk();
        $this->get(route('admin.features.index'))
            ->assertOk()
            ->assertSee(__('AI assistant'))
            ->assertSee(__('Files'));

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('ai.index'), false)
            ->assertSee(route('files.index'), false)
            ->assertSee(route('admin.features.index'), false);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('ai.index'), false)
            ->assertDontSee(route('files.index'), false);
    }

    public function test_admin_can_publish_and_lock_a_module_for_regular_accounts(): void
    {
        config(['beta.features.ai' => false]);
        $admin = $this->administrator();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.features.update', 'ai'), ['enabled' => '1'])
            ->assertRedirect(route('admin.features.index'));

        $this->assertDatabaseHas('feature_flags', [
            'key' => 'ai',
            'enabled' => true,
            'updated_by' => $admin->id,
        ]);
        $this->actingAs($user)->get('/ai')->assertOk();

        $this->actingAs($admin)->put(route('admin.features.update', 'ai'), ['enabled' => '0'])
            ->assertRedirect(route('admin.features.index'));

        $this->actingAs($user)->get('/ai')->assertNotFound();
        $this->actingAs($admin)->get('/ai')->assertOk();
    }

    public function test_admin_can_lock_a_regular_module_without_locking_the_admin_out(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.features.update', 'projects'), ['enabled' => '0'])
            ->assertRedirect(route('admin.features.index'));

        $this->actingAs($user)->get('/projects')->assertNotFound();
        $this->actingAs($admin)->get('/projects')->assertOk();
    }

    public function test_cli_can_grant_admin_access_and_cannot_revoke_the_last_admin(): void
    {
        $user = User::factory()->create();

        $this->artisan('task-manager:admin', ['username' => $user->username])->assertExitCode(0);
        $this->assertTrue($user->fresh()->is_admin);

        $this->artisan('task-manager:admin', ['username' => $user->username, '--revoke' => true])->assertExitCode(1);
        $this->assertTrue($user->fresh()->is_admin);
    }
}
