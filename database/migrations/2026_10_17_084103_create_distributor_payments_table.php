<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_invoice_id')->constrained()->restrictOnDelete();
            $table->uuid('request_id')->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->date('paid_on');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_payments');
    }
};
