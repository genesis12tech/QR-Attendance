<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages;

use App\Filament\SuperAdmin\Resources\SecurityPolicies\SecurityPolicyResource;
use Filament\Resources\Pages\ListRecords;

class ListSecurityPolicies extends ListRecords
{
    protected static string $resource = SecurityPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
