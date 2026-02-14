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
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->string('likely_cause');
            $table->float('confidence');
            $table->text('reasoning')->nullable();
            $table->json('next_steps')->nullable();
            $table->json('ai_suggestions')->nullable();
            $table->json('correlated_signals')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            
            $table->index('status');
            $table->index('confidence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
