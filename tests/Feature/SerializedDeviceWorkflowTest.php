<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\DeviceServiceRecord;
use App\Models\DeviceUnit;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerializedDeviceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private Branch $otherBranch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $supplier = Supplier::create(['name' => 'NizPhone Supplier']);
        $this->branch = Branch::create(['supplier_id' => $supplier->id, 'name' => 'Main', 'code' => 'MAIN', 'business_type' => Branch::TYPE_STORE]);
        $this->otherBranch = Branch::create(['supplier_id' => $supplier->id, 'name' => 'Branch 2', 'code' => 'B2', 'business_type' => Branch::TYPE_STORE]);
        $this->user = User::create([
            'fname' => 'Carlo', 'lname' => 'Cashier', 'username' => 'cashier', 'password' => 'password',
            'role' => User::ROLE_CASHIER, 'branch_id' => $this->branch->id, 'access' => [User::MENU_POS, '40', '41'],
        ]);
        CashSession::create([
            'session_number' => 'SES-TEST-MAIN', 'user_id' => $this->user->id, 'branch_id' => $this->branch->id,
            'opening_cash' => 0, 'expected_cash' => 0, 'status' => 'open', 'opened_at' => now(),
        ]);
        $this->product = Product::create(['name' => 'iPhone 16 Pro', 'barcode' => 'IP16P', 'product_type' => 'standard', 'status' => 'active']);
    }

    public function test_register_transfer_sell_service_and_void_serialized_unit(): void
    {
        $this->actingAs($this->user)->post(route('device-units.store'), [
            'product_id' => $this->product->id, 'branch_id' => $this->branch->id,
            'imei' => '123456789012345', 'serial_number' => 'SN-IP16-001',
            'cost' => 50000, 'acquired_at' => today()->toDateString(), 'warranty_months' => 12,
        ])->assertSessionHasNoErrors();

        $unit = DeviceUnit::firstOrFail();
        $this->assertTrue($this->product->fresh()->track_serials);
        $this->assertSame(1, ProductStock::where('product_id', $this->product->id)->where('branch_id', $this->branch->id)->value('stock'));

        $this->actingAs($this->user)->post(route('device-units.transfer', $unit), ['branch_id' => $this->otherBranch->id])->assertSessionHasNoErrors();
        $this->assertSame($this->otherBranch->id, $unit->fresh()->branch_id);
        $this->assertSame(0, ProductStock::where('product_id', $this->product->id)->where('branch_id', $this->branch->id)->value('stock'));
        $this->assertSame(1, ProductStock::where('product_id', $this->product->id)->where('branch_id', $this->otherBranch->id)->value('stock'));

        $this->user->update(['branch_id' => $this->otherBranch->id]);
        CashSession::create([
            'session_number' => 'SES-TEST-001', 'user_id' => $this->user->id, 'branch_id' => $this->otherBranch->id,
            'opening_cash' => 0, 'expected_cash' => 0, 'status' => 'open', 'opened_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson(route('pos.barcode.lookup', ['barcode' => '123456789012345']))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('product.id', $this->product->id)
            ->assertJsonPath('device_unit_id', $unit->id);

        $this->actingAs($this->user)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 1, 'device_unit_id' => $unit->id]],
            'payment_method' => 'cash', 'payment_amount' => 60000,
        ])->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();
        $unit->refresh();
        $this->assertSame('sold', $unit->status);
        $this->assertNotNull($unit->sale_item_id);
        $this->assertEquals(today()->addMonthsNoOverflow(12)->toDateString(), $unit->warranty_expires_at->toDateString());
        $this->assertSame(0, ProductStock::where('product_id', $this->product->id)->where('branch_id', $this->otherBranch->id)->value('stock'));

        $this->actingAs($this->user)->post(route('device-services.store'), [
            'device_unit_id' => $unit->id, 'service_type' => 'warranty_claim',
            'issue' => 'Screen flickers', 'amount' => 0,
        ])->assertSessionHasNoErrors();
        $service = DeviceServiceRecord::firstOrFail();
        $this->assertTrue($service->warranty_covered);
        $this->assertSame('in_service', $unit->fresh()->status);

        $this->actingAs($this->user)->patch(route('device-services.update', $service), [
            'status' => 'completed', 'diagnosis' => 'Display connector', 'resolution' => 'Reseated connector',
            'technician' => 'Tech 1', 'amount' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertSame('sold', $unit->fresh()->status);

        $this->actingAs($this->user)->post(route('pos.void', $sale), ['reason' => 'Test cancellation'])->assertSessionHasNoErrors();
        $unit->refresh();
        $this->assertSame('available', $unit->status);
        $this->assertNull($unit->sale_item_id);
        $this->assertNull($unit->warranty_expires_at);
        $this->assertSame(1, ProductStock::where('product_id', $this->product->id)->where('branch_id', $this->otherBranch->id)->value('stock'));
    }

    public function test_serialized_product_cannot_be_sold_without_exact_unit(): void
    {
        $this->product->update(['track_serials' => true]);
        ProductStock::create(['product_id' => $this->product->id, 'branch_id' => $this->branch->id, 'stock' => 1, 'capital' => 50000, 'markup' => 20]);

        $this->actingAs($this->user)->post(route('pos.store'), [
            'items' => [['id' => $this->product->id, 'qty' => 1]], 'payment_method' => 'cash', 'payment_amount' => 60000,
        ])->assertSessionHasErrors('error');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(1, ProductStock::first()->stock);
    }
}
