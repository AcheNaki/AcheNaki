<?php

namespace App\Enums;

enum ElectricityStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case UNAVAILABLE = 'UNAVAILABLE';
    case UNSTABLE = 'UNSTABLE';
}
