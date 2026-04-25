<?php

namespace App\Enums;

enum RoleEnum: string
{
    case PENGURUS_PANTI = 'PENGURUS_PANTI';
    case KEPALA_PANTI = 'KEPALA_PANTI';
    case PENGUNJUNG = 'PENGUNJUNG';
}
