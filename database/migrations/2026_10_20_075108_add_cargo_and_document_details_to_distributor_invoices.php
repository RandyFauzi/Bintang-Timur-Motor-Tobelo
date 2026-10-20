<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments')) {
            throw new RuntimeException('Jalankan migrasi pelacakan kargo sebelum menghubungkan faktur.');
        }

        /** MySQL DDL may commit each added column before a later statement fails. */
        Schema::table('distributor_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('distributor_invoices', 'invoiced_on')) {
                $table->date('invoiced_on')->nullable();
            }
            if (! Schema::hasColumn('distributor_invoices', 'shipment_id')) {
                $table->foreignId('shipment_id')->nullable();
            }
            if (! Schema::hasColumn('distributor_invoices', 'discrepancy_notes')) {
                $table->text('discrepancy_notes')->nullable();
            }
            if (! Schema::hasColumn('distributor_invoices', 'claim_amount_cents')) {
                $table->unsignedBigInteger('claim_amount_cents')->default(0);
            }
        });

        $hasShipmentKey = collect(Schema::getForeignKeys('distributor_invoices'))
            ->contains(fn (array $key): bool => $key['columns'] === ['shipment_id']);
        if (! $hasShipmentKey) {
            Schema::table('distributor_invoices', function (Blueprint $table): void {
                $table->foreign('shipment_id')->references('id')->on('shipments')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('distributor_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipment_id');
            $table->dropColumn(['invoiced_on', 'discrepancy_notes', 'claim_amount_cents']);
        });
    }
};
