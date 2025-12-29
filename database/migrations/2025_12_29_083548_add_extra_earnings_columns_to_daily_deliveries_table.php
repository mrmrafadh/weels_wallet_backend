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
        Schema::table('daily_deliveries', function (Blueprint $table) {
            // Add the new columns after 'admin_commission' for better organization
            $table->decimal('total_deliveryfee', 10, 2)->default(0.00)->after('admin_commission');
            $table->decimal('total_restaurantcomm', 10, 2)->default(0.00)->after('total_deliveryfee');
            $table->decimal('total_svc', 10, 2)->default(0.00)->after('total_restaurantcomm');
            $table->decimal('admin_comm_delivery', 10, 2)->default(0.00)->after('total_svc');
            $table->decimal('admin_comm_restaurantcomm', 10, 2)->default(0.00)->after('total_svc');
            $table->decimal('admin_comm_svc', 10, 2)->default(0.00)->after('admin_comm_delivery');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'total_deliveryfee',
                'total_restaurantcomm',
                'total_svc',
                'admin_comm_delivery',
                'admin_comm_restaurantcomm',
                'admin_comm_svc'
            ]);
        });
    }
};
