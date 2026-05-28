<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages;

use App\Filament\SuperAdmin\Resources\SecurityPolicies\SecurityPolicyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSecurityPolicy extends CreateRecord
{
    protected static string $resource = SecurityPolicyResource::class;
}
