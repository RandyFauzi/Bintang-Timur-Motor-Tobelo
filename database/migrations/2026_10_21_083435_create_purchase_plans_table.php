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
        Schema::create('purchase_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->string('reference')->unique();
            $table->string('name');
            $table->date('planned_on');
            $table->json('items');
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_plans');
    }
};
