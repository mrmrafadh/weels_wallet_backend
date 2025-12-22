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
    // 1. MODIFY EXISTING USERS TABLE
    // We use "table" instead of "create" to add columns to an existing table
    Schema::table('users', function (Blueprint $table) {
        $table->enum('role', ['admin', 'rider'])->default('rider')->after('name');
    });

    // 2. CREATE WALLETS (New Table)
    Schema::create('wallets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->decimal('balance', 15, 2)->default(0.00);      // Rider Money
        $table->decimal('cash_on_hand', 15, 2)->default(0.00); // Admin Liability
        $table->decimal('earnings', 15, 2)->default(0.00);     // Company Profit
        $table->timestamps();
    });

    // 3. CREATE TRANSACTIONS (New Table)
    Schema::create('transactions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
        $table->unsignedBigInteger('admin_id')->nullable();
        $table->decimal('amount', 15, 2);
        $table->string('type'); // recharge, deduct, refund, withdraw
        $table->text('description')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('all_tables');
    }
};
