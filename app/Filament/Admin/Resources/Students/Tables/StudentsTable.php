<?php

namespace App\Filament\Admin\Resources\Students\Tables;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('roll_no')
                    ->label('Roll No')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable(),
                TextColumn::make('batch_year')
                    ->label('Batch')
                    ->sortable(),
                TextColumn::make('section')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('status')
                    ->options(StudentStatus::class),
                SelectFilter::make('batch_year')
                    ->label('Batch Year')
                    ->options(fn () => Student::query()
                        ->distinct()
                        ->orderByDesc('batch_year')
                        ->pluck('batch_year', 'batch_year')
                        ->toArray()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewAttendance')
                    ->label('Attendance')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->url(fn (Student $record) => '/admin/attendance-records?tableFilters%5Bstudent_id%5D%5Bvalue%5D='.$record->id),
                Action::make('viewEnrollments')
                    ->label('Enrollments')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->url(fn (Student $record) => '/admin/enrollments?tableFilters%5Bstudent_id%5D%5Bvalue%5D='.$record->id),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export_csv')
                        ->label('Export CSV')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->action(function (Collection $records) {
                            $handle = fopen('php://temp', 'r+');
                            fputcsv($handle, ['Roll No', 'Name', 'Department', 'Batch Year', 'Section', 'Status']);
                            foreach ($records as $record) {
                                /** @var Student $record */
                                fputcsv($handle, [
                                    $record->roll_no,
                                    $record->user?->name ?? '—',
                                    $record->department?->name ?? '—',
                                    $record->batch_year,
                                    $record->section,
                                    $record->status->value,
                                ]);
                            }
                            rewind($handle);
                            $csv = stream_get_contents($handle);
                            fclose($handle);

                            return response()->streamDownload(function () use ($csv) {
                                echo $csv;
                            }, 'students.csv');
                        }),
                    BulkAction::make('enroll_in_course')
                        ->label('Enrol in Course')
                        ->icon(Heroicon::OutlinedAcademicCap)
                        ->schema([
                            Select::make('course_id')
                                ->label('Course')
                                ->options(fn () => Course::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->live(),
                            Select::make('class_group_id')
                                ->label('Class Group')
                                ->options(fn (Get $get) => ClassGroup::query()
                                    ->where('course_id', $get('course_id'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $student) {
                                Enrollment::firstOrCreate(
                                    [
                                        'student_id' => $student->id,
                                        'course_id' => $data['course_id'],
                                    ],
                                    [
                                        'class_group_id' => $data['class_group_id'],
                                        'status' => EnrollmentStatus::Active,
                                        'enrolled_at' => now(),
                                    ]
                                );
                            }
                            Notification::make()->title('Students enrolled successfully')->success()->send();
                        }),
                ]),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No students found')
            ->emptyStateDescription('Add the first student using the button above.');
    }
}
