<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::updateOrCreate(
            ['code' => 'WH-001'],
            [
                'name'      => 'NizPhone Stockroom',
                'address'   => '456 Trade Ave., Uptown',
                'notes'     => 'Main stockroom for receiving, holding, and transferring gadget inventory to store branches.',
                'is_active' => true,
            ]
        );

        $this->command->info('✓ Warehouses seeded/updated (1)');
    }
}
