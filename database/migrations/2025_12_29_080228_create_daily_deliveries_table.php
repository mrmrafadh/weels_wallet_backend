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
        Schema::create('daily_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->onDelete('cascade');
            $table->date('delivery_date');
            $table->longText('records_json'); // Stores the list of deliveries as JSON
            $table->decimal('total_earnings', 10, 2); // Sum of all fees (for quick math)
            $table->decimal('actual_earnings', 10, 2); // Sum of all fees (for quick math)
            $table->decimal('admin_commission', 10, 2); // Sum of all fees (for quick math)
            $table->string('status')->default('pending'); // 'pending' or 'paid'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_deliveries');
    }
};
