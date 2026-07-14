<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'status')) {
                $table->string('status', 20)->default('active')->after('stock');
                $table->index('status');
            }
            if (!Schema::hasColumn('products', 'featured')) {
                $table->boolean('featured')->default(false)->after('status');
            }
            if (!Schema::hasColumn('products', 'sku')) {
                $table->string('sku', 50)->nullable()->unique()->after('featured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'sku')) {
                $table->dropUnique(['sku']);
                $table->dropColumn('sku');
            }
            if (Schema::hasColumn('products', 'featured')) {
                $table->dropColumn('featured');
            }
            if (Schema::hasColumn('products', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
        });
    }
};
