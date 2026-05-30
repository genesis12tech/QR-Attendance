<?php

namespace App\Filament\Admin\Resources\Enrollments\Schemas;

use App\Enums\EnrollmentStatus;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('student_id')
                ->label('Student')
                ->options(fn () => Student::query()
                    ->with('user')
                    ->get()
                    ->pluck('user.name', 'id'))
                ->searchable()
                ->preload(false)
                ->required()
                ->rules([
                    fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                        $courseId = $get('course_id');
                        if ($value && $courseId) {
                            $exists = Enrollment::query()
                                ->where('student_id', $value)
                                ->where('course_id', $courseId)
                                ->when(
                                    request()->route('record'),
                                    fn ($q) => $q->where('id', '!=', request()->route('record'))
                                )
                                ->exists();
                            if ($exists) {
                                $fail('This student is already enrolled in the selected course.');
                            }
                        }
                    },
                ]),
            Select::make('course_id')
                ->label('Course')
                ->options(fn () => Course::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->live(),
            Select::make('class_group_id')
                ->label('Class Group')
                ->options(fn (Get $get) => ClassGroup::query()
                    ->when($get('course_id'), fn ($q, $id) => $q->where('course_id', $id))
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->required(),
            DatePicker::make('enrolled_at')
                ->required()
                ->default(now()),
            Select::make('status')
                ->options(EnrollmentStatus::class)
                ->default(EnrollmentStatus::Active->value)
                ->required(),
        ]);
    }
}
