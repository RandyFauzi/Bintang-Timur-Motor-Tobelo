<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_staff_cannot_manage_accounts_or_export_users(): void
    {
        $target = User::factory()->create(['role' => 'staff']);
        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->actingAs($target)->get(route('users.index'))->assertForbidden();
        $this->get(route('users.export'))->assertForbidden();
        $this->post(route('users.store'), [])->assertForbidden();
        $this->patch(route('users.update', $target), [])->assertForbidden();
        $this->delete(route('users.destroy', $target))->assertForbidden();
    }

    public function test_admin_can_create_a_user_with_normalized_email_and_hashed_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => '  Joko Kasir  ', 'email' => ' JOKO@EXAMPLE.COM ', 'role' => 'staff',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('users.index'))->assertSessionHasNoErrors();
        $user = User::where('email', 'joko@example.com')->sole();
        $this->assertSame('Joko Kasir', $user->name);
        $this->assertSame('staff', $user->role);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->get(route('users.index', ['edit' => $user->id]))->assertOk()->assertSee('Edit Pengguna')->assertDontSee($user->password);
    }

    public function test_edit_can_preserve_password_and_email_change_clears_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'staff']);
        $password = $target->password;
        $this->actingAs($admin)->patch(route('users.update', $target), ['name' => 'Nama Baru', 'email' => 'baru@example.com', 'role' => 'admin', 'password' => ''])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($password, $target->refresh()->password);
        $this->assertNull($target->email_verified_at);
        $this->assertSame('admin', $target->role);
    }

    public function test_edit_password_requires_confirmation_and_rotates_remember_token(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'staff']);
        $token = $target->remember_token;
        $payload = ['name' => $target->name, 'email' => $target->email, 'role' => 'staff', 'password' => 'new-password', 'password_confirmation' => 'mismatch'];
        $this->actingAs($admin)->patchJson(route('users.update', $target), $payload)->assertUnprocessable()->assertJsonValidationErrors('password');
        $payload['password_confirmation'] = 'new-password';
        $this->patch(route('users.update', $target), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password', $target->refresh()->password));
        $this->assertNotSame($token, $target->remember_token);
    }

    public function test_duplicate_email_invalid_role_and_self_demotion_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($admin)->postJson(route('users.store'), ['name' => 'Other', 'email' => $staff->email, 'role' => 'owner', 'password' => 'short'])->assertUnprocessable()->assertJsonValidationErrors(['email', 'role', 'password']);
        $this->patchJson(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'staff'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertSame('admin', $admin->refresh()->role);
    }

    public function test_search_role_filter_cards_and_export_share_the_same_scope_without_exposing_secrets(): void
    {
        $admin = User::factory()->create(['name' => 'Manager', 'role' => 'admin']);
        $staff = User::factory()->create(['name' => 'Joko Pilihan', 'role' => 'staff']);
        $response = $this->actingAs($admin)->get(route('users.index', ['search' => 'Pilihan', 'role' => 'staff', 'view' => 'cards']));
        $response->assertOk()->assertSee('product-card', false)->assertSee('Joko Pilihan')->assertDontSee($staff->password)->assertDontSee($staff->remember_token);
        $this->assertSame([$staff->id], $response->viewData('users')->modelKeys());
        $csv = $this->get(route('users.export', ['search' => 'Pilihan']))->assertOk()->assertDownload()->streamedContent();
        $this->assertStringContainsString('Joko Pilihan', $csv);
        $this->assertStringNotContainsString('Manager', $csv);
        $this->assertStringNotContainsString($staff->password, $csv);
    }

    public function test_accounts_with_sales_cannot_be_deleted_and_self_deletion_is_prevented(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        Sale::factory()->for($staff)->create();
        $this->actingAs($admin)->delete(route('users.destroy', $staff))->assertSessionHasErrors('user');
        $this->delete(route('users.destroy', $admin))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('users',['id' => $admin->id]);
    }
}
