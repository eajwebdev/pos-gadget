<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Branch is a physical store/location owned by a Supplier.
     * All operational data (stock, sales, cash, expenses, etc.)
     * is scoped to a branch — NOT directly to a supplier.
     *
     * business_type identifies which branch operation belongs to the location:
     *   store, service_center
     *
     * Stockrooms and warehouses are managed by the Warehouses module.
     *
     * Feature flags are auto-applied when business_type is set,
     * but can be overridden individually per branch.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable()->unique();
            $table->string('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('contact_person', 100)->nullable();
            $table->boolean('is_active')->default(true);

            // Business type and feature flags
            $table->string('business_type', 30)->default('store');
            $table->boolean('use_table_ordering')->default(false);
            $table->boolean('use_variants')->default(false);
            $table->boolean('use_expiry_tracking')->default(false);
            $table->boolean('use_recipe_system')->default(false);
            $table->boolean('use_bundles')->default(false);

            $table->timestamps();

            $table->index('supplier_id');
            $table->index('business_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
