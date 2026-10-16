<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessSettingRequest;
use App\Models\BusinessSetting;
use App\Services\ImageStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessSettingController extends Controller
{
    public function update(BusinessSettingRequest $request, ImageStorageService $images): RedirectResponse
    {
        $images->replace($request->file('logo'), $request->boolean('remove_logo'), 'branding/logos', function (?string $path, bool $change) use ($request): array {
            return DB::transaction(function () use ($request, $path, $change): array {
                BusinessSetting::firstOrCreate(['id' => 1], ['business_name' => 'Bintang Timur']);
                $settings = BusinessSetting::lockForUpdate()->findOrFail(1);
                $previous = $settings->logo_path;
                $settings->business_name = trim($request->validated('business_name'));
                if ($change) {
                    $settings->logo_path = $path;
                }
                $settings->save();

                return [$settings, $previous];
            });
        });

        return redirect()->route('profile.edit')->with('success', 'Identitas usaha berhasil diperbarui.');
    }

    public function logo(): StreamedResponse
    {
        $settings = BusinessSetting::current();
        abort_unless($settings->logo_path && Storage::disk('local')->exists($settings->logo_path), 404);

        return Storage::disk('local')->response($settings->logo_path, null, ['Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}
