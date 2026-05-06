<?php

namespace App\Enums;

enum ReportStatusEnum: string
{
    case PENDING = 'PENDING';
    case PUBLISHED = 'PUBLISHED';
    case REJECTED = 'REJECTED';
}
