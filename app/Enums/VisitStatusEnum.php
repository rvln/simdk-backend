<?php

namespace App\Enums;

enum VisitStatusEnum: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case NEEDS_RESCHEDULE = 'NEEDS_RESCHEDULE';
}
