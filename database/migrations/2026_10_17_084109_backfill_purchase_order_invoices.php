<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_orders')->orderBy('id')->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                $total = DB::table('purchase_order_items')->where('purchase_order_id', $order->id)->selectRaw('SUM(ROUND(unit_price * 100) * quantity) AS cents')->value('cents');
                DB::table('distributor_invoices')->insertOrIgnore(['purchase_order_id' => $order->id, 'reference' => 'INV-'.$order->reference, 'due_on' => CarbonImmutable::parse($order->ordered_on)->addMonthsNoOverflow(3)->toDateString(), 'total_cents' => (int) $total, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void {}
};
