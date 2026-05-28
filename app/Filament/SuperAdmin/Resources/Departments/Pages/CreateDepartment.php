<?php

namespace App\Filament\SuperAdmin\Resources\Departments\Pages;

use App\Filament\SuperAdmin\Resources\Departments\DepartmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;
}
