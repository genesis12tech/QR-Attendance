<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Graduated = 'graduated';
    case Dropped = 'dropped';
}
