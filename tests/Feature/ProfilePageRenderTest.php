<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_renders_in_english(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'locale' => 'en',
            'phone' => '+1 555 000 0000',
            'location' => 'London',
            'website' => 'https://example.com',
            'bio' => 'Building things.',
        ]);

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertSee('Profile completeness')
            ->assertSee('Personal Information')
            ->assertSee('Security', false)
            ->assertSee('Building things.');
    }

    public function test_profile_page_renders_in_persian_with_translations(): void
    {
        $user = User::factory()->create([
            'name' => 'کاربر نمونه',
            'locale' => 'fa',
        ]);

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('کاملیت پروفایل')
            ->assertSee('اطلاعات فردی')
            ->assertSee('ثبت نشده')
            ->assertSee('راه‌های ارتباطی')
            ->assertSee('dir="rtl"', false);
    }

    public function test_profile_page_shows_counts_and_morning_routine(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $routine = Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'daily',
            'title' => 'Morning Walk',
            'tracking_mode' => 'value',
            'value_kind' => 'time',
        ]);

        $user->update([
            'wake_routine_id' => $routine->id,
            'morning_checkin_enabled' => true,
            'morning_window_start' => '05:00',
            'morning_window_end' => '10:00',
        ]);

        $response = $this->actingAs($user)->get(route('profile.show'))->assertOk();

        $response->assertSee('Morning Walk');
        $response->assertSee('05:00');
        $response->assertSee('Enabled');
    }

    public function test_edit_page_renders_in_english_with_all_sections(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'locale' => 'en', 'bio' => 'Hello world']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Edit Profile')
            ->assertSee('Basic Information')
            ->assertSee('Profile Picture')
            ->assertSee('Morning Check-in')
            ->assertSee('Good to know')
            ->assertSee('Hello world')
            ->assertSee('name="avatar"', false)
            ->assertSee('name="morning_window_start"', false)
            ->assertSee('value="fa"', false);
    }

    public function test_edit_page_renders_in_persian(): void
    {
        $user = User::factory()->create(['name' => 'کاربر نمونه', 'locale' => 'fa']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('ویرایش پروفایل')
            ->assertSee('اطلاعات پایه')
            ->assertSee('عکس پروفایل')
            ->assertSee('چک‌این صبحگاهی')
            ->assertSee('نکات مهم');
    }

    public function test_edit_page_shows_validation_errors(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->from(route('profile.edit'))
            ->put(route('profile.update'), ['name' => '', 'email' => 'not-an-email'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors(['name', 'email']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('is-invalid', false);
    }

    public function test_password_page_renders_in_english(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'locale' => 'en']);

        $this->actingAs($user)->get(route('profile.password'))
            ->assertOk()
            ->assertSee('Change Password')
            ->assertSee('Update Password')
            ->assertSee('Current Password')
            ->assertSee('New Password')
            ->assertSee('Confirm New Password')
            ->assertSee('At least 8 characters')
            ->assertSee('One special character')
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_password_page_renders_in_persian(): void
    {
        $user = User::factory()->create(['name' => 'کاربر نمونه', 'locale' => 'fa']);

        $this->actingAs($user)->get(route('profile.password'))
            ->assertOk()
            ->assertSee('تغییر رمز عبور')
            ->assertSee('رمز عبور فعلی')
            ->assertSee('رمز عبور جدید')
            ->assertSee('تکرار رمز عبور جدید')
            ->assertSee('نکات رمز عبور')
            ->assertSee('ایمن بمانید');
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->from(route('profile.password'))
            ->put(route('profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'New-Strong-Pass1!',
                'password_confirmation' => 'New-Strong-Pass1!',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_password_update_succeeds_and_translates_flash(): void
    {
        $user = User::factory()->create(['locale' => 'fa']);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'password',
            'password' => 'New-Strong-Pass1!',
            'password_confirmation' => 'New-Strong-Pass1!',
        ])->assertRedirect(route('profile.show'));

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('رمز عبور با موفقیت به‌روزرسانی شد!');
    }

    public function test_profile_update_saves_and_translates_flash(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Grace Hopper',
            'email' => $user->email,
            'locale' => 'fa',
        ])->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Grace Hopper', 'locale' => 'fa']);

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('پروفایل شما با موفقیت به‌روزرسانی شد!');
    }
}
