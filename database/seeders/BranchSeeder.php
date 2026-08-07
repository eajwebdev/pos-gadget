<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * General branch types auto-apply POS feature flags via Branch::booted().
     *
     * IMEI / serial tracking is handled per product unit in Device Units and POS.
     * Table ordering and recipe/BOM features are intentionally disabled for this setup.
     */
    public function run(): void
    {
        $coop = Supplier::where('name', 'COOP')->first();
        $abc  = Supplier::where('name', 'ABC Trading')->first();
        $xyz  = Supplier::where('name', 'XYZ Wholesale')->first();

        $branches = [
            [
                'supplier_id'           => $coop?->id,
                'name'                  => 'NizPhone Main Store',
                'code'                  => 'CMC',
                'address'               => 'Main Building, Ground Floor',
                'phone'                 => '09171234501',
                'contact_person'        => 'Ana Rivera',
                'is_active'             => true,
                'business_type'         => Branch::TYPE_STORE,
                'use_table_ordering'    => false,
                'use_variants'          => true,
                'use_expiry_tracking'   => false,
                'use_recipe_system'     => false,
                'use_bundles'           => true,
            ],
            [
                'supplier_id'           => $coop?->id,
                'name'                  => 'NizPhone Branch Store',
                'code'                  => 'CAN',
                'address'               => 'Annex Building, Room 101',
                'phone'                 => '09171234502',
                'contact_person'        => 'Ben Torres',
                'is_active'             => true,
                'business_type'         => Branch::TYPE_STORE,
                'use_table_ordering'    => false,
                'use_variants'          => true,
                'use_expiry_tracking'   => false,
                'use_recipe_system'     => false,
                'use_bundles'           => true,
            ],
            [
                'supplier_id'           => $abc?->id,
                'name'                  => 'NizPhone Sales Branch',
                'code'                  => 'ABC1',
                'address'               => '123 Commerce St., City Center',
                'phone'                 => '09281234501',
                'contact_person'        => 'Maria Santos',
                'is_active'             => true,
                'business_type'         => Branch::TYPE_STORE,
                'use_table_ordering'    => false,
                'use_variants'          => true,
                'use_expiry_tracking'   => false,
                'use_recipe_system'     => false,
                'use_bundles'           => true,
            ],
            [
                'supplier_id'           => $xyz?->id,
                'name'                  => 'NizPhone Outlet Store',
                'code'                  => 'XYZ1',
                'address'               => '456 Trade Ave., Uptown',
                'phone'                 => '09391234501',
                'contact_person'        => 'Pedro Reyes',
                'is_active'             => true,
                'business_type'         => Branch::TYPE_STORE,
                'use_table_ordering'    => false,
                'use_variants'          => true,
                'use_expiry_tracking'   => false,
                'use_recipe_system'     => false,
                'use_bundles'           => true,
            ],
        ];

        foreach ($branches as $data) {
            if (!$data['supplier_id']) {
                continue;
            }

            Branch::updateOrCreate(['code' => $data['code']], $data);
        }

        Branch::query()
            ->whereIn('business_type', [
                'gadget_store', 'phone_store', 'tablet_store', 'laptop_store', 'accessories_store', 'warehouse',
                'retail', 'grocery', 'sari_sari', 'cafe', 'restaurant', 'food_stall',
                'bar', 'bakery', 'pharmacy', 'salon', 'laundry', 'hardware', 'school', 'mixed',
            ])
            ->update([
                'business_type'       => Branch::TYPE_STORE,
                'use_table_ordering'  => false,
                'use_expiry_tracking' => false,
                'use_recipe_system'   => false,
            ]);

        Branch::query()
            ->where('business_type', 'repair_service')
            ->update([
                'business_type'       => Branch::TYPE_SERVICE_CENTER,
                'use_table_ordering'  => false,
                'use_expiry_tracking' => false,
                'use_recipe_system'   => false,
            ]);

        $this->command->info('✓ General branches seeded/updated (' . count($branches) . ')');
    }
}
