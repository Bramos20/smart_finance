<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "KPLC Bill", "Water Bill"
            $table->string('category')->default('utilities'); // utilities, subscriptions, loans, etc.
            $table->decimal('amount', 18, 2)->unsigned();
            $table->string('currency', 3)->default('KES');
            $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->integer('due_day')->default(1); // Day of month (1-31) or week (1-7)
            $table->string('merchant_code')->nullable(); // Pesapal merchant code or phone number
            $table->string('account_number')->nullable(); // Account/reference number for the bill
            $table->boolean('auto_pay')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('next_due_date')->nullable();
            $table->timestamp('last_paid_at')->nullable();
            $table->json('meta')->nullable(); // Additional bill info
            $table->timestamps();
            
            $table->index(['user_id', 'active', 'auto_pay']);
            $table->index(['next_due_date', 'auto_pay']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bills');
    }
};