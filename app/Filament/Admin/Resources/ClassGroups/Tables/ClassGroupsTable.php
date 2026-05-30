<?php

namespace App\Filament\Admin\Resources\ClassGroups\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('course.code')
                    ->label('Course')
                    ->sortable()
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No class groups found')
            ->emptyStateDescription('Add the first class group using the button above.');
    }
}
