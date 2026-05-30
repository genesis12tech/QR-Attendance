<?php

namespace App\Filament\Faculty\Resources\AttendanceSessions\Pages;

use App\Filament\Faculty\Resources\AttendanceSessions\AttendanceSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSessions extends ListRecords
{
    protected static string $resource = AttendanceSessionResource::class;
}
