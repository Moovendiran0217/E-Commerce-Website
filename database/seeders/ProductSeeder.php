<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'The Sunday Throw', 'price' => '1890.00', 'stock' => 12],
            ['name' => 'Everyday Stoneware Cup', 'price' => '640.00', 'stock' => 24],
            ['name' => 'Little Ritual Vase', 'price' => '980.00', 'stock' => 8],
            ['name' => 'The Slow Morning Tray', 'price' => '1250.00', 'stock' => 10],
            ['name' => 'Soft Light Table Lamp', 'price' => '3290.00', 'stock' => 6],
            ['name' => 'Gather Round Side Table', 'price' => '5490.00', 'stock' => 4],
            ['name' => 'Sunday Market Tote', 'price' => '790.00', 'stock' => 18],
            ['name' => 'Quiet Hours Candle', 'price' => '560.00', 'stock' => 20],
        ] as $product) {
            Product::query()->firstOrCreate(
                ['name' => $product['name']],
                ['price' => $product['price'], 'stock' => $product['stock']],
            );
        }
    }
}
