<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
        ], ['current_password.required' => 'Password saat ini wajib diisi.', 'current_password.current_password' => 'Password saat ini tidak sesuai.', 'password.required' => 'Password baru wajib diisi.', 'password.min' => 'Password baru minimal 8 karakter.', 'password.confirmed' => 'Konfirmasi password baru tidak cocok.', 'password.different' => 'Password baru harus berbeda dari password saat ini.']);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate(true);

        return redirect()->route('profile.edit')->with('status', 'password-updated')->with('success', 'Password berhasil diperbarui.');
    }
}
