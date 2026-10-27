<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\ImageStorageService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ActivityRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-business', fn (User $user): bool => $user->role === 'admin');
        Gate::define('manage-users', fn (User $user): bool => $user->role === 'admin');
        Gate::define('view-activity-logs', fn (User $user): bool => $user->role === 'admin');
        Gate::define('view-reports', fn (User $user): bool => $user->role === 'admin');
        Gate::define('manage-purchase-orders', fn (User $user): bool => $user->role === 'admin');
        Gate::define('manage-invoices', fn (User $user): bool => $user->role === 'admin');
        Gate::define('manage-shipments', fn (User $user): bool => $user->role === 'admin');
        Gate::define('view-sales-metrics', fn (User $user): bool => $user->role === 'admin');
        Gate::define('view-transactions', fn (User $user): bool => $user->role === 'admin');
        Gate::define('cancel-transactions', fn (User $user): bool => $user->role === 'admin');
        User::deleted(function (User $user): void {
            DB::afterCommit(fn () => app(ImageStorageService::class)->delete($user->avatar_path));
        });
        Event::listen(['eloquent.created: App\\Models\\*', 'eloquent.updated: App\\Models\\*', 'eloquent.deleted: App\\Models\\*', 'eloquent.restored: App\\Models\\*'], function (string $event, array $models): void {
            app(ActivityRecorder::class)->capture($event, $models[0]);
        });

        Event::listen(TransactionRolledBack::class, function (TransactionRolledBack $event): void {
            app(ActivityRecorder::class)->rollback($event->connection->transactionLevel());
        });
        Event::listen(TransactionCommitted::class, function (TransactionCommitted $event): void {
            app(ActivityRecorder::class)->commit($event->connection->transactionLevel());
        });
        Event::listen(Login::class, function (Login $event): void {
            app(ActivityRecorder::class)->authentication('login', $event->user);
        });
        Event::listen(Logout::class, function (Logout $event): void {
            app(ActivityRecorder::class)->authentication('logout', $event->user);
        });
        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            app(ActivityRecorder::class)->authentication('password_reset', $event->user);
        });
        Event::listen(Failed::class, function (Failed $event): void {
            app(ActivityRecorder::class)->authentication('login_failed');
        });

    }
}
