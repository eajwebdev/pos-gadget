<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceUnit extends Model
{
    public const STATUSES = ['available', 'sold', 'in_service', 'damaged', 'returned'];

    protected $fillable = ['product_id', 'branch_id', 'supplier_id', 'sale_item_id', 'imei', 'imei_2', 'serial_number', 'status', 'cost', 'acquired_at', 'sold_at', 'warranty_months', 'warranty_expires_at', 'notes'];

    protected $casts = ['cost' => 'decimal:2', 'acquired_at' => 'date', 'sold_at' => 'datetime', 'warranty_expires_at' => 'date', 'warranty_months' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function serviceRecords(): HasMany
    {
        return $this->hasMany(DeviceServiceRecord::class);
    }

    public function getIdentifierAttribute(): string
    {
        return $this->imei ?: ($this->serial_number ?: (string) $this->imei_2);
    }

    public function getWarrantyStatusAttribute(): string
    {
        if (! $this->sold_at || ! $this->warranty_expires_at) {
            return 'not_started';
        }

        return $this->warranty_expires_at->isPast() ? 'expired' : 'active';
    }
}
