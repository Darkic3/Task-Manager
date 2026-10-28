<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_username_account_without_email_or_phone_and_logs_in(): void
    {
        $response = $this->post(route('register.store'), [
            'username' => 'Beta_User-1',
            'password' => 'a-long-beta-password',
            'password_confirmation' => 'a-long-beta-password',
            'is_admin' => true,
        ]);

        $user = User::where('username', 'beta_user-1')->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('beta_user-1', $user->username);
        $this->assertSame('beta_user-1', $user->name);
        $this->assertNull($user->email);
        $this->assertNull($user->phone);
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('a-long-beta-password', $user->password));
    }

    public function test_registration_requires_a_valid_unique_username_and_confirmed_password(): void
    {
        User::factory()->create(['username' => 'taken_name']);

        $this->from(route('register'))->post(route('register.store'), [
            'username' => 'taken_name',
            'password' => 'a-long-beta-password',
            'password_confirmation' => 'different-password',
        ])->assertRedirect(route('register'))->assertSessionHasErrors(['username', 'password']);

        $this->from(route('register'))->post(route('register.store'), [
            'username' => 'bad name!',
            'password' => 'a-long-beta-password',
            'password_confirmation' => 'a-long-beta-password',
        ])->assertSessionHasErrors('username');
    }

    public function test_login_accepts_username_and_legacy_email(): void
    {
        $user = User::factory()->create([
            'username' => 'beta_member',
            'email' => 'member@example.test',
            'password' => Hash::make('a-long-beta-password'),
        ]);

        $this->post(route('login'), [
            'username' => 'BETA_MEMBER',
            'password' => 'a-long-beta-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();

        $this->post(route('login'), [
            'username' => 'member@example.test',
            'password' => 'a-long-beta-password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_email_and_phone_can_be_added_and_removed_from_profile(): void
    {
        $user = User::factory()->create(['email' => null, 'phone' => null]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'beta@example.test',
            'phone' => '+989121234567',
        ])->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'beta@example.test',
            'phone' => '+989121234567',
        ]);

        $this->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => '',
            'phone' => '',
        ])->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => null, 'phone' => null]);
    }
}
