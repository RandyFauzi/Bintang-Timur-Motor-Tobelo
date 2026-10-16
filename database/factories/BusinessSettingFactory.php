<?php

namespace Database\Factories;

use App\Models\BusinessSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessSetting> */
class BusinessSettingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['id' => 1, 'business_name' => fake()->company(), 'logo_path' => null];
    }
}
