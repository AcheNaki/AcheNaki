<?php

namespace App\Enums;

enum LiveStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case UNAVAILABLE = 'UNAVAILABLE';
    case UNSTABLE = 'UNSTABLE';
    case NORMAL = 'NORMAL';
    case LOW = 'LOW';
    case VERY_LOW = 'VERY_LOW';
    case MIXED = 'MIXED';
    case INSUFFICIENT_DATA = 'INSUFFICIENT_DATA';
}
