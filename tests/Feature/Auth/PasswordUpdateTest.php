<?php

namespace Tests\Feature\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }

    public function test_confirmation_and_reusing_current_password_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from(route('profile.edit'))->put(route('password.update'), ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'different-password'])->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->put(route('password.update'), ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_errors_are_attached_to_the_correct_form_fields(): void
    {
        $this->actingAs(User::factory()->create())->from(route('profile.edit'))->put(route('password.update'), ['current_password' => 'wrong-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'Password saat ini tidak sesuai.']);
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('profile.edit'))->assertOk();
        $response->assertSee('Password saat ini tidak sesuai.');
        $this->assertMatchesRegularExpression('/id="update-password-current"[^>]*aria-invalid="true"/', $response->getContent());
        $this->assertMatchesRegularExpression('/id="delete-account-password"[^>]*aria-invalid="false"/', $response->getContent());
    }

    public function test_success_rotates_session_and_remember_token_without_logging_password(): void
    {
        $user = User::factory()->create();
        $oldRememberToken = $user->remember_token;
        $this->actingAs($user)->withSession(['settings-marker' => 'preserved']);
        $oldSessionId = session()->getId();
        $this->withCookie(config('session.cookie'), $oldSessionId)->put(route('password.update'), ['current_password' => 'password', 'password' => 'fresh-password-123', 'password_confirmation' => 'fresh-password-123'])->assertRedirect(route('profile.edit'))->assertSessionHas('success')->assertSessionHas('settings-marker', 'preserved');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotSame($oldRememberToken, $user->fresh()->remember_token);
        $this->assertTrue(Hash::check('fresh-password-123', $user->fresh()->password));
        $log = ActivityLog::where('route_name', 'password.update')->firstOrFail();
        $this->assertSame('[DISAMARKAN]', $log->changes[0]['after']['password']);
        $this->assertStringNotContainsString('fresh-password-123', $log->toJson());
    }
}
