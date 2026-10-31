<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DistributorInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_order_id' => PurchaseOrder::factory(), 'reference' => 'INV-'.Str::uuid(), 'due_on' => now()->addMonthsNoOverflow(3)->toDateString(), 'total_cents' => 10000000];
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'purchase_order_id' => null,
            'distributor_id' => Distributor::factory(),
            'invoiced_on' => now()->toDateString(),
        ]);
    }
}
