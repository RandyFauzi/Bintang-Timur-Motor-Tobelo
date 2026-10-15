<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshops', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('work_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('competitor_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->restrictOnDelete();
            $table->date('observed_on');
            $table->unsignedTinyInteger('observed_hour');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['observed_on', 'workshop_id']);
        });
        Schema::create('competitor_observation_work_type', function (Blueprint $table): void {
            $table->foreignId('competitor_observation_id')->constrained(indexName: 'observation_work_type_observation_fk')->cascadeOnDelete();
            $table->foreignId('work_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->primary(['competitor_observation_id', 'work_type_id'], 'observation_work_type_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_observation_work_type');
        Schema::dropIfExists('competitor_observations');
        Schema::dropIfExists('work_types');
        Schema::dropIfExists('workshops');
    }
};
