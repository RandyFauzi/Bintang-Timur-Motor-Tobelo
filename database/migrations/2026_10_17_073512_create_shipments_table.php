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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->unique();
            $table->foreignId('shipping_service_id')->constrained()->restrictOnDelete();
            $table->string('service_name');
            $table->unsignedSmallInteger('duration_days');
            $table->string('cargo_name');
            $table->string('vessel_name')->nullable();
            $table->string('origin_port');
            $table->string('destination_port');
            $table->text('destination_address')->nullable();
            $table->unsignedInteger('package_count')->default(1);
            $table->date('departed_on');
            $table->date('estimated_arrival_on');
            $table->text('notes')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['delivered_at', 'estimated_arrival_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
