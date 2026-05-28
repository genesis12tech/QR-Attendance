<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages;

use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\AdminRoleAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminRoleAssignment extends CreateRecord
{
    protected static string $resource = AdminRoleAssignmentResource::class;
}
