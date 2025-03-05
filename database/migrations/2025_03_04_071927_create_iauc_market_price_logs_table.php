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
        Schema::create('iauc_market_price_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('iauc_models')->onDelete('cascade');
            $table->enum('status', ['processing', 'success', 'failed'])->default('processing');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iauc_market_price_logs');
    }
};
