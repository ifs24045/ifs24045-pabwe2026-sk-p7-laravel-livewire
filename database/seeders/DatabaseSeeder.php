<?php

namespace Database\Seeders;

use App\Features\Stock\Enums\StockStatus;
use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => 'admin',
                'role' => User::ROLE_ADMIN,
            ]
        );

        $member = User::firstOrCreate(
            ['username' => 'member'],
            [
                'name' => 'Member',
                'email' => 'member@example.com',
                'password' => 'member',
                'role' => User::ROLE_MEMBER,
            ]
        );

        $items = [
            ['name' => 'Beras Premium 5kg', 'sku' => 'BRS-001', 'quantity' => 50, 'unit' => 'karung', 'price' => 68000, 'min_stock' => 10, 'description' => '**Beras premium** kualitas super, kemasan 5 kg.'],
            ['name' => 'Minyak Goreng 2L', 'sku' => 'MYK-001', 'quantity' => 8, 'unit' => 'pcs', 'price' => 38000, 'min_stock' => 10, 'description' => 'Minyak goreng kemasan 2 liter.'],
            ['name' => 'Gula Pasir 1kg', 'sku' => 'GUL-001', 'quantity' => 0, 'unit' => 'kg', 'price' => 17500, 'min_stock' => 5, 'description' => 'Gula pasir putih, *stok sedang kosong*.'],
            ['name' => 'Kopi Bubuk 200g', 'sku' => 'KOP-001', 'quantity' => 25, 'unit' => 'pcs', 'price' => 22000, 'min_stock' => 5, 'description' => null],
            ['name' => 'Teh Celup (isi 25)', 'sku' => 'TEH-001', 'quantity' => 5, 'unit' => 'box', 'price' => 12000, 'min_stock' => 5, 'description' => null],
        ];

        foreach ($items as $item) {
            Stock::firstOrCreate(
                ['user_id' => $member->id, 'sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'min_stock' => $item['min_stock'],
                    'status' => StockStatus::fromQuantity($item['quantity'], $item['min_stock'])->value,
                ]
            );
        }
    }
}