<?php

namespace App\Filament\Admin\Resources\Enrollments\Tables;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class EnrollmentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.roll_no')
                    ->label('Roll No')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('course.code')
                    ->label('Course')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('classGroup.name')
                    ->label('Group')
                    ->sortable(),
                TextColumn::make('enrolled_at')
                    ->label('Enrolled At')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('drop')
                        ->label('Drop')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(fn (Enrollment $enrollment) => $enrollment->update([
                                'status' => EnrollmentStatus::Dropped,
                            ]));
                            Notification::make()->title('Enrollments dropped')->success()->send();
                        }),
                    BulkAction::make('mark_completed')
                        ->label('Mark Completed')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(fn (Enrollment $enrollment) => $enrollment->update([
                                'status' => EnrollmentStatus::Completed,
                            ]));
                            Notification::make()->title('Enrollments marked as completed')->success()->send();
                        }),
                ]),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No enrollments found')
            ->emptyStateDescription('Create the first enrollment using the button above.');
    }
}
