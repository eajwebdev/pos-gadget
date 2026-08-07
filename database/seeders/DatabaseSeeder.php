<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Foundation — no foreign key dependencies
            SupplierSeeder::class,
            CategorySeeder::class,
            ExpenseCategorySeeder::class,

            // 2. Branches — depends on suppliers
            BranchSeeder::class,
            WarehouseSeeder::class,

            // 3. Users — depends on branches
            UserSeeder::class,

            // 4. System settings — global defaults
            SystemSettingSeeder::class,

            // 5. Gadget/retail products — serialized phone inventory
            RetailProductSeeder::class,
        ]);
    }
}
