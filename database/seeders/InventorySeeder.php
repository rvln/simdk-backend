<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        Inventory::create([
            'itemName' => 'Beras',
            'stock' => 50,
        ]);

        Inventory::create([
            'itemName' => 'Buku Tulis',
            'stock' => 120,
        ]);

        Inventory::create([
            'itemName' => 'Sepatu Anak',
            'stock' => 15,
        ]);
    }
}
