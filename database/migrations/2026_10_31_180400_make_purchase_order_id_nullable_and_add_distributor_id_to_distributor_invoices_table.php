<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('distributor_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('distributor_invoices', 'distributor_id')) {
                $table->foreignId('distributor_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            $table->foreignId('purchase_order_id')->nullable()->change();
        });

        // Backfill distributor_id from purchase_orders for existing invoices
        $invoices = DB::table('distributor_invoices')
            ->whereNotNull('purchase_order_id')
            ->whereNull('distributor_id')
            ->get(['id', 'purchase_order_id']);

        foreach ($invoices as $inv) {
            $distributorId = DB::table('purchase_orders')
                ->where('id', $inv->purchase_order_id)
                ->value('distributor_id');

            if ($distributorId) {
                DB::table('distributor_invoices')
                    ->where('id', $inv->id)
                    ->update(['distributor_id' => $distributorId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distributor_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('distributor_invoices', 'distributor_id')) {
                $table->dropConstrainedForeignId('distributor_id');
            }
            $table->foreignId('purchase_order_id')->nullable(false)->change();
        });
    }
};
