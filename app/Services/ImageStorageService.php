<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImageStorageService
{
    /** @return list<mixed> */
    public static function rules(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'];
    }

    /** @param Closure(?string, bool): array{0: Model, 1: ?string} $save */
    public function replace(?UploadedFile $upload, bool $remove, string $folder, Closure $save): Model
    {
        $path = null;
        try {
            if ($upload) {
                $path = $upload->store($folder, 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['image' => 'Gambar tidak dapat disimpan. Silakan coba kembali.']);
                }
            }
            [$model, $previous] = $save($path, $upload !== null || $remove);
        } catch (Throwable $exception) {
            $this->delete($path);
            throw $exception;
        }
        if (($upload || $remove) && $previous && $previous !== $path) {
            DB::afterCommit(fn () => $this->delete($previous));
        }

        return $model;
    }

    public function delete(?string $path): void
    {
        if ($path && (str_starts_with($path, 'avatars/users/') || str_starts_with($path, 'branding/logos/'))) {
            Storage::disk('local')->delete($path);
        }
    }
}
