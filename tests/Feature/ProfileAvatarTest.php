<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Profile avatar goes through ImageUploadService (ported from shopora):
 * safe naming, webp + square crop when GD exists, old-file cleanup.
 * NOTE: this environment has no GD, so jpeg uploads are kept as-is —
 * the service still validates content and stores safely.
 */
class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    // 1x1 real JPEG bytes (getimagesize passes without GD).
    private const PIXEL_JPEG = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAAAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AK//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAn//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAYFAn//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAZFAn//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAVAn//2gAMAwEAAhADEAAAAdT//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAQ//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAYFAn//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAZFAn//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAVAn//2Q==';

    // 1x1 real GIF bytes.
    private const PIXEL_GIF = 'R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=';

    private function uploadFile(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar').'_'.$name;
        file_put_contents($path, base64_decode($bytes));

        return new UploadedFile($path, $name, $mime, null, true);
    }

    public function test_update_stores_avatar_and_old_file_is_removed_on_reupload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => null]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $this->uploadFile(self::PIXEL_JPEG, 'me.jpg', 'image/jpeg'),
        ])->assertRedirect(route('profile.show'));

        $first = $user->fresh()->avatar;
        $this->assertNotNull($first);
        $this->assertStringStartsWith('avatars/', $first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $this->uploadFile(self::PIXEL_GIF, 'me2.gif', 'image/gif'),
        ])->assertRedirect(route('profile.show'));

        $second = $user->fresh()->avatar;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => null]);

        $path = tempnam(sys_get_temp_dir(), 'avatar').'_evil.txt';
        file_put_contents($path, 'not an image');
        $file = new UploadedFile($path, 'evil.txt', 'text/plain', null, true);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $file,
        ])->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar);
    }

    public function test_delete_avatar_removes_file_and_nulls_column(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => null]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $this->uploadFile(self::PIXEL_JPEG, 'me.jpg', 'image/jpeg'),
        ])->assertRedirect();

        $stored = $user->fresh()->avatar;
        Storage::disk('public')->assertExists($stored);

        $this->actingAs($user)->delete(route('profile.avatar.delete'))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertNull($user->fresh()->avatar);
        Storage::disk('public')->assertMissing($stored);
    }

    public function test_service_rejects_non_image_content(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $path = tempnam(sys_get_temp_dir(), 'avatar').'_x.jpg';
        file_put_contents($path, 'plain text, not pixels');
        $file = new UploadedFile($path, 'x.jpg', 'image/jpeg', null, true);

        $this->assertNull(ImageUploadService::upload($file, 'avatars', [512, 512]));
    }

    public function test_service_delete_tolerates_empty_and_missing_paths(): void
    {
        Storage::fake('public');

        $this->assertTrue(ImageUploadService::delete(null));
        $this->assertTrue(ImageUploadService::delete('avatars/does-not-exist.webp'));
    }
}
