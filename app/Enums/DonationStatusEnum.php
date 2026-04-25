<?php

namespace App\Enums;

enum DonationStatusEnum: string
{
    case PENDING = 'PENDING';
    case SUCCESS = 'SUCCESS';
    case FAILED = 'FAILED';
    case EXPIRED = 'EXPIRED';
    case PENDING_DELIVERY = 'PENDING_DELIVERY';
    case REJECTED = 'REJECTED';
}
