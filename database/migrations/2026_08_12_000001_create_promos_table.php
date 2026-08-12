<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promos')) {
            Schema::create('promos', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique()->nullable();
                $table->text('description')->nullable();
                $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
                $table->decimal('discount_value', 10, 2)->default(0);
                $table->enum('applies_to', ['all', 'specific_products', 'specific_categories'])->default('all');
                $table->decimal('minimum_purchase', 10, 2)->nullable();
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('uses_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['is_active', 'starts_at', 'expires_at']);
                $table->index('created_by');
            });
        }

        if (! Schema::hasTable('promo_products')) {
            Schema::create('promo_products', function (Blueprint $table) {
                $table->foreignId('promo_id')->constrained('promos')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->primary(['promo_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('promo_categories')) {
            Schema::create('promo_categories', function (Blueprint $table) {
                $table->foreignId('promo_id')->constrained('promos')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->primary(['promo_id', 'category_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_categories');
        Schema::dropIfExists('promo_products');
        Schema::dropIfExists('promos');
    }
};
