<?php

namespace App\Filament\Admin\Resources\Timetables\Schemas;

use App\Enums\DayOfWeek;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TimetableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('course_id')
                ->label('Course')
                ->options(fn () => Course::query()->orderBy('code')->pluck('code', 'id'))
                ->searchable()
                ->required(),
            Select::make('class_group_id')
                ->label('Class Group')
                ->options(fn () => ClassGroup::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Select::make('faculty_id')
                ->label('Faculty')
                ->options(fn () => Faculty::query()->with('user')->get()->pluck('user.name', 'id'))
                ->searchable()
                ->required(),
            Select::make('room_id')
                ->label('Room')
                ->options(fn () => Room::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Select::make('day_of_week')
                ->label('Day of Week')
                ->options(DayOfWeek::class)
                ->required(),
            TimePicker::make('start_time')
                ->label('Start Time')
                ->seconds(false)
                ->required(),
            TimePicker::make('end_time')
                ->label('End Time')
                ->seconds(false)
                ->required()
                ->rules([
                    fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                        $startTime = $get('start_time');
                        if ($value && $startTime && $value <= $startTime) {
                            $fail('End time must be after start time.');
                        }
                    },
                ]),
            DatePicker::make('effective_from')
                ->label('Effective From')
                ->required(),
            DatePicker::make('effective_until')
                ->label('Effective Until'),
        ]);
    }
}
