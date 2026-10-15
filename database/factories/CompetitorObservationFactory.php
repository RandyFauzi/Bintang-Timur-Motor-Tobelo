<?php

namespace Database\Factories;

use App\Models\CompetitorObservation;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompetitorObservation> */
class CompetitorObservationFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['workshop_id' => Workshop::factory(), 'observed_on' => now()->toDateString(), 'observed_hour' => 11, 'created_by' => User::factory()];
    }
}
