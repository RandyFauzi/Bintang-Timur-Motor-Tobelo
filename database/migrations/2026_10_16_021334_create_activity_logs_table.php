<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('actor_name');
            $table->string('actor_role', 30)->nullable();
            $table->string('action', 30);
            $table->string('module', 80);
            $table->string('route_name')->nullable();
            $table->string('method', 10);
            $table->string('route_path');
            $table->unsignedSmallInteger('status_code');
            $table->string('result', 20);
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->json('changes');
            $table->json('metadata');
            $table->timestamp('occurred_at', 6);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['module', 'occurred_at']);
            $table->index(['result', 'occurred_at']);
            $table->index(['occurred_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
