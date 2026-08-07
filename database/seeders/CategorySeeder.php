<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'iPhones', 'description' => 'Apple iPhone units with IMEI / serial tracking'],
            ['name' => 'Android Phones', 'description' => 'Samsung, OPPO, vivo, Xiaomi, realme, Huawei and other Android phones'],
            ['name' => 'Laptops', 'description' => 'MacBook, Windows laptops, gaming laptops and ultrabooks'],
            ['name' => 'Tablets', 'description' => 'iPad and Android tablets'],
            ['name' => 'Smart Watches', 'description' => 'Apple Watch, Galaxy Watch and wearable devices'],
            ['name' => 'Audio', 'description' => 'AirPods, earbuds, headphones and speakers'],
            ['name' => 'Chargers & Cables', 'description' => 'Adapters, power banks, USB-C, Lightning and charging accessories'],
            ['name' => 'Cases & Protection', 'description' => 'Phone cases, laptop sleeves, screen protectors and tempered glass'],
            ['name' => 'Repair Parts', 'description' => 'Replacement screens, batteries, cameras and service parts'],
            ['name' => 'Repair Services', 'description' => 'Labor and after-sales service items'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                [
                    'slug' => Str::slug($category['name']),
                    'description' => $category['description'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✓ Gadget categories seeded ('.count($categories).')');
    }
}
