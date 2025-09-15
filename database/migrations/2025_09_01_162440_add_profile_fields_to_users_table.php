<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'profile_changes_count')) {
                $table->integer('profile_changes_count')->default(0)->after('country_code');
            }
            if (!Schema::hasColumn('users', 'last_profile_change')) {
                $table->timestamp('last_profile_change')->nullable()->after('profile_changes_count');
            }
            if (!Schema::hasColumn('users', 'profile_change_reset_date')) {
                $table->timestamp('profile_change_reset_date')->nullable()->after('last_profile_change');
            }
            if (!Schema::hasColumn('users', 'phone_verified')) {
                $table->boolean('phone_verified')->default(false)->after('profile_change_reset_date');
            }
            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('phone_verified');
            }

            // Índices para optimizar consultas
            $table->index(['phone_country_code', 'phone_number'], 'users_phone_index');
            $table->index(['country_code', 'postal_code'], 'users_location_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_phone_index');
            $table->dropIndex('users_location_index');

            $table->dropColumn([
                'profile_changes_count',
                'last_profile_change',
                'profile_change_reset_date',
                'phone_verified',
                'phone_verified_at'
            ]);
        });
    }
};
