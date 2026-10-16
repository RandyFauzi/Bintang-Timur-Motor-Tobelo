<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessSettingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_only_admin_can_change_business_identity(): void
    {
        $this->patch(route('business.update'))->assertRedirect(route('login'));
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->patchJson(route('business.update'), ['business_name' => 'Tidak Sah'])->assertForbidden();
        $this->get(route('profile.edit'))->assertDontSee('Simpan Identitas Usaha');
        $this->assertDatabaseCount('business_settings', 0);
    }

    public function test_admin_can_update_name_and_logo_and_restore_default_logo(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6eZ8AAAAASUVORK5CYII='));
        $this->actingAs($admin)->patch(route('business.update'), ['business_name' => 'Toko Baru', 'logo' => $file])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
        $settings = BusinessSetting::findOrFail(1);
        $this->assertSame('Toko Baru', $settings->business_name);
        Storage::disk('local')->assertExists($settings->logo_path);
        $this->get(route('business.logo'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('profile.edit'))->assertOk()->assertSee('Toko Baru')->assertSee('Simpan Identitas Usaha');
        $this->patch(route('business.update'), ['business_name' => 'Toko Baru', 'remove_logo' => 1])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($settings->logo_path);
        $this->assertNull($settings->fresh()->logo_path);
        $this->assertDatabaseHas('activity_logs', ['route_name' => 'business.update', 'module' => 'business', 'result' => 'success']);
    }

    public function test_invalid_logo_cannot_change_business_name_or_create_files(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patchJson(route('business.update'), ['business_name' => 'Bad Logo', 'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg/>')])->assertUnprocessable()->assertJsonValidationErrors('logo');
        $this->assertDatabaseCount('business_settings', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
