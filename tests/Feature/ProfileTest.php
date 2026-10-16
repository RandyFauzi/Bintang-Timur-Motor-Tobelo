<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_normalizes_input_and_does_not_allow_role_changes(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $this->actingAs($user)->patch(route('profile.update'), ['name' => '  Nama Baru  ', 'email' => '  BARU@EXAMPLE.COM  ', 'role' => 'admin'])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('baru@example.com', $user->email);
        $this->assertSame('staff', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->get(route('profile.edit'))->assertOk()->assertSee('Nama Baru')->assertSee('baru@example.com');
    }

    public function test_profile_rejects_duplicate_email_and_blank_name_without_changes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $originalName = $user->name;
        $originalEmail = $user->email;
        $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), ['name' => '   ', 'email' => $other->email])->assertSessionHasErrors(['name', 'email']);
        $this->assertSame($originalName, $user->fresh()->name);
        $this->assertSame($originalEmail, $user->fresh()->email);
    }

    public function test_profile_and_password_updates_require_authentication(): void
    {
        $this->patch(route('profile.update'), ['name' => 'Unauthorized', 'email' => 'unauthorized@example.com'])->assertRedirect(route('login'));
        $this->put(route('password.update'), ['password' => 'Unauthorized-password-123'])->assertRedirect(route('login'));
    }

    public function test_settings_never_render_old_password_input(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['_old_input' => ['password' => 'never-render-new-password', 'current_password' => 'never-render-old-password', 'password_confirmation' => 'never-render-confirmation']])->get(route('profile.edit'))->assertOk()->assertSee('Informasi Profil')->assertSee('Ganti Password')->assertDontSee('never-render-new-password')->assertDontSee('never-render-old-password')->assertDontSee('never-render-confirmation');
    }
}
