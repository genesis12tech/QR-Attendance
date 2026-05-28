<?php

namespace App\Filament\SuperAdmin\Resources\Departments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('code')->sortable()->searchable(),
                TextColumn::make('headFaculty.user.name')
                    ->label('Head of Faculty')
                    ->default('—'),
                TextColumn::make('students_count')
                    ->label('Students')
                    ->state(fn ($record) => $record->students()->count()),
                TextColumn::make('faculty_count')
                    ->label('Faculty')
                    ->state(fn ($record) => $record->faculty()->count()),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->options([1 => 'Active', 0 => 'Inactive'])
                    ->attribute('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No departments yet')
            ->emptyStateDescription('Create the first department using the button above.');
    }
}
