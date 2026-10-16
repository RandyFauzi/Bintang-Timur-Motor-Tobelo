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
        return ['request_id' => fake()->uuid(), 'user_id' => User::factory(), 'actor_name' => 'Akun Uji', 'actor_role' => 'staff', 'action' => 'view', 'module' => 'dashboard', 'route_name' => 'dashboard', 'method' => 'GET', 'route_path' => 'dashboard', 'status_code' => 200, 'result' => 'success', 'ip_address' => '127.0.0.1', 'user_agent' => 'Test client', 'duration_ms' => 10, 'changes' => [], 'metadata' => [], 'occurred_at' => now()];
    }
}
