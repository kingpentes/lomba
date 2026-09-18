<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('monitoring_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_code')->unique();
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('elevation');
            $table->enum('status', ['STABLE', 'WARNING', 'CRITICAL', 'OFFLINE'])->default('STABLE');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('monitoring_nodes');
    }
};
