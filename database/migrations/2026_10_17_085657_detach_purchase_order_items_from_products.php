<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->index('purchase_order_id', 'po_items_order_index');
            $table->dropUnique(['purchase_order_id', 'product_id']);
            $table->dropConstrainedForeignId('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['purchase_order_id', 'product_id']);
            $table->dropIndex('po_items_order_index');
        });
    }
};
