<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 16)->unique()->nullable()->after('id');
            $table->string('tracking_number', 100)->nullable()->after('payment_intent');
            $table->string('shipping_carrier', 50)->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('shipping_carrier');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['order_number', 'tracking_number', 'shipping_carrier', 'shipped_at']);
        });
    }
};
