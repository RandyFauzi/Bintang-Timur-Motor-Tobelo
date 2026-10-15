<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1);
        });
        Schema::create('sale_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name');
            $table->text('note')->nullable();
            $table->json('before_state');
            $table->json('after_state');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_corrections');
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('version'));
    }
};
