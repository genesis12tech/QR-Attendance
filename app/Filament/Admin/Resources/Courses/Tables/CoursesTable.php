<?php

namespace App\Filament\Admin\Resources\Courses\Tables;

use App\Models\Course;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable(),
                TextColumn::make('semester')
                    ->sortable(),
                TextColumn::make('credits')
                    ->sortable(),
                TextColumn::make('min_attendance_pct')
                    ->label('Min Attendance %')
                    ->sortable(),
                TextColumn::make('enrollments_count')
                    ->label('Enrollments')
                    ->counts('enrollments')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('manageEnrollments')
                    ->label('Enrollments')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->url(fn (Course $record) => '/admin/enrollments?tableFilters%5Bcourse_id%5D%5Bvalue%5D='.$record->id),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No courses found')
            ->emptyStateDescription('Add the first course using the button above.');
    }
}
