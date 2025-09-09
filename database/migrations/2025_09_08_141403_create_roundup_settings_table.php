<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roundup_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->enum('round_to', ['10', '50', '100'])->default('10'); // Round to nearest 10, 50, or 100
            $table->foreignId('savings_account_id')->constrained('accounts'); // Which account to credit the round-up
            $table->decimal('monthly_limit', 18, 2)->nullable(); // Optional monthly limit for round-ups
            $table->timestamps();
            
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roundup_settings');
    }
};