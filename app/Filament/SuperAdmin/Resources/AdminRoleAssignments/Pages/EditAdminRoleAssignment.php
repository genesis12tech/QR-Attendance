<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages;

use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\AdminRoleAssignmentResource;
use Filament\Resources\Pages\EditRecord;

class EditAdminRoleAssignment extends EditRecord
{
    protected static string $resource = AdminRoleAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
