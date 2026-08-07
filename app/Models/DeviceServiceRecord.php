<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceServiceRecord extends Model
{
    public const TYPES = ['warranty_claim', 'repair', 'return'];

    public const STATUSES = ['received', 'diagnosing', 'waiting_parts', 'in_progress', 'ready', 'completed', 'cancelled'];

    protected $fillable = ['service_number', 'device_unit_id', 'sale_id', 'customer_id', 'branch_id', 'received_by', 'service_type', 'status', 'warranty_covered', 'issue', 'diagnosis', 'resolution', 'technician', 'amount', 'received_at', 'completed_at', 'notes'];

    protected $casts = ['warranty_covered' => 'boolean', 'amount' => 'decimal:2', 'received_at' => 'datetime', 'completed_at' => 'datetime'];

    public function deviceUnit(): BelongsTo
    {
        return $this->belongsTo(DeviceUnit::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
