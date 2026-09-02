<?php

namespace App\Enums;

enum ElectricityOutageLifecycle: string
{
    case CANDIDATE = 'CANDIDATE';
    case ACTIVE = 'ACTIVE';
    case RESOLVED = 'RESOLVED';
}
