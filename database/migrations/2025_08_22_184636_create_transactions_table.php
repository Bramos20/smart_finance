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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // pesapal | flutterwave
            $table->string('direction'); // in | out
            $table->string('status'); // pending | succeeded | failed
            $table->unsignedDecimal('amount', 18, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('provider_ref')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id','provider','direction','status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
