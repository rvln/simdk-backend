<?php

namespace App\Enums;

enum InventoryEnum: string
{
    case MAKANAN = 'MAKANAN';
    case PAKAIAN = 'PAKAIAN';
    case PENDIDIKAN = 'PENDIDIKAN';
    case KESEHATAN = 'KESEHATAN';
    case KEBERSIHAN = 'KEBERSIHAN';
    case LAINNYA = 'LAINNYA';
}
