<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DeviceUnit;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeviceUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $branchId = $user->isSuperAdmin() ? $request->integer('branch_id') : $user->branch_id;

        $query = DeviceUnit::query()->with(['product:id,name,barcode,category_id', 'branch:id,name', 'supplier:id,name', 'saleItem.sale:id,receipt_number,customer_id,customer_name'])
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim().'%';
                $q->where(fn ($inner) => $inner->where('imei', 'like', $term)->orWhere('imei_2', 'like', $term)->orWhere('serial_number', 'like', $term)->orWhereHas('product', fn ($p) => $p->where('name', 'like', $term)));
            })
            ->latest();

        $units = $query->paginate(25)->withQueryString()->through(fn (DeviceUnit $unit) => $this->mapUnit($unit));
        $scope = DeviceUnit::query()->when(! $user->isSuperAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $tagUnits = (clone $scope)
            ->with(['product:id,name,barcode,category_id', 'branch:id,name', 'supplier:id,name', 'saleItem.sale:id,receipt_number,customer_id,customer_name'])
            ->where(fn ($q) => $q->whereNotNull('imei')->orWhereNotNull('imei_2')->orWhereNotNull('serial_number'))
            ->latest()
            ->limit(1000)
            ->get()
            ->map(fn (DeviceUnit $unit) => $this->mapUnit($unit))
            ->values();

        return Inertia::render('DeviceUnits/Index', [
            'units' => $units,
            'tagUnits' => $tagUnits,
            'stats' => [
                'total' => (clone $scope)->count(),
                'available' => (clone $scope)->where('status', 'available')->count(),
                'sold' => (clone $scope)->where('status', 'sold')->count(),
                'in_service' => (clone $scope)->where('status', 'in_service')->count(),
                'warranty_active' => (clone $scope)->where('status', 'sold')->whereDate('warranty_expires_at', '>=', today())->count(),
            ],
            'products' => Product::active()->where('product_type', 'standard')->orderBy('name')->get(['id', 'name', 'barcode', 'category_id', 'warranty_months']),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $user->isSuperAdmin() ? Branch::active()->orderBy('name')->get(['id', 'name']) : Branch::whereKey($user->branch_id)->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'status', 'branch_id'),
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'imei' => ['nullable', 'digits:15', 'unique:device_units,imei', 'unique:device_unit_identifiers,value'],
            'imei_2' => ['nullable', 'digits:15', 'different:imei', 'unique:device_units,imei_2', 'unique:device_unit_identifiers,value'],
            'serial_number' => ['nullable', 'string', 'max:100', 'unique:device_units,serial_number', 'unique:device_unit_identifiers,value'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'acquired_at' => ['nullable', 'date'],
            'warranty_months' => ['required', 'integer', 'between:0,120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! $validated['imei'] && ! $validated['serial_number']) {
            return back()->withErrors(['imei' => 'Enter an IMEI or serial number.'])->withInput();
        }
        $this->assertBranchAccess((int) $validated['branch_id']);

        DB::transaction(function () use ($validated, $user) {
            $unit = DeviceUnit::create($validated + ['status' => 'available']);
            $this->syncIdentifiers($unit);
            Product::whereKey($validated['product_id'])->update(['track_serials' => true, 'warranty_months' => $validated['warranty_months']]);
            $stock = ProductStock::firstOrCreate(
                ['product_id' => $validated['product_id'], 'branch_id' => $validated['branch_id']],
                ['stock' => 0, 'capital' => $validated['cost'] ?? 0, 'markup' => 0, 'updated_by' => $user->id]
            );
            $stock->increment('stock');
            if (isset($validated['cost'])) {
                $stock->update(['capital' => $validated['cost'], 'updated_by' => $user->id]);
            }
            $this->log('device_unit_registered', "Registered {$unit->identifier}", $unit->id);
        });

        return back()->with('success', 'Serialized unit registered and added to stock.');
    }

    public function update(Request $request, DeviceUnit $deviceUnit): RedirectResponse
    {
        $this->assertBranchAccess($deviceUnit->branch_id);
        if ($deviceUnit->status === 'sold') {
            return back()->withErrors(['error' => 'Sold unit identity cannot be changed.']);
        }
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'imei' => ['nullable', 'digits:15', Rule::unique('device_units', 'imei')->ignore($deviceUnit)],
            'imei_2' => ['nullable', 'digits:15', 'different:imei', Rule::unique('device_units', 'imei_2')->ignore($deviceUnit)],
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique('device_units', 'serial_number')->ignore($deviceUnit)],
            'cost' => ['nullable', 'numeric', 'min:0'], 'acquired_at' => ['nullable', 'date'],
            'warranty_months' => ['required', 'integer', 'between:0,120'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! $validated['imei'] && ! $validated['serial_number']) {
            return back()->withErrors(['imei' => 'Enter an IMEI or serial number.']);
        }
        $identifiers = array_values(array_filter([$validated['imei'] ?? null, $validated['imei_2'] ?? null, $validated['serial_number'] ?? null]));
        $identifierTaken = DB::table('device_unit_identifiers')->whereIn('value', $identifiers)->where('device_unit_id', '!=', $deviceUnit->id)->exists();
        if ($identifierTaken) {
            return back()->withErrors(['imei' => 'That IMEI or serial number is already assigned to another unit.']);
        }
        DB::transaction(function () use ($deviceUnit, $validated) {
            $deviceUnit->update($validated);
            $this->syncIdentifiers($deviceUnit);
        });

        return back()->with('success', 'Unit details updated.');
    }

    public function transfer(Request $request, DeviceUnit $deviceUnit): RedirectResponse
    {
        $this->assertBranchAccess($deviceUnit->branch_id);
        $validated = $request->validate(['branch_id' => ['required', 'exists:branches,id', Rule::notIn([$deviceUnit->branch_id])]]);
        if ($deviceUnit->status !== 'available') {
            return back()->withErrors(['error' => 'Only available units can be transferred.']);
        }

        DB::transaction(function () use ($deviceUnit, $validated) {
            $source = ProductStock::where('product_id', $deviceUnit->product_id)->where('branch_id', $deviceUnit->branch_id)->lockForUpdate()->firstOrFail();
            if ($source->stock < 1) {
                throw ValidationException::withMessages(['branch_id' => 'Source branch has no stock available.']);
            }
            $destination = ProductStock::firstOrCreate(['product_id' => $deviceUnit->product_id, 'branch_id' => $validated['branch_id']], ['stock' => 0, 'capital' => $source->capital, 'markup' => $source->markup, 'updated_by' => Auth::id()]);
            $source->decrement('stock');
            $destination->increment('stock');
            $deviceUnit->update(['branch_id' => $validated['branch_id']]);
            $this->log('device_unit_transferred', "Transferred {$deviceUnit->identifier}", $deviceUnit->id);
        });

        return back()->with('success', 'Unit transferred successfully.');
    }

    public function destroy(DeviceUnit $deviceUnit): RedirectResponse
    {
        $this->assertBranchAccess($deviceUnit->branch_id);
        if ($deviceUnit->status !== 'available' || $deviceUnit->serviceRecords()->exists()) {
            return back()->withErrors(['error' => 'Only unused available units can be deleted.']);
        }
        DB::transaction(function () use ($deviceUnit) {
            ProductStock::where('product_id', $deviceUnit->product_id)->where('branch_id', $deviceUnit->branch_id)->lockForUpdate()->first()?->decrement('stock');
            $deviceUnit->delete();
        });

        return back()->with('success', 'Unit removed from inventory.');
    }

    private function assertBranchAccess(int $branchId): void
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin() || $user->branch_id === $branchId, 403);
    }

    private function log(string $action, string $description, int $subjectId): void
    {
        if (! class_exists(ActivityLog::class)) {
            return;
        }
        ActivityLog::create(['user_id' => Auth::id(), 'action' => $action, 'properties' => ['description' => $description], 'subject_type' => DeviceUnit::class, 'subject_id' => $subjectId]);
    }

    private function syncIdentifiers(DeviceUnit $unit): void
    {
        DB::table('device_unit_identifiers')->where('device_unit_id', $unit->id)->delete();
        $now = now();
        $rows = collect(['imei' => $unit->imei, 'imei_2' => $unit->imei_2, 'serial_number' => $unit->serial_number])
            ->filter()->map(fn ($value, $kind) => ['device_unit_id' => $unit->id, 'kind' => $kind, 'value' => $value, 'created_at' => $now, 'updated_at' => $now])->values()->all();
        if ($rows) {
            DB::table('device_unit_identifiers')->insert($rows);
        }
    }

    private function mapUnit(DeviceUnit $unit): array
    {
        return [
            'id' => $unit->id, 'product_id' => $unit->product_id, 'product_name' => $unit->product?->name, 'product_category_id' => $unit->product?->category_id,
            'branch_id' => $unit->branch_id, 'branch_name' => $unit->branch?->name, 'supplier_id' => $unit->supplier_id,
            'supplier_name' => $unit->supplier?->name, 'imei' => $unit->imei, 'imei_2' => $unit->imei_2,
            'serial_number' => $unit->serial_number, 'identifier' => $unit->identifier, 'status' => $unit->status,
            'cost' => $unit->cost ? (float) $unit->cost : null, 'acquired_at' => $unit->acquired_at?->toDateString(),
            'sold_at' => $unit->sold_at?->toIso8601String(), 'warranty_months' => $unit->warranty_months,
            'warranty_expires_at' => $unit->warranty_expires_at?->toDateString(), 'warranty_status' => $unit->warranty_status,
            'receipt_number' => $unit->saleItem?->sale?->receipt_number, 'customer_name' => $unit->saleItem?->sale?->customer_name,
            'notes' => $unit->notes,
        ];
    }
}
