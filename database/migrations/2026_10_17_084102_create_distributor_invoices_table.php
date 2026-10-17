<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->date('due_on')->index();
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->string('supplier_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_invoices');
    }
};
