<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tables')) {
            Schema::create('tables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('table_number', 30);
                $table->string('section', 80)->nullable();
                $table->unsignedSmallInteger('capacity')->default(4);
                $table->string('status', 30)->default('available');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['branch_id', 'table_number', 'section']);
                $table->index(['branch_id', 'status']);
                $table->index('is_active');
            });
        }

        if (! Schema::hasTable('table_orders')) {
            Schema::create('table_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 40)->unique();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('table_id')->constrained('tables')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
                $table->unsignedSmallInteger('covers')->default(1);
                $table->string('customer_name')->nullable();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->string('status', 30)->default('open');
                $table->text('notes')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['branch_id', 'status']);
                $table->index(['table_id', 'status']);
                $table->index('opened_at');
            });
        }

        if (! Schema::hasTable('table_order_items')) {
            Schema::create('table_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('table_order_id')->constrained('table_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->unsignedBigInteger('bundle_table_order_item_id')->nullable();
                $table->boolean('is_bundle_component')->default(false);
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('price', 10, 2);
                $table->decimal('total', 12, 2);
                $table->string('status', 30)->default('pending');
                $table->text('kitchen_note')->nullable();
                $table->timestamps();

                $table->foreign('bundle_table_order_item_id')->references('id')->on('table_order_items')->nullOnDelete();
                $table->index('table_order_id');
                $table->index('product_id');
                $table->index('product_variant_id');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('table_order_items');
        Schema::dropIfExists('table_orders');
        Schema::dropIfExists('tables');
    }
};
