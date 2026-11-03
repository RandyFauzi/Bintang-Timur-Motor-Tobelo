<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('distributor_invoices')
            ->whereNull('received_on')
            ->whereNotNull('invoiced_on')
            ->whereNull('purchase_order_id')
            ->update(['received_on' => DB::raw('invoiced_on')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op for data backfill
    }
};
