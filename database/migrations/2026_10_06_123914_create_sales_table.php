<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->string('status')->default('completed'); 
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('sold_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};