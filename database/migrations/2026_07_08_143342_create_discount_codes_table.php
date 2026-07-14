<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->unsignedTinyInteger('percentage');            // 1-99
            $table->timestamp('expires_at')->nullable();
            $table->decimal('min_order_amount', 8, 2)->nullable();
            $table->unsignedInteger('max_uses')->nullable();       // total
            $table->unsignedInteger('max_uses_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['code', 'active']);
        });

        // Registro de cada uso: quién usó qué y en qué pedido
        Schema::create('discount_code_uses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_discounted', 8, 2);
            $table->timestamp('used_at');
            $table->timestamps();

            $table->index(['discount_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_code_uses');
        Schema::dropIfExists('discount_codes');
    }
};
