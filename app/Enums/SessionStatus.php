<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Paused = 'paused';
    case Closed = 'closed';
}
