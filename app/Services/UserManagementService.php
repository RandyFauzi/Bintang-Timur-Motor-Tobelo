<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(private ImageStorageService $images) {}

    /** @param array<string, mixed> $filters */
    public function query(array $filters): Builder
    {
        $query = User::query();
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn (Builder $query): Builder => $query->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }
        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return $query;
    }

    /** @param array{name: string, email: string, role: string, password?: ?string} $data */
    public function save(array $data, User $actor, ?User $target = null): User
    {
        try {
            return $this->images->replace($data['avatar'] ?? null, (bool) ($data['remove_avatar'] ?? false), 'avatars/users', function (?string $path, bool $change) use ($data, $actor, $target): array {
                return DB::transaction(function () use ($data, $actor, $target, $path, $change): array {
                    User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
                    abort_unless($actor->fresh()?->can('manage-users'), 403);
                    if ($target) {
                        $target = User::lockForUpdate()->findOrFail($target->id);
                        if ($target->id === $actor->id && $data['role'] !== 'admin') {
                            throw ValidationException::withMessages(['role' => 'Anda tidak dapat menurunkan hak akses akun sendiri.']);
                        }
                    }
                    $attributes = Arr::only($data, ['name', 'email', 'role']);
                    if (! empty($data['password'])) {
                        $attributes['password'] = $data['password'];
                        $attributes['remember_token'] = Str::random(60);
                    }
                    $target ??= new User;
                    if ($target->exists && $target->email !== $data['email']) {
                        $target->email_verified_at = null;
                    }
                    $target->fill(Arr::except($attributes, ['remember_token']));
                    if (isset($attributes['remember_token'])) {
                        $target->remember_token = $attributes['remember_token'];
                    }
                    $previous = $target->avatar_path;
                    if ($change) {
                        $target->avatar_path = $path;
                    }
                    $target->save();

                    return [$target, $previous];
                }, attempts: 3);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email sudah digunakan oleh pengguna lain.']);
        }
    }
}
