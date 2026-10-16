<?php

namespace App\Models;

use Database\Factories\BusinessSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;

class BusinessSetting extends Model
{
    /** @use HasFactory<BusinessSettingFactory> */
    use HasFactory;

    protected $fillable = ['id', 'business_name'];

    public function logoUrl(): string
    {
        return $this->logo_path
            ? route('business.logo', ['v' => substr(hash('sha256', $this->logo_path), 0, 12)])
            : Vite::asset('resources/images/brand/logo.svg');
    }

    public static function current(): self
    {
        $settings = request()->attributes->get('business.settings');
        if (! $settings) {
            $settings = self::find(1) ?? new self(['business_name' => 'Bintang Timur']);
            request()->attributes->set('business.settings', $settings);
        }

        return $settings;
    }
}
