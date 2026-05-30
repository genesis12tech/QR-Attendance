<?php

namespace App\Filament\Admin\Resources\Timetables\Pages;

use App\Filament\Admin\Resources\Timetables\TimetableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTimetable extends EditRecord
{
    protected static string $resource = TimetableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
