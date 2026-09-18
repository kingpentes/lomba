<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('telemetry_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('monitoring_nodes')->onDelete('cascade');
            $table->timestamp('recorded_at');
            $table->float('acc_x');
            $table->float('acc_y');
            $table->float('acc_z');
            $table->float('pitch');
            $table->float('roll');
            $table->float('vibration_freq');
            $table->float('ppv_value');
            $table->string('ai_classification');
            $table->timestamps();
            
            $table->index(['node_id', 'recorded_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('telemetry_logs');
    }
};
