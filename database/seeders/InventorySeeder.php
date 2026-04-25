<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory;
use App\Enums\InventoryEnum;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        Inventory::create([
            'itemName'    => 'Beras',
            'category'    => InventoryEnum::MAKANAN->value,
            'stock'       => 50,
            'target_qty'  => 100,
            'unit'        => 'Kg',
            'description' => 'Beras putih untuk kebutuhan makan harian anak-anak panti.',
        ]);

        Inventory::create([
            'itemName'    => 'Susu Kotak',
            'category'    => InventoryEnum::MAKANAN->value,
            'stock'       => 30,
            'target_qty'  => 80,
            'unit'        => 'Dus',
            'description' => 'Susu UHT kemasan kotak untuk asupan gizi harian.',
        ]);

        Inventory::create([
            'itemName'    => 'Buku Tulis',
            'category'    => InventoryEnum::PENDIDIKAN->value,
            'stock'       => 120,
            'target_qty'  => 200,
            'unit'        => 'pcs',
            'description' => 'Buku tulis 58 lembar untuk keperluan sekolah.',
        ]);

        Inventory::create([
            'itemName'    => 'Pakaian Anak',
            'category'    => InventoryEnum::PAKAIAN->value,
            'stock'       => 15,
            'target_qty'  => 50,
            'unit'        => 'pcs',
            'description' => 'Pakaian layak pakai untuk anak usia 6-12 tahun.',
        ]);

        Inventory::create([
            'itemName'    => 'Sabun Mandi',
            'category'    => InventoryEnum::KEBERSIHAN->value,
            'stock'       => 40,
            'target_qty'  => 60,
            'unit'        => 'pcs',
            'description' => 'Sabun batang untuk kebersihan diri anak-anak.',
        ]);
    }
}
