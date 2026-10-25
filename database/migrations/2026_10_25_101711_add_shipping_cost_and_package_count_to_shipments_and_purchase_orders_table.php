<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipments', 'shipping_cost_cents')) {
                $table->unsignedBigInteger('shipping_cost_cents')->default(0)->after('package_count');
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_orders', 'package_count')) {
                $table->unsignedInteger('package_count')->nullable()->default(1)->after('supplier_note_total_cents');
            }
            if (! Schema::hasColumn('purchase_orders', 'shipping_cost_cents')) {
                $table->unsignedBigInteger('shipping_cost_cents')->default(0)->after('package_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            if (Schema::hasColumn('shipments', 'shipping_cost_cents')) {
                $table->dropColumn('shipping_cost_cents');
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $columns = array_filter(['package_count', 'shipping_cost_cents'], fn (string $column): bool => Schema::hasColumn('purchase_orders', $column));
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
