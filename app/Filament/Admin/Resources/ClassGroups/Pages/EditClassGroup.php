<?php

namespace App\Filament\Admin\Resources\ClassGroups\Pages;

use App\Filament\Admin\Resources\ClassGroups\ClassGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClassGroup extends EditRecord
{
    protected static string $resource = ClassGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
