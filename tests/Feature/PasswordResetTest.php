<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_and_reset_forms_are_available_to_guests(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('name="email"', false);

        $user = User::factory()->create(['email' => 'member@example.test']);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_reset_link_is_sent_only_to_the_optional_profile_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'member@example.test']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status', __('If the email is registered on an account, a reset link has been sent.'));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_response_does_not_disclose_unknown_or_missing_email(): void
    {
        Notification::fake();
        User::factory()->create(['email' => null]);

        foreach (['nobody@example.test', 'member@example.test'] as $email) {
            $this->post(route('password.email'), ['email' => $email])
                ->assertRedirect()
                ->assertSessionHas('status', __('If the email is registered on an account, a reset link has been sent.'));
        }

        Notification::assertNothingSent();
    }

    public function test_valid_reset_token_replaces_password_and_rotates_remember_token(): void
    {
        $user = User::factory()->create(['email' => 'member@example.test']);
        $oldRememberToken = $user->remember_token;
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
        ])->assertRedirect(route('login'))->assertSessionHas('status', __('passwords.reset'));

        $user->refresh();
        $this->assertTrue(Hash::check('a-new-long-password', $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token);
    }
}
