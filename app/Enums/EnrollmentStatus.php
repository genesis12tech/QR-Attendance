<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Dropped = 'dropped';
    case Completed = 'completed';
}
