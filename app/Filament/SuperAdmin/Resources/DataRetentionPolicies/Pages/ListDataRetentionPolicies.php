<?php

namespace App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages;

use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\DataRetentionPolicyResource;
use Filament\Resources\Pages\ListRecords;

class ListDataRetentionPolicies extends ListRecords
{
    protected static string $resource = DataRetentionPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
