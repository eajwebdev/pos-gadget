<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\DeviceUnit;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RetailProductSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('code', 'ABC1')->first();

        if (! $branch) {
            $this->command->warn('Branch ABC1 not found. Skipping gadget catalog.');

            return;
        }

        $categories = Category::pluck('id', 'name');
        $supplier = Supplier::where('name', 'ABC Trading')->first() ?? Supplier::first();
        $productCount = 0;
        $variantCount = 0;
        $deviceUnitCount = 0;

        $syncDeviceIdentifiers = function (DeviceUnit $unit): void {
            DB::table('device_unit_identifiers')
                ->where('device_unit_id', $unit->id)
                ->delete();

            foreach ([
                'imei' => $unit->imei,
                'imei_2' => $unit->imei_2,
                'serial_number' => $unit->serial_number,
            ] as $kind => $value) {
                if (! $value) {
                    continue;
                }

                DB::table('device_unit_identifiers')->updateOrInsert(
                    ['value' => $value],
                    [
                        'device_unit_id' => $unit->id,
                        'kind' => $kind,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        };

        $makeProduct = function (array $data) use ($branch, $categories, &$productCount): Product {
            $product = Product::updateOrCreate(
                ['barcode' => $data['barcode']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? 'NizPhone Gadgets catalog item.',
                    'category_id' => $categories[$data['category']] ?? null,
                    'product_type' => $data['product_type'] ?? 'standard',
                    'status' => 'active',
                    'is_taxable' => $data['is_taxable'] ?? true,
                    'track_serials' => $data['track_serials'] ?? false,
                    'warranty_months' => $data['warranty_months'] ?? 12,
                    'duration_minutes' => $data['duration_minutes'] ?? null,
                ]
            );

            ProductStock::updateOrCreate(
                ['product_id' => $product->id, 'branch_id' => $branch->id],
                [
                    'stock' => $data['stock'],
                    'capital' => $data['capital'],
                    'markup' => $data['markup'],
                    'batch_number' => $data['batch_number'] ?? null,
                ]
            );

            $productCount++;

            return $product;
        };

        $makeVariants = function (Product $product, array $variants) use ($branch, &$variantCount): void {
            foreach ($variants as $index => $data) {
                $variant = ProductVariant::updateOrCreate(
                    ['sku' => $data['sku']],
                    [
                        'product_id' => $product->id,
                        'name' => $data['name'],
                        'barcode' => $data['barcode'] ?? null,
                        'attributes' => $data['attributes'] ?? [],
                        'extra_price' => $data['extra_price'] ?? 0,
                        'is_available' => true,
                        'sort_order' => $index,
                    ]
                );

                ProductVariantStock::updateOrCreate(
                    ['product_variant_id' => $variant->id, 'branch_id' => $branch->id],
                    [
                        'stock' => $data['stock'],
                        'capital' => $data['capital'],
                        'markup' => $data['markup'],
                        'batch_number' => $data['batch_number'] ?? null,
                    ]
                );

                $variantCount++;
            }
        };

        $seedDeviceUnits = function (Product $product, array $data, int $index) use ($branch, $supplier, $syncDeviceIdentifiers, &$deviceUnitCount): void {
            if (! ($data['track_serials'] ?? false)) {
                return;
            }

            $stock = (int) $data['stock'];
            $kind = $data['device_kind'] ?? 'phone';

            for ($unitIndex = 1; $unitIndex <= $stock; $unitIndex++) {
                $imei = $kind === 'phone'
                    ? '356789'.str_pad((string) (930000000 + ($index * 100) + $unitIndex), 9, '0', STR_PAD_LEFT)
                    : null;

                $unit = DeviceUnit::updateOrCreate(
                    [
                        'serial_number' => 'NIZ-'.$data['barcode'].'-'.str_pad((string) $unitIndex, 3, '0', STR_PAD_LEFT),
                    ],
                    [
                        'product_id' => $product->id,
                        'branch_id' => $branch->id,
                        'supplier_id' => $supplier?->id,
                        'sale_item_id' => null,
                        'imei' => $imei,
                        'imei_2' => null,
                        'status' => 'available',
                        'cost' => $data['capital'],
                        'acquired_at' => now()->subDays(45 - min($index, 30))->toDateString(),
                        'sold_at' => null,
                        'warranty_months' => $data['warranty_months'] ?? 12,
                        'warranty_expires_at' => null,
                        'notes' => 'Seeded serialized unit for NizPhone demo inventory.',
                    ]
                );

                $syncDeviceIdentifiers($unit);
                $deviceUnitCount++;
            }
        };

        $catalog = [
            // iPhones
            ['barcode' => 'NP1001', 'name' => 'iPhone 15 Pro Max', 'category' => 'iPhones', 'capital' => 66500, 'markup' => 18.05, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1002', 'name' => 'iPhone 15 Pro', 'category' => 'iPhones', 'capital' => 56000, 'markup' => 17.86, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1003', 'name' => 'iPhone 15', 'category' => 'iPhones', 'capital' => 43000, 'markup' => 18.60, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1004', 'name' => 'iPhone 14 Pro Max', 'category' => 'iPhones', 'capital' => 52000, 'markup' => 17.31, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1005', 'name' => 'iPhone 14', 'category' => 'iPhones', 'capital' => 35000, 'markup' => 20.00, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1006', 'name' => 'iPhone 13', 'category' => 'iPhones', 'capital' => 28000, 'markup' => 21.43, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1007', 'name' => 'iPhone 12', 'category' => 'iPhones', 'capital' => 21000, 'markup' => 23.81, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 6],
            ['barcode' => 'NP1008', 'name' => 'iPhone 11', 'category' => 'iPhones', 'capital' => 16000, 'markup' => 25.00, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 6],

            // Android phones
            ['barcode' => 'NP1101', 'name' => 'Samsung Galaxy S24 Ultra', 'category' => 'Android Phones', 'capital' => 60000, 'markup' => 18.33, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1102', 'name' => 'Samsung Galaxy S24', 'category' => 'Android Phones', 'capital' => 42000, 'markup' => 19.05, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1103', 'name' => 'Samsung Galaxy A55 5G', 'category' => 'Android Phones', 'capital' => 22000, 'markup' => 22.73, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1104', 'name' => 'Samsung Galaxy A35 5G', 'category' => 'Android Phones', 'capital' => 17000, 'markup' => 23.53, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1105', 'name' => 'OPPO Reno11 5G', 'category' => 'Android Phones', 'capital' => 21000, 'markup' => 23.81, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1106', 'name' => 'vivo V30 5G', 'category' => 'Android Phones', 'capital' => 23000, 'markup' => 21.74, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1107', 'name' => 'Xiaomi 14', 'category' => 'Android Phones', 'capital' => 39000, 'markup' => 20.51, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1108', 'name' => 'Redmi Note 13 Pro+ 5G', 'category' => 'Android Phones', 'capital' => 21000, 'markup' => 23.81, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1109', 'name' => 'realme 12 Pro+ 5G', 'category' => 'Android Phones', 'capital' => 23000, 'markup' => 21.74, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],
            ['barcode' => 'NP1110', 'name' => 'Huawei nova 12 SE', 'category' => 'Android Phones', 'capital' => 17000, 'markup' => 23.53, 'stock' => 4, 'track_serials' => true, 'device_kind' => 'phone', 'warranty_months' => 12],

            // Laptops
            ['barcode' => 'NP2001', 'name' => 'MacBook Air 13-inch M2', 'category' => 'Laptops', 'capital' => 52000, 'markup' => 17.31, 'stock' => 2, 'track_serials' => true, 'device_kind' => 'laptop', 'warranty_months' => 12],
            ['barcode' => 'NP2002', 'name' => 'MacBook Air 15-inch M3', 'category' => 'Laptops', 'capital' => 71000, 'markup' => 15.49, 'stock' => 2, 'track_serials' => true, 'device_kind' => 'laptop', 'warranty_months' => 12],
            ['barcode' => 'NP2003', 'name' => 'MacBook Pro 14-inch M3', 'category' => 'Laptops', 'capital' => 92000, 'markup' => 15.22, 'stock' => 2, 'track_serials' => true, 'device_kind' => 'laptop', 'warranty_months' => 12],
            ['barcode' => 'NP2004', 'name' => 'ASUS Vivobook 15', 'category' => 'Laptops', 'capital' => 28000, 'markup' => 25.00, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'laptop', 'warranty_months' => 12],
            ['barcode' => 'NP2005', 'name' => 'ASUS TUF Gaming A15', 'category' => 'Laptops', 'capital' => 52000, 'markup' => 17.31, 'stock' => 2, 'track_serials' => true, 'device_kind' => 'laptop', 'warranty_months' => 12],
            ['barcode' => 'NP2006', 'name' => 'Lenovo IdeaPad Slim 3', 'category' => 'Laptops', 'capital' => 26000, 'markup' => 26.92, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'laptop', 'warranty_months' => 12],
            ['barcode' => 'NP2007', 'name' => 'Acer Aspire 5', 'category' => 'Laptops', 'capital' => 30000, 'markup' => 23.33, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'laptop', 'warranty_months' => 12],
            ['barcode' => 'NP2008', 'name' => 'HP Pavilion 14', 'category' => 'Laptops', 'capital' => 34000, 'markup' => 22.06, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'laptop', 'warranty_months' => 12],

            // Tablets
            ['barcode' => 'NP3001', 'name' => 'iPad 10th Gen', 'category' => 'Tablets', 'capital' => 24000, 'markup' => 25.00, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'tablet', 'warranty_months' => 12],
            ['barcode' => 'NP3002', 'name' => 'iPad Air M2', 'category' => 'Tablets', 'capital' => 39000, 'markup' => 20.51, 'stock' => 2, 'track_serials' => true, 'device_kind' => 'tablet', 'warranty_months' => 12],
            ['barcode' => 'NP3003', 'name' => 'Samsung Galaxy Tab S9 FE', 'category' => 'Tablets', 'capital' => 25000, 'markup' => 24.00, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'tablet', 'warranty_months' => 12],
            ['barcode' => 'NP3004', 'name' => 'Xiaomi Pad 6', 'category' => 'Tablets', 'capital' => 17000, 'markup' => 23.53, 'stock' => 3, 'track_serials' => true, 'device_kind' => 'tablet', 'warranty_months' => 12],

            // Accessories
            ['barcode' => 'NP4001', 'name' => 'Apple 20W USB-C Power Adapter', 'category' => 'Chargers & Cables', 'capital' => 900, 'markup' => 66.67, 'stock' => 25],
            ['barcode' => 'NP4002', 'name' => 'USB-C to Lightning Cable 1m', 'category' => 'Chargers & Cables', 'capital' => 450, 'markup' => 77.78, 'stock' => 30],
            ['barcode' => 'NP4003', 'name' => 'USB-C to USB-C Cable 1m', 'category' => 'Chargers & Cables', 'capital' => 350, 'markup' => 85.71, 'stock' => 30],
            ['barcode' => 'NP4004', 'name' => 'MagSafe Wireless Charger', 'category' => 'Chargers & Cables', 'capital' => 1600, 'markup' => 56.25, 'stock' => 12],
            ['barcode' => 'NP4005', 'name' => '10,000mAh Power Bank', 'category' => 'Chargers & Cables', 'capital' => 850, 'markup' => 76.47, 'stock' => 18],
            ['barcode' => 'NP4101', 'name' => 'Tempered Glass for iPhone', 'category' => 'Cases & Protection', 'capital' => 80, 'markup' => 212.50, 'stock' => 60],
            ['barcode' => 'NP4102', 'name' => 'Clear Case for iPhone', 'category' => 'Cases & Protection', 'capital' => 120, 'markup' => 191.67, 'stock' => 45],
            ['barcode' => 'NP4103', 'name' => 'Shockproof Android Case', 'category' => 'Cases & Protection', 'capital' => 140, 'markup' => 185.71, 'stock' => 40],
            ['barcode' => 'NP4201', 'name' => 'AirPods Pro 2', 'category' => 'Audio', 'capital' => 10500, 'markup' => 23.81, 'stock' => 8],
            ['barcode' => 'NP4202', 'name' => 'Samsung Galaxy Buds FE', 'category' => 'Audio', 'capital' => 3200, 'markup' => 40.63, 'stock' => 10],
            ['barcode' => 'NP4301', 'name' => 'Apple Watch SE 2', 'category' => 'Smart Watches', 'capital' => 13500, 'markup' => 25.93, 'stock' => 5, 'track_serials' => true, 'device_kind' => 'watch', 'warranty_months' => 12],
            ['barcode' => 'NP4302', 'name' => 'Samsung Galaxy Watch6', 'category' => 'Smart Watches', 'capital' => 12500, 'markup' => 24.00, 'stock' => 5, 'track_serials' => true, 'device_kind' => 'watch', 'warranty_months' => 12],

            // Services / repair
            ['barcode' => 'NP9001', 'name' => 'Screen Protector Installation', 'category' => 'Repair Services', 'capital' => 250, 'markup' => 0, 'stock' => 999, 'product_type' => 'service', 'duration_minutes' => 10, 'is_taxable' => false, 'warranty_months' => 0],
            ['barcode' => 'NP9002', 'name' => 'Phone Diagnostic Check', 'category' => 'Repair Services', 'capital' => 500, 'markup' => 0, 'stock' => 999, 'product_type' => 'service', 'duration_minutes' => 30, 'is_taxable' => false, 'warranty_months' => 0],
            ['barcode' => 'NP9003', 'name' => 'Battery Replacement Labor', 'category' => 'Repair Services', 'capital' => 800, 'markup' => 0, 'stock' => 999, 'product_type' => 'service', 'duration_minutes' => 60, 'is_taxable' => false, 'warranty_months' => 1],
            ['barcode' => 'NP9004', 'name' => 'Laptop Cleaning Service', 'category' => 'Repair Services', 'capital' => 1000, 'markup' => 0, 'stock' => 999, 'product_type' => 'service', 'duration_minutes' => 90, 'is_taxable' => false, 'warranty_months' => 0],
        ];

        foreach ($catalog as $index => $data) {
            $product = $makeProduct(array_merge([
                'description' => 'NizPhone Gadgets catalog item with branch stock and warranty workflow.',
                'track_serials' => false,
                'warranty_months' => 12,
                'product_type' => 'standard',
                'is_taxable' => true,
            ], $data));

            $seedDeviceUnits($product, $data, $index + 1);
        }

        $variantSets = [
            'NP1001' => [['256GB / Natural Titanium', 0, 2], ['512GB / Blue Titanium', 12000, 1]],
            'NP1002' => [['128GB / Black Titanium', 0, 2], ['256GB / White Titanium', 7000, 1]],
            'NP1003' => [['128GB / Pink', 0, 2], ['256GB / Black', 6000, 2]],
            'NP1006' => [['128GB / Midnight', 0, 2], ['256GB / Starlight', 5000, 2]],
            'NP1101' => [['256GB / Titanium Gray', 0, 2], ['512GB / Titanium Black', 10000, 1]],
            'NP1103' => [['128GB / Awesome Navy', 0, 2], ['256GB / Awesome Lilac', 3500, 2]],
            'NP1108' => [['256GB / Midnight Black', 0, 2], ['512GB / Moonlight White', 4000, 2]],
            'NP2001' => [['8GB / 256GB / Midnight', 0, 1], ['8GB / 512GB / Starlight', 9000, 1]],
            'NP2004' => [['8GB / 512GB / Silver', 0, 2], ['16GB / 512GB / Blue', 6000, 1]],
            'NP2005' => [['Ryzen 5 / RTX 3050', 0, 1], ['Ryzen 7 / RTX 4060', 18000, 1]],
            'NP3001' => [['64GB / Wi-Fi / Blue', 0, 2], ['256GB / Wi-Fi / Silver', 9000, 1]],
            'NP4201' => [['USB-C Case', 0, 5], ['Lightning Case', -500, 3]],
            'NP4301' => [['40mm / Midnight', 0, 3], ['44mm / Starlight', 2500, 2]],
        ];

        foreach ($variantSets as $barcode => $variants) {
            $product = Product::where('barcode', $barcode)->first();

            if (! $product) {
                continue;
            }

            $variantData = [];
            foreach ($variants as $index => [$name, $extraPrice, $stock]) {
                $variantData[] = [
                    'sku' => $barcode.'-V'.($index + 1),
                    'barcode' => $barcode.'V'.($index + 1),
                    'name' => $name,
                    'attributes' => $this->variantAttributes($name),
                    'extra_price' => $extraPrice,
                    'stock' => $stock,
                    'capital' => (float) ProductStock::where('product_id', $product->id)->where('branch_id', $branch->id)->value('price'),
                    'markup' => 0,
                ];
            }

            $makeVariants($product, $variantData);
        }

        $this->command->info("✓ NizPhone gadget catalog seeded ({$productCount} products, {$variantCount} variants, {$deviceUnitCount} serialized units)");
    }

    private function variantAttributes(string $name): array
    {
        $parts = array_map('trim', explode('/', $name));
        $attributes = [];

        foreach ($parts as $part) {
            if (str_contains($part, 'GB') || str_contains($part, 'TB')) {
                $attributes[] = ['storage_or_memory' => $part];
            } elseif (preg_match('/\d+mm/i', $part)) {
                $attributes[] = ['size' => $part];
            } else {
                $attributes[] = ['color_or_option' => $part];
            }
        }

        return array_merge(...$attributes);
    }
}
