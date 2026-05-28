<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages;

use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\AdminRoleAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdminRoleAssignments extends ListRecords
{
    protected static string $resource = AdminRoleAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
