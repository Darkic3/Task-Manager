<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_creates_and_promotes_the_configured_account_idempotently(): void
    {
        config(['admin' => [
            'name' => 'Ahora',
            'username' => 'ahora',
            'email' => 'ahora@a.com',
            'password' => 'a-unique-production-password',
        ]]);

        app(AdminUserSeeder::class)->run();

        $admin = User::where('email', 'ahora@a.com')->firstOrFail();
        $adminId = $admin->id;
        $this->assertSame('ahora', $admin->username);
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check('a-unique-production-password', $admin->password));

        config(['admin.password' => '']);
        app(AdminUserSeeder::class)->run();

        $admin->refresh();
        $this->assertSame($adminId, $admin->id);
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check('a-unique-production-password', $admin->password));
    }

    public function test_admin_seeder_promotes_an_existing_email_account_without_resetting_its_password(): void
    {
        $existing = User::factory()->create([
            'name' => 'Existing',
            'username' => 'existing_user',
            'email' => 'ahora@a.com',
        ]);

        config(['admin' => [
            'name' => 'Ahora',
            'username' => 'ahora',
            'email' => 'ahora@a.com',
            'password' => '',
        ]]);

        app(AdminUserSeeder::class)->run();

        $existing->refresh();
        $this->assertSame('Ahora', $existing->name);
        $this->assertSame('ahora', $existing->username);
        $this->assertTrue($existing->is_admin);
        $this->assertTrue(Hash::check('password', $existing->password));
    }
}
