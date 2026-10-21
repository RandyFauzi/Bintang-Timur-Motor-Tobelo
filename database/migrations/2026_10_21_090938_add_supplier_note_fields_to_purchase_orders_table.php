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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->boolean('note_issued')->default(false);
            $table->string('supplier_note_number')->nullable();
            $table->date('supplier_note_on')->nullable();
            $table->timestamp('shipment_finalized_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['note_issued', 'supplier_note_number', 'supplier_note_on', 'shipment_finalized_at']);
        });
    }
};
