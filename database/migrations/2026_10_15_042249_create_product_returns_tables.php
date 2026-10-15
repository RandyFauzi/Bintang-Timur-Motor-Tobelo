<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_returns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('request_id')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->json('product_snapshot');
            $table->unsignedInteger('quantity');
            $table->string('note_status', 20)->default('pending');
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'created_at']);
        });

        Schema::create('product_return_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('product_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name');
            $table->string('action', 20);
            $table->integer('stock_delta')->default(0);
            $table->json('before_state')->nullable();
            $table->json('after_state');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_return_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_return_events');
        Schema::dropIfExists('product_returns');
    }
};
