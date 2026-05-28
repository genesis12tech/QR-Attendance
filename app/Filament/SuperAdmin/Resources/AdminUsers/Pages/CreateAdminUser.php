<?php

namespace App\Filament\SuperAdmin\Resources\AdminUsers\Pages;

use App\Filament\SuperAdmin\Resources\AdminUsers\AdminUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;
}
