<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use DatabaseMigrations;

    public function test_admin_upload_replaces_and_removes_photo_without_leaving_old_files(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $payload = ['name' => $user->name, 'email' => $user->email, 'role' => 'staff', 'avatar' => $this->image()];
        $this->actingAs($admin)->patch(route('users.update', $user), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $first = $user->fresh()->avatar_path;
        Storage::disk('local')->assertExists($first);
        $this->assertStringStartsWith('avatars/users/', $first);
        $this->assertNotSame('photo.png', basename($first));
        $payload['avatar'] = $this->image();
        $this->patch(route('users.update', $user), $payload)->assertSessionHasNoErrors();
        $second = $user->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
        unset($payload['avatar']);
        $payload['remove_avatar'] = 1;
        $this->patch(route('users.update', $user), $payload)->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_profile_photo_can_be_set_by_owner_and_is_served_only_to_authenticated_accounts(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->get(route('users.avatar', $user))->assertRedirect(route('login'));
        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'avatar' => $this->image()])->assertSessionHasNoErrors();
        $this->get($user->fresh()->avatarUrl())->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertArrayNotHasKey('avatar_path', $user->fresh()->toArray());
        $this->get(route('profile.edit'))->assertOk()->assertSee('Upload Foto Profil');
    }

    public function test_non_images_and_unauthorized_user_changes_cannot_store_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'staff']);
        $this->actingAs($user)->patchJson(route('users.update', $user), ['avatar' => $this->image()])->assertForbidden();
        $this->patchJson(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'avatar' => UploadedFile::fake()->createWithContent('photo.svg', '<svg/>')])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_failed_account_change_cleans_new_image_and_account_deletion_cleans_saved_image(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'staff', 'avatar' => $this->image(),
        ])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame('admin', $admin->fresh()->role);
        $target = User::factory()->create(['role' => 'staff']);
        $this->patch(route('users.update', $target), [
            'name' => $target->name, 'email' => $target->email, 'role' => 'staff', 'avatar' => $this->image(),
        ])->assertSessionHasNoErrors();
        $path = $target->fresh()->avatar_path;
        Storage::disk('local')->assertExists($path);
        $this->delete(route('users.destroy', $target))->assertRedirect(route('users.index'));
        Storage::disk('local')->assertMissing($path);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6eZ8AAAAASUVORK5CYII='));
    }
}
