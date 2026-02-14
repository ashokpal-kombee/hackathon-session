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
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->onDelete('cascade');
            $table->float('cpu_usage')->nullable();
            $table->float('memory_usage')->nullable();
            $table->float('db_latency')->nullable();
            $table->integer('requests_per_sec')->nullable();
            $table->json('additional_metrics')->nullable();
            $table->timestamps();
            
            $table->index('analysis_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_metrics');
    }
};
