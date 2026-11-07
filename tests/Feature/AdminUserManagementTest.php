<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin->fresh();
    }

    /**
     * Sign in through the login form. Guards are dropped first so the
     * "guest" middleware does not bounce the request back to the dashboard.
     */
    private function signIn(string $username, string $password = 'password')
    {
        $this->app['auth']->forgetGuards();

        return $this->post(route('login'), [
            'username' => $username,
            'password' => $password,
        ]);
    }

    public function test_regular_accounts_cannot_reach_the_user_management_panel(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.store'), [])->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.edit', $target->id))->assertForbidden();
        $this->actingAs($user)->put(route('admin.users.update', $target->id), [])->assertForbidden();
        $this->actingAs($user)->put(route('admin.users.role', $target->id))->assertForbidden();
        $this->actingAs($user)->put(route('admin.users.status', $target->id))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.users.destroy', $target->id))->assertForbidden();

        $this->assertTrue($target->fresh()->is_admin === false);
        $this->assertNull($target->fresh()->deleted_at);

        // The sidebar entry belongs to administrators only.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.users.index'), false);

        $this->actingAs($this->administrator())->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.users.index'), false);
    }

    public function test_admin_can_search_and_filter_the_account_list(): void
    {
        $admin = $this->administrator();
        $alice = User::factory()->create(['name' => 'Alice Cooper', 'username' => 'alicecooper']);
        $bob = User::factory()->create(['name' => 'Bob Stone', 'username' => 'bobstone']);
        User::factory()->create(['name' => 'Hidden Person', 'username' => 'hiddenperson']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('alicecooper')
            ->assertSee('bobstone');

        $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'alice']))
            ->assertOk()
            ->assertSee('alicecooper')
            ->assertDontSee('bobstone');

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'admin']))
            ->assertOk()
            ->assertSee($admin->username)
            ->assertDontSee('bobstone');

        // A suspended account only shows up under its own status filter.
        $bob->forceFill(['is_active' => false])->save();

        $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'banned']))
            ->assertOk()
            ->assertSee('bobstone');

        $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'active']))
            ->assertOk()
            ->assertDontSee('bobstone');
    }

    public function test_admin_can_create_an_account(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'username' => 'NewUser',
            'name' => 'New User',
            'email' => 'new.user@example.com',
            'password' => 'super-secret-pass',
        ])->assertRedirect(route('admin.users.index'));

        $created = User::where('username', 'newuser')->first();

        $this->assertNotNull($created);
        $this->assertSame('New User', $created->name);
        $this->assertFalse($created->is_admin);
        $this->assertTrue($created->is_active);
        $this->assertTrue(Hash::check('super-secret-pass', $created->password));

        // The fresh account can sign in.
        $this->signIn('newuser', 'super-secret-pass')->assertRedirect(route('dashboard'));
    }

    public function test_creating_an_account_validates_its_input(): void
    {
        $admin = $this->administrator();
        User::factory()->create(['username' => 'takenname']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'username' => 'takenname',
            'name' => '',
            'password' => 'short',
        ])->assertSessionHasErrors(['username', 'name', 'password']);

        $this->assertSame(1, User::where('username', 'takenname')->count());
    }

    public function test_admin_can_edit_a_profile(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $user->id), [
            'username' => 'renameduser',
            'name' => 'Renamed User',
            'email' => 'renamed@example.com',
            'bio' => 'Updated by the administrator',
            'phone' => '+123456',
            'location' => 'Tehran',
            'website' => 'https://example.com',
            'locale' => 'fa',
        ])->assertRedirect(route('admin.users.edit', $user->id));

        $fresh = $user->fresh();

        $this->assertSame('renameduser', $fresh->username);
        $this->assertSame('Renamed User', $fresh->name);
        $this->assertSame('renamed@example.com', $fresh->email);
        $this->assertSame('fa', $fresh->locale);
    }

    public function test_role_can_be_granted_and_revoked(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.role', $user->id))
            ->assertRedirect();
        $this->assertTrue($user->fresh()->is_admin);

        $this->actingAs($admin)->put(route('admin.users.role', $user->id))
            ->assertRedirect();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_the_last_administrator_cannot_be_demoted(): void
    {
        $admin = $this->administrator();

        // Only one administrator: demoting anyone (even yourself) is refused.
        $this->actingAs($admin)->put(route('admin.users.role', $admin->id))
            ->assertRedirect();
        $this->assertTrue($admin->fresh()->is_admin);

        // A second administrator makes the demotion possible.
        $second = User::factory()->create();
        $second->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->put(route('admin.users.role', $second->id))
            ->assertRedirect();
        $this->assertFalse($second->fresh()->is_admin);
    }

    public function test_a_suspended_account_cannot_sign_in_and_loses_its_session(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();

        // An open session is cut off by the account-status middleware.
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();

        $this->actingAs($admin)->put(route('admin.users.status', $user->id))
            ->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($user->fresh())->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->signIn($user->username)->assertSessionHasErrors('username');
    }

    public function test_a_deleted_account_is_soft_deleted_and_cannot_sign_in(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user->id))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));

        $this->signIn($user->username)->assertSessionHasErrors('username');
    }

    public function test_sessions_of_a_suspended_account_are_dropped(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-'.$user->id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)->put(route('admin.users.status', $user->id))
            ->assertRedirect();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_administrators_cannot_suspend_or_delete_themselves(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->put(route('admin.users.status', $admin->id))
            ->assertRedirect();
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin->id))
            ->assertRedirect();
        $this->assertNull($admin->fresh()->deleted_at);
    }
}
