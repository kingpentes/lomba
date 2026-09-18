<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('monitoring_nodes')->onDelete('cascade');
            $table->timestamp('triggered_at');
            $table->string('trigger_type');
            $table->enum('severity', ['WARNING', 'CRITICAL']);
            $table->float('max_tilt_angle');
            $table->float('ai_confidence');
            $table->string('snapshot_path')->nullable();
            $table->enum('status', ['UNRESOLVED', 'ACKNOWLEDGED', 'RESOLVED'])->default('UNRESOLVED');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('incidents');
    }
};
