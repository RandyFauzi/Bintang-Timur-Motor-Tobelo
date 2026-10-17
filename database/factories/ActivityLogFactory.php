<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityLog> */
class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return ['request_id' => fake()->uuid(), 'user_id' => User::factory(), 'actor_name' => 'Akun Uji', 'actor_role' => 'staff', 'action' => 'update', 'module' => 'products', 'route_name' => 'products.update', 'method' => 'PATCH', 'route_path' => 'products/{product}', 'status_code' => 200, 'result' => 'success', 'ip_address' => null, 'user_agent' => null, 'duration_ms' => 10, 'changes' => [], 'metadata' => [], 'occurred_at' => now()];
    }
}
