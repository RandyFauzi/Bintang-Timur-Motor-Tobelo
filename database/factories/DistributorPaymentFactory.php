<?php

namespace Database\Factories;

use App\Models\DistributorInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DistributorPaymentFactory extends Factory
{
    public function definition(): array
    {
        return ['distributor_invoice_id' => DistributorInvoice::factory(), 'request_id' => (string) Str::uuid(), 'paid_on' => now()->toDateString(), 'amount_cents' => 1000000, 'method' => 'transfer', 'created_by' => User::factory()];
    }
}
