<?php

namespace App\Enums;

enum FacultyStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Inactive = 'inactive';
}
