<?php

namespace App\Enums;

enum GasStatus: string
{
    case NORMAL = 'NORMAL';
    case LOW = 'LOW';
    case VERY_LOW = 'VERY_LOW';
    case UNAVAILABLE = 'UNAVAILABLE';
}
