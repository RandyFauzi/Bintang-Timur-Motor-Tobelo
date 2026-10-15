<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\User;
use App\Services\ProductReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;

class ProductReturnSeeder extends Seeder
{
    public function run(ProductReturnService $returns): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }
        if (ProductReturn::withTrashed()->where('request_id', 'fe1dde34-4e77-43af-8485-dc15fef49341')->exists()) {
            return;
        }
        $user = User::query()->first();
        $product = Product::query()->where('is_active', true)->whereHas('stock', fn (Builder $query): Builder => $query->whereColumn('quantity', '>', 'reserved_quantity'))->first();
        if ($user && $product) {
            $returns->create(['request_id' => 'fe1dde34-4e77-43af-8485-dc15fef49341', 'product_id' => $product->id, 'quantity' => 1, 'note_status' => 'pending'], $user);
        }
    }
}
