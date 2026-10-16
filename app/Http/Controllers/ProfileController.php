<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\ImageStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, ImageStorageService $images): RedirectResponse
    {
        $images->replace($request->file('avatar'), $request->boolean('remove_avatar'), 'avatars/users', function (?string $path, bool $change) use ($request): array {
            return DB::transaction(function () use ($request, $path, $change): array {
                $user = User::lockForUpdate()->findOrFail($request->user()->id);
                $previous = $user->avatar_path;
                $user->fill($request->safe()->only(['name', 'email']));
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }
                if ($change) {
                    $user->avatar_path = $path;
                }
                $user->save();

                return [$user, $previous];
            });
        });

        return Redirect::route('profile.edit')->with('status', 'profile-updated')->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
