<?php

namespace App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages;

use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\DataRetentionPolicyResource;
use Filament\Resources\Pages\EditRecord;

class EditDataRetentionPolicy extends EditRecord
{
    protected static string $resource = DataRetentionPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
