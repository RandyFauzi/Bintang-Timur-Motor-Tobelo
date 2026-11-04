<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->timestamp('undone_at', 6)->nullable()->after('occurred_at');
            $table->unsignedBigInteger('undone_by_user_id')->nullable()->after('undone_at');
            $table->string('undone_by_name')->nullable()->after('undone_by_user_id');
            $table->unsignedBigInteger('undo_activity_log_id')->nullable()->after('undone_by_name');
            $table->index('undone_at');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex(['undone_at']);
            $table->dropColumn(['undone_at', 'undone_by_user_id', 'undone_by_name', 'undo_activity_log_id']);
        });
    }
};
