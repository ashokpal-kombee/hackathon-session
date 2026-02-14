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
        Schema::create('log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->onDelete('cascade');
            $table->timestamp('log_timestamp');
            $table->string('severity')->default('info');
            $table->text('message');
            $table->text('raw_log')->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->timestamps();
            
            $table->index(['analysis_id', 'log_timestamp']);
            $table->index('severity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_entries');
    }
};
