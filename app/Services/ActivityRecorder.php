<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ActivityRecorder
{
    private bool $active = false;

    /** @var array{user_id:int, actor_name:string, actor_role:?string}|null */
    private ?array $actor = null;

    private ?string $authentication = null;

    /** @var list<array<string, mixed>> */
    private array $changes = [];

    private int $changeBytes = 0;

    private bool $truncated = false;

    public function begin(?User $user): void
    {
        $this->active = true;
        $this->actor = $user ? ['user_id' => $user->id, 'actor_name' => $user->name, 'actor_role' => $user->role] : null;
        $this->authentication = null;
        $this->changes = [];
        $this->truncated = false;
        $this->changeBytes = 0;
    }

    public function authentication(string $action, ?User $user = null): void
    {
        if (! $this->active) {
            return;
        }
        $this->authentication = $action;
        if ($user && $action !== 'login_failed') {
            $this->actor = ['user_id' => $user->id, 'actor_name' => $user->name, 'actor_role' => $user->role];
        }
    }

    public function capture(string $event, Model $model): void
    {
        if (! $this->active || $model instanceof ActivityLog) {
            return;
        }
        if (count($this->changes) >= 200) {
            $this->truncated = true;

            return;
        }
        $action = Str::between($event, 'eloquent.', ':');
        $attributes = $action === 'updated' ? Arr::except($model->getChanges(), ['updated_at', 'remember_token']) : Arr::except($model->getAttributes(), ['created_at', 'updated_at', 'remember_token']);
        if ($attributes === []) {
            return;
        }
        $before = $action === 'created' ? [] : Arr::only($model->getRawOriginal(), array_keys($attributes));
        $after = $action === 'deleted' ? [] : $attributes;
        $change = ['entity' => class_basename($model), 'id' => (string) $model->getKey(), 'event' => $action, 'before' => $this->sanitize($before), 'after' => $this->sanitize($after), 'level' => DB::transactionLevel()];
        $bytes = strlen(json_encode($change, JSON_INVALID_UTF8_SUBSTITUTE));
        if ($this->changeBytes + $bytes > 65536) {
            $this->truncated = true;

            return;
        }
        $this->changeBytes += $bytes;
        $this->changes[] = $change;

    }

    public function rollback(int $level): void
    {
        $this->changes = array_values(array_filter($this->changes, fn (array $change): bool => $change['level'] <= $level));
    }

    public function commit(int $level): void
    {
        foreach ($this->changes as &$change) {
            $change['level'] = min($change['level'], $level);
        }
    }

    /** @param array<string|int, mixed> $values @return array<string|int, mixed> */
    private function sanitize(array $values, int $depth = 0): array
    {
        if ($depth > 3) {
            return ['value' => '[RINGKASAN]'];
        }
        $result = [];
        foreach (array_slice($values, 0, 60, true) as $key => $value) {
            if (preg_match('/password|token|secret|authorization|cookie|email|phone|address|description|note|before_state|after_state/i', (string) $key)) {
                $result[$key] = '[DISAMARKAN]';
            } elseif (is_array($value)) {
                $result[$key] = $this->sanitize($value, $depth + 1);
            } elseif (is_string($value)) {
                $result[$key] = Str::limit($value, 500);
            } elseif (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @return array<string, string> */
    private function targets(Request $request): array
    {
        $targets = [];
        $parameters = array_merge($request->route()?->parameters() ?? [], $request->only(['invoice', 'edit', 'detail']));
        foreach ($parameters as $key => $value) {
            if (preg_match('/token|password|secret/i', (string) $key)) {
                continue;
            }
            $value = $value instanceof Model ? $value->getKey() : $value;
            if (is_scalar($value) && preg_match('/^(?:[0-9]{1,20}|[a-f0-9-]{36})$/i', (string) $value)) {
                $targets[$key] = (string) $value;
            }
        }

        return $targets;
    }

    public function finish(Request $request, int $status, float $startedAt): void
    {
        $this->active = false;
        if (! $request->isMethodSafe() && ! $this->authentication) {
            $this->authentication = match ($request->path()) {
                'login' => 'login', 'register' => 'register', 'forgot-password' => 'password_reset_request', 'reset-password' => 'password_reset', default => null
            };
        }
        if (! $this->actor && ! $this->authentication) {
            $this->changes = [];

            return;
        }
        $routeName = $request->route()?->getName() ?? '';
        if ($request->isMethodSafe() && $status < 400 && ! $this->authentication && $this->changes === [] && ! str_contains($routeName, 'export')) {
            $this->actor = null;

            return;
        }
        $requestId = (string) Str::uuid();
        $route = $request->route();
        $name = $route?->getName();
        $module = Str::before($name ?? '', '.');
        if ($module === 'reports') {
            $module = in_array($name, ['reports.finance', 'reports.expenses.store'], true) ? 'finance' : 'research';
        }
        if (in_array($module, ['password', 'verification'], true)) {
            $module = 'auth';
        }
        $module = isset(ActivityLog::MODULES[$module]) ? $module : ($this->authentication ? 'auth' : 'other');
        $validationFailed = ! $request->isMethodSafe() && $request->hasSession() && $request->session()->get('errors')?->any() && $request->session()->get('errors') !== $request->attributes->get('audit.initial_errors');
        $result = in_array($status, [401, 403, 419, 429], true) ? 'denied' : (($status >= 400 || $validationFailed || $this->authentication === 'login_failed') ? 'failed' : 'success');
        $action = $this->authentication ?? (str_contains($name ?? '', 'export') ? 'export' : match ($request->method()) {
            'GET','HEAD' => $status >= 400 ? ($result === 'denied' ? 'access_denied' : 'request_failed') : 'update', 'POST' => str_ends_with($name ?? '', '.store') ? 'create' : 'submit', 'PUT','PATCH' => 'update', 'DELETE' => 'delete', default => 'submit'
        });
        $action = match ($name) {
            'pos.checkout' => 'checkout', 'pos.scan' => 'scan', 'returns.cancel' => 'cancel', 'returns.bulk' => 'bulk', default => $action
        };
        try {
            ActivityLog::create(($this->actor ?? ['user_id' => null, 'actor_name' => 'Tamu', 'actor_role' => null]) + [
                'request_id' => $requestId, 'action' => $action, 'module' => $module, 'route_name' => $name,
                'method' => $request->method(), 'route_path' => Str::limit($route?->uri() ?? 'unknown', 255, ''),
                'status_code' => $status, 'result' => $result, 'ip_address' => null,
                'user_agent' => null,
                'duration_ms' => (int) min(4294967295, max(0, round((microtime(true) - $startedAt) * 1000))),
                'changes' => array_map(fn (array $change): array => Arr::except($change, ['level']), $this->changes),
                'metadata' => ['targets' => $this->targets($request), 'changes_truncated' => $this->truncated, 'parameters' => array_values(array_intersect(array_keys($request->all()), ['search', 'period', 'view', 'kind', 'low_stock', 'category', 'amount', 'expense_date', 'items', 'quantity', 'role', 'user_id', 'module', 'action', 'result', 'start_date', 'end_date', 'detail', 'edit', 'invoice']))],
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::critical('Audit activity could not be persisted.', ['request_id' => $requestId, 'route' => $name, 'exception_type' => $exception::class]);
        } finally {
            $this->changes = [];
            $this->actor = null;
            $this->authentication = null;
        }
    }
}
