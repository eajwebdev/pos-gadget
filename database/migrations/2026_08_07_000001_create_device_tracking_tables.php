<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_serials')->default(false)->after('is_taxable');
            $table->unsignedSmallInteger('warranty_months')->default(12)->after('track_serials');
        });

        Schema::create('device_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('imei', 20)->nullable()->unique();
            $table->string('imei_2', 20)->nullable()->unique();
            $table->string('serial_number', 100)->nullable()->unique();
            $table->string('status', 30)->default('available');
            $table->decimal('cost', 12, 2)->nullable();
            $table->date('acquired_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->unsignedSmallInteger('warranty_months')->default(12);
            $table->date('warranty_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index(['product_id', 'status']);
        });

        // A single unique key space prevents an IMEI stored as SIM 1 on one unit
        // from being reused as SIM 2 on another unit.
        Schema::create('device_unit_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_unit_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('value', 100)->unique();
            $table->timestamps();
            $table->unique(['device_unit_id', 'kind']);
        });

        Schema::create('device_service_records', function (Blueprint $table) {
            $table->id();
            $table->string('service_number', 40)->unique();
            $table->foreignId('device_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('service_type', 30)->default('repair');
            $table->string('status', 30)->default('received');
            $table->boolean('warranty_covered')->default(false);
            $table->text('issue');
            $table->text('diagnosis')->nullable();
            $table->text('resolution')->nullable();
            $table->string('technician', 100)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamp('received_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index(['device_unit_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_service_records');
        Schema::dropIfExists('device_unit_identifiers');
        Schema::dropIfExists('device_units');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['track_serials', 'warranty_months']));
    }
};
