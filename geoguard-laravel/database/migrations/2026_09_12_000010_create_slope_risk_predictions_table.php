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
        Schema::create('slope_risk_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('monitoring_nodes')->cascadeOnDelete();
            $table->decimal('angular_velocity', 8, 4)->nullable();
            $table->decimal('inv_velocity', 8, 4)->nullable();
            $table->string('risk_level');
            $table->timestamp('estimated_collapse_time')->nullable();
            $table->decimal('insar_displacement_rate', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slope_risk_predictions');
    }
};
