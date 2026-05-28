<?php

namespace App\Filament\SuperAdmin\Resources\Departments\Pages;

use App\Filament\SuperAdmin\Resources\Departments\DepartmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDepartment extends EditRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
