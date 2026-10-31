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
        if (! Schema::hasColumn('distributor_invoices', 'distributor_id')) {
            Schema::table('distributor_invoices', function (Blueprint $table): void {
                $table->foreignId('distributor_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        try {
            Schema::table('distributor_invoices', function (Blueprint $table): void {
                $table->foreignId('purchase_order_id')->nullable()->change();
            });
        } catch (Throwable) {
            try {
                DB::statement('ALTER TABLE distributor_invoices MODIFY purchase_order_id BIGINT UNSIGNED NULL');
            } catch (Throwable) {
                // Already nullable or unsupported
            }
        }

        // Backfill distributor_id from purchase_orders for existing invoices
        try {
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
        } catch (Throwable) {
            // Ignore if backfill fails
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('distributor_invoices', function (Blueprint $table): void {
                if (Schema::hasColumn('distributor_invoices', 'distributor_id')) {
                    $table->dropConstrainedForeignId('distributor_id');
                }
            });
        } catch (Throwable) {
            try {
                Schema::table('distributor_invoices', function (Blueprint $table): void {
                    if (Schema::hasColumn('distributor_invoices', 'distributor_id')) {
                        $table->dropColumn('distributor_id');
                    }
                });
            } catch (Throwable) {
                // Ignore
            }
        }

        try {
            Schema::table('distributor_invoices', function (Blueprint $table): void {
                $table->foreignId('purchase_order_id')->nullable(false)->change();
            });
        } catch (Throwable) {
            // Ignore
        }
    }
};
