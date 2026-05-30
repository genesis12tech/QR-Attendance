<?php

namespace App\Filament\Faculty\Resources\SessionExports\Pages;

use App\Filament\Faculty\Resources\SessionExports\SessionExportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSessionExport extends EditRecord
{
    protected static string $resource = SessionExportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
