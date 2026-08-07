<?php

namespace App\Http\Controllers;

use App\Models\DeviceServiceRecord;
use App\Models\DeviceUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeviceServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $records = DeviceServiceRecord::with(['deviceUnit.product:id,name', 'customer:id,name,contact_number', 'branch:id,name', 'receivedBy:id,fname,lname'])
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->trim().'%';
                $q->where(fn ($x) => $x->where('service_number', 'like', $term)->orWhereHas('deviceUnit', fn ($u) => $u->where('imei', 'like', $term)->orWhere('serial_number', 'like', $term))->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
            })->latest('received_at')->paginate(25)->withQueryString();

        $records->through(fn ($r) => [
            'id' => $r->id, 'service_number' => $r->service_number, 'device_unit_id' => $r->device_unit_id,
            'device' => $r->deviceUnit?->product?->name, 'identifier' => $r->deviceUnit?->identifier,
            'customer' => $r->customer?->name, 'customer_phone' => $r->customer?->contact_number,
            'branch' => $r->branch?->name, 'service_type' => $r->service_type, 'status' => $r->status,
            'warranty_covered' => $r->warranty_covered, 'issue' => $r->issue, 'diagnosis' => $r->diagnosis,
            'resolution' => $r->resolution, 'technician' => $r->technician, 'amount' => (float) $r->amount,
            'received_at' => $r->received_at?->toIso8601String(), 'completed_at' => $r->completed_at?->toIso8601String(), 'notes' => $r->notes,
        ]);

        $units = DeviceUnit::with(['product:id,name', 'saleItem.sale.customer:id,name'])
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->whereIn('status', ['sold', 'returned'])->orderByDesc('sold_at')->get()->map(fn ($u) => [
                'id' => $u->id, 'label' => "{$u->product?->name} — {$u->identifier}", 'branch_id' => $u->branch_id,
                'sale_id' => $u->saleItem?->sale_id, 'customer_id' => $u->saleItem?->sale?->customer_id,
                'customer_name' => $u->saleItem?->sale?->customer?->name ?? $u->saleItem?->sale?->customer_name,
                'warranty_active' => $u->warranty_status === 'active', 'warranty_expires_at' => $u->warranty_expires_at?->toDateString(),
            ]);

        return Inertia::render('DeviceServices/Index', ['records' => $records, 'units' => $units, 'filters' => $request->only('search', 'status')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'device_unit_id' => ['required', 'exists:device_units,id'], 'service_type' => ['required', Rule::in(DeviceServiceRecord::TYPES)],
            'issue' => ['required', 'string', 'max:3000'], 'technician' => ['nullable', 'string', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        DB::transaction(function () use ($validated) {
            $unit = DeviceUnit::with('saleItem.sale')->lockForUpdate()->findOrFail($validated['device_unit_id']);
            $this->assertBranchAccess($unit->branch_id);
            if (! in_array($unit->status, ['sold', 'returned'], true)) {
                throw ValidationException::withMessages(['device_unit_id' => 'This unit is not eligible for after-sales service.']);
            }
            if ($unit->serviceRecords()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
                throw ValidationException::withMessages(['device_unit_id' => 'This unit already has an active service record.']);
            }
            DeviceServiceRecord::create($validated + [
                'service_number' => 'SRV-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'sale_id' => $unit->saleItem?->sale_id, 'customer_id' => $unit->saleItem?->sale?->customer_id,
                'branch_id' => $unit->branch_id, 'received_by' => Auth::id(), 'status' => 'received',
                'warranty_covered' => $validated['service_type'] === 'warranty_claim' && $unit->warranty_status === 'active',
                'received_at' => now(),
            ]);
            $unit->update(['status' => 'in_service']);
        });

        return back()->with('success', 'Service job created.');
    }

    public function update(Request $request, DeviceServiceRecord $deviceService): RedirectResponse
    {
        $this->assertBranchAccess($deviceService->branch_id);
        $validated = $request->validate([
            'status' => ['required', Rule::in(DeviceServiceRecord::STATUSES)], 'diagnosis' => ['nullable', 'string', 'max:3000'],
            'resolution' => ['nullable', 'string', 'max:3000'], 'technician' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        DB::transaction(function () use ($deviceService, $validated) {
            $terminal = in_array($validated['status'], ['completed', 'cancelled'], true);
            $deviceService->update($validated + ['completed_at' => $terminal ? now() : null]);
            $deviceService->deviceUnit()->update(['status' => $terminal ? ($deviceService->service_type === 'return' && $validated['status'] === 'completed' ? 'returned' : 'sold') : 'in_service']);
        });

        return back()->with('success', 'Service job updated.');
    }

    private function assertBranchAccess(int $branchId): void
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin() || $user->branch_id === $branchId, 403);
    }
}
