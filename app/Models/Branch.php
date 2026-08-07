<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    // ── Business type constants ────────────────────────────────────
    const TYPE_STORE          = 'store';
    const TYPE_SERVICE_CENTER = 'service_center';
    const TYPE_WAREHOUSE      = 'warehouse';

    // Backward-compatible constants for older code/tests/data.
    const TYPE_GADGET_STORE      = self::TYPE_STORE;
    const TYPE_PHONE_STORE       = 'phone_store';
    const TYPE_TABLET_STORE      = 'tablet_store';
    const TYPE_LAPTOP_STORE      = 'laptop_store';
    const TYPE_ACCESSORIES_STORE = 'accessories_store';
    const TYPE_REPAIR_SERVICE    = self::TYPE_SERVICE_CENTER;
    const TYPE_RETAIL     = self::TYPE_STORE;
    const TYPE_CAFE       = 'cafe';
    const TYPE_RESTAURANT = 'restaurant';
    const TYPE_FOOD_STALL = 'food_stall';
    const TYPE_BAR        = 'bar';
    const TYPE_BAKERY     = 'bakery';
    const TYPE_PHARMACY   = 'pharmacy';
    const TYPE_SALON      = 'salon';
    const TYPE_LAUNDRY    = 'laundry';
    const TYPE_HARDWARE   = 'hardware';
    const TYPE_SCHOOL     = 'school';
    const TYPE_MIXED      = 'mixed';

    public static function businessTypes(): array
    {
        return [
            // ── Food & Beverage ───────────────────────────────────────
            self::TYPE_STORE          => 'Store / Retail Branch',
            self::TYPE_SERVICE_CENTER => 'Service / Repair Center',
            // ── Retail & Services ─────────────────────────────────────
            // ── Other ─────────────────────────────────────────────────
        ];
    }

    public static function normalizeBusinessType(?string $type): string
    {
        $type = trim((string) $type);

        if ($type === '') {
            return self::TYPE_STORE;
        }

        if (array_key_exists($type, self::businessTypes())) {
            return $type;
        }

        return match ($type) {
            'gadget_store', 'phone_store', 'tablet_store', 'laptop_store', 'accessories_store', 'warehouse',
            'retail', 'grocery', 'sari_sari', self::TYPE_CAFE, self::TYPE_RESTAURANT,
            self::TYPE_FOOD_STALL, self::TYPE_BAR, self::TYPE_BAKERY, self::TYPE_PHARMACY,
            self::TYPE_SALON, self::TYPE_LAUNDRY, self::TYPE_HARDWARE, self::TYPE_SCHOOL,
            self::TYPE_MIXED => self::TYPE_STORE,
            'repair_service' => self::TYPE_SERVICE_CENTER,
            default => self::TYPE_STORE,
        };
    }

    /**
     * Default feature flags per business type.
     * Applied automatically by booted() when business_type changes.
     */
    public static function defaultFlagsFor(string $type): array
    {
        return match (static::normalizeBusinessType($type)) {
            self::TYPE_STORE => [
                'use_table_ordering'  => false,
                'use_variants'        => true,
                'use_expiry_tracking' => false,
                'use_recipe_system'   => false,
                'use_bundles'         => true,
            ],
            self::TYPE_SERVICE_CENTER => [
                'use_table_ordering'  => false,
                'use_variants'        => false,
                'use_expiry_tracking' => false,
                'use_recipe_system'   => false,
                'use_bundles'         => true,
            ],
            default => [
                'use_table_ordering'  => false,
                'use_variants'        => true,
                'use_expiry_tracking' => false,
                'use_recipe_system'   => false,
                'use_bundles'         => true,
            ],
        };
    }

    protected $fillable = [
        'supplier_id',
        'name',
        'code',
        'address',
        'phone',
        'contact_person',
        'is_active',
        'business_type',
        'use_table_ordering',
        'use_variants',
        'use_expiry_tracking',
        'use_recipe_system',
        'use_bundles',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'use_table_ordering'  => 'boolean',
        'use_variants'        => 'boolean',
        'use_expiry_tracking' => 'boolean',
        'use_recipe_system'   => 'boolean',
        'use_bundles'         => 'boolean',
    ];

    protected $attributes = [
        'business_type'       => self::TYPE_STORE,
        'use_table_ordering'  => false,
        'use_variants'        => false,
        'use_expiry_tracking' => false,
        'use_recipe_system'   => false,
        'use_bundles'         => false,
    ];

    // ── Boot ───────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (Branch $branch) {
            $branch->business_type = static::normalizeBusinessType($branch->business_type);

            if ($branch->isDirty('business_type')) {
                $defaults = static::defaultFlagsFor($branch->business_type);
                foreach ($defaults as $flag => $value) {
                    if (!$branch->isDirty($flag)) {
                        $branch->{$flag} = $value;
                    }
                }
            }
        });
    }

    // ── Business Type Helpers ──────────────────────────────────────

    public function isRetail(): bool     { return static::normalizeBusinessType($this->business_type) === self::TYPE_STORE; }
    public function isCafe(): bool       { return false; }
    public function isRestaurant(): bool { return false; }
    public function isFoodStall(): bool  { return false; }
    public function isBar(): bool        { return false; }
    public function isBakery(): bool     { return false; }
    public function isPharmacy(): bool   { return false; }
    public function isSalon(): bool      { return false; }
    public function isLaundry(): bool    { return false; }
    public function isHardware(): bool   { return false; }
    public function isSchool(): bool     { return false; }
    public function isWarehouse(): bool  { return false; }
    public function isMixed(): bool      { return static::normalizeBusinessType($this->business_type) === self::TYPE_STORE; }

    public function getBusinessTypeLabelAttribute(): string
    {
        $type = static::normalizeBusinessType($this->business_type);

        return static::businessTypes()[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    // ── Feature Flag Helpers ───────────────────────────────────────

    public function usesTableOrdering(): bool  { return (bool) $this->use_table_ordering; }
    public function usesVariants(): bool       { return (bool) $this->use_variants; }
    public function usesExpiryTracking(): bool { return (bool) $this->use_expiry_tracking; }
    public function usesRecipeSystem(): bool   { return (bool) $this->use_recipe_system; }
    public function usesBundles(): bool        { return (bool) $this->use_bundles; }

    /**
     * All feature flags as an array — pass this to Inertia/Vue frontend.
     */
    public function getFeatureFlagsAttribute(): array
    {
        return [
            'table_ordering'  => $this->use_table_ordering,
            'variants'        => $this->use_variants,
            'expiry_tracking' => $this->use_expiry_tracking,
            'recipe_system'   => $this->use_recipe_system,
            'bundles'         => $this->use_bundles,
        ];
    }

    // ── System Settings Shortcut ───────────────────────────────────

    /**
     * Read a system setting scoped to this branch.
     * Shortcut for SystemSetting::get($key, $this->id, $default).
     *
     * Usage: $branch->setting('pos.require_cash_session')
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return SystemSetting::get($key, $this->id, $default);
    }

    /**
     * Read all settings for this branch (branch overrides global).
     */
    public function allSettings(): array
    {
        return SystemSetting::allForBranch($this->id);
    }

    // ── Relationships ──────────────────────────────────────────────

    public function supplier(): BelongsTo         { return $this->belongsTo(Supplier::class); }
    public function users(): HasMany              { return $this->hasMany(User::class); }
    public function productStocks(): HasMany      { return $this->hasMany(ProductStock::class); }
    public function sales(): HasMany              { return $this->hasMany(Sale::class); }
    public function orders(): HasMany             { return $this->hasMany(Order::class); }
    public function cashSessions(): HasMany       { return $this->hasMany(CashSession::class); }
    public function expenses(): HasMany           { return $this->hasMany(Expense::class); }
    public function dailySummaries(): HasMany     { return $this->hasMany(DailySummary::class); }
    public function goodsReceivedNotes(): HasMany { return $this->hasMany(GoodsReceivedNote::class); }
    public function diningTables(): HasMany       { return $this->hasMany(DiningTable::class); }
    public function tableOrders(): HasMany        { return $this->hasMany(TableOrder::class); }

    public function pettyCashFunds(): HasMany
    {
        return $this->hasMany(PettyCashFund::class);
    }

    public function activePettyCashFund()
    {
        return $this->hasOne(PettyCashFund::class)->where('status', 'active')->latestOfMany();
    }

    public function settings(): HasMany
    {
        return $this->hasMany(SystemSetting::class);
    }

    // ── Accessors ──────────────────────────────────────────────────

    public function getTotalStockAttribute(): int
    {
        return (int) $this->productStocks()->sum('stock');
    }

    public function getLowStockCountAttribute(): int
    {
        $threshold = SystemSetting::lowStockThreshold($this->id);
        return $this->productStocks()
            ->where('stock', '>', 0)
            ->where('stock', '<=', $threshold)
            ->count();
    }

    public function getExpiredStockCountAttribute(): int
    {
        if (!$this->use_expiry_tracking) return 0;
        return $this->productStocks()->whereDate('expiry_date', '<', now())->count();
    }

    public function getAvailableTablesCountAttribute(): int
    {
        if (!$this->use_table_ordering) return 0;
        return $this->diningTables()->where('status', 'available')->where('is_active', true)->count();
    }

    public function getOccupiedTablesCountAttribute(): int
    {
        if (!$this->use_table_ordering) return 0;
        return $this->diningTables()->where('status', 'occupied')->count();
    }

    public function getPettyCashBalanceAttribute(): float
    {
        return (float) ($this->activePettyCashFund?->current_balance ?? 0.00);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeActive($query)              { return $query->where('is_active', true); }
    public function scopeOfType($query, string $type){ return $query->where('business_type', $type); }
    public function scopeWithTableOrdering($query)   { return $query->where('use_table_ordering', true); }
    public function scopeWithRecipeSystem($query)    { return $query->where('use_recipe_system', true); }
    public function scopeWithBundles($query)         { return $query->where('use_bundles', true); }
}
