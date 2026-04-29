<?php

namespace App\Enums;

enum TimeSlotEnum: string
{
    case MORNING = 'MORNING';
    case AFTERNOON = 'AFTERNOON';
    case EVENING = 'EVENING';
    case NIGHT = 'NIGHT';
}
