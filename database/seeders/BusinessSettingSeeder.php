<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use Illuminate\Database\Seeder;

class BusinessSettingSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSetting::firstOrCreate(['id' => 1], ['business_name' => 'Bintang Timur']);
    }
}
