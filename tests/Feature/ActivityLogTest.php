<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_read_or_export_logs_and_denial_is_recorded(): void
    {
        $this->get(route('activity-logs.index'))->assertRedirect(route('login'));
        $user = User::factory()->create(['role' => 'staff']);
        $this->actingAs($user)->get(route('activity-logs.index'))->assertForbidden();
        $this->get(route('activity-logs.export'))->assertForbidden();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'result' => 'denied', 'status_code' => 403]);
    }

    public function test_reads_expense_writes_and_validation_failures_are_recorded_without_payload_secrets(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $this->actingAs($user)->get(route('reports.finance', ['period' => '7days', 'token' => 'never-store-this']))->assertOk();
        $this->from(route('reports.finance'))->post(route('reports.expenses.store'), ['category' => 'Konsumsi', 'amount' => 12500, 'expense_date' => now()->toDateString(), 'description' => 'sensitive free text', 'password' => 'never-store-this'])->assertRedirect();
        $log = ActivityLog::where('route_name', 'reports.expenses.store')->firstOrFail();
        $this->assertSame('success', $log->result);
        $this->assertSame('Expense', $log->changes[0]['entity']);
        $this->assertSame('[DISAMARKAN]', $log->changes[0]['after']['description']);
        $this->assertSame(12500, (int) $log->changes[0]['after']['amount']);
        $this->assertStringNotContainsString('never-store-this', ActivityLog::all()->toJson());
        $this->postJson(route('reports.expenses.store'), ['amount' => -1])->assertUnprocessable();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'route_name' => 'reports.expenses.store', 'result' => 'failed', 'status_code' => 422]);
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_login_failed_login_and_logout_are_audited_without_credentials(): void
    {
        $user = User::factory()->create(['password' => 'Audit-password-123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('activity_logs', ['action' => 'login_failed', 'result' => 'failed']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Audit-password-123'])->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['action' => 'login', 'user_id' => $user->id, 'result' => 'success']);
        $this->post('/logout')->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['action' => 'logout', 'user_id' => $user->id]);
        $serialized = ActivityLog::all()->toJson();
        $this->assertStringNotContainsString('Audit-password-123', $serialized);
        $this->assertStringNotContainsString('wrong-password', $serialized);
        $this->assertStringNotContainsString($user->email, $serialized);
    }

    public function test_rolled_back_changes_are_not_presented_as_committed_changes(): void
    {
        Route::middleware('web')->post('/audit-rollback-test', function () {
            try {
                DB::transaction(function (): void {
                    Expense::create(['category' => 'Rolled back', 'amount' => 100, 'expense_date' => now()->toDateString()]);
                    throw new LogicException('Rollback');
                });
            } catch (LogicException) {
            }

            return response()->json(['saved' => false], 422);
        })->name('reports.rollback-test');
        $this->actingAs(User::factory()->create())->postJson('/audit-rollback-test')->assertUnprocessable();
        $log = ActivityLog::where('route_name', 'reports.rollback-test')->firstOrFail();
        $this->assertSame([], $log->changes);
        $this->assertSame('failed', $log->result);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_admin_can_filter_inspect_and_export_logs_and_deleted_actors_remain_identifiable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $actor = User::factory()->create(['name' => 'Akun Lama']);
        $record = ActivityLog::factory()->create(['user_id' => $actor->id, 'actor_name' => $actor->name, 'action' => 'update', 'module' => 'products', 'route_name' => 'products.update']);
        ActivityLog::factory()->create(['actor_name' => 'Other account']);
        $actor->delete();
        $response = $this->actingAs($admin)->get(route('activity-logs.index', ['user_id' => $actor->id, 'action' => 'update', 'detail' => $record->id]));
        $response->assertOk()->assertSee('Akun Lama')->assertSee($record->request_id);
        $this->assertSame(1, $response->viewData('logs')->total());
        $export = $this->get(route('activity-logs.export', ['user_id' => $actor->id]));
        $export->assertDownload();
        $this->assertStringContainsString('Akun Lama', $export->streamedContent());
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'export']);
        $this->patch('/activity-logs/'.$record->id, ['actor_name' => 'Changed'])->assertNotFound();
        $this->delete('/activity-logs/'.$record->id)->assertNotFound();
    }

    public function test_model_rejects_rewriting_existing_audit_entries(): void
    {
        $log = ActivityLog::factory()->create();
        $this->expectException(LogicException::class);
        $log->update(['actor_name' => 'Changed']);
    }

    public function test_filters_reject_invalid_dates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson(route('activity-logs.index', ['start_date' => '2026-10-20', 'end_date' => '2026-10-01']))->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_expired_csrf_token_is_audited_before_the_controller_runs(): void
    {
        $this->actingAs(User::factory()->create());
        $this->app->instance('env', 'production');
        $this->withSession(['_token' => 'valid-token'])->post(route('reports.expenses.store'), ['_token' => 'invalid-token'])->assertStatus(419);
        $this->assertDatabaseHas('activity_logs', ['route_name' => 'reports.expenses.store', 'status_code' => 419, 'result' => 'denied']);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_stock_changes_have_before_and_after_values(): void
    {
        $product = Product::factory()->withStock(10)->create();
        $this->actingAs(User::factory()->create())->patch(route('products.update', $product), ['quantity' => 4, 'stock_minimum' => 2])->assertRedirect();
        $log = ActivityLog::where('route_name', 'products.update')->firstOrFail();
        $change = collect($log->changes)->firstWhere('entity', 'ProductStock');
        $this->assertSame(10, (int) $change['before']['quantity']);
        $this->assertSame(4, (int) $change['after']['quantity']);
    }

    public function test_password_reset_is_logged_without_token_or_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123'])->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $log = ActivityLog::where('action', 'password_reset')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertStringNotContainsString($token, $log->toJson());
        $this->assertStringNotContainsString('New-password-123', $log->toJson());
    }

    public function test_audit_storage_failure_is_reported_without_breaking_the_business_request(): void
    {
        $user = User::factory()->create();
        Log::spy();
        Event::listen('eloquent.creating: App\\Models\\ActivityLog', function (): void {
            throw new \RuntimeException('Audit unavailable');
        });
        $this->actingAs($user)->post(route('reports.expenses.store'), ['category' => 'Konsumsi', 'amount' => 1000, 'expense_date' => now()->toDateString()])->assertRedirect();
        $this->assertDatabaseCount('expenses', 1);
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_end_date_and_shared_pagination_options_can_be_used_independently(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('activity-logs.index', ['end_date' => now()->toDateString(), 'per_page' => 6]))->assertOk();
    }

    public function test_model_rejects_deleting_an_existing_audit_entry(): void
    {
        $log = ActivityLog::factory()->create();
        $this->expectException(LogicException::class);
        $log->delete();
    }
}
