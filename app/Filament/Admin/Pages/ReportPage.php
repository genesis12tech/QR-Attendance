<?php

namespace App\Filament\Admin\Pages;

use App\Enums\ExportFormat;
use App\Jobs\GenerateAttendanceReport;
use App\Models\Course;
use App\Models\Department;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ReportPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Generate Report';

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.admin.pages.report-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Report Type')
                    ->options([
                        'department' => 'Department',
                        'course' => 'Course',
                        'faculty' => 'Faculty',
                        'student' => 'Student',
                        'date_range' => 'Date Range',
                    ])
                    ->required(),
                Select::make('department_id')
                    ->label('Department')
                    ->options(fn () => Department::pluck('name', 'id'))
                    ->searchable(),
                Select::make('course_id')
                    ->label('Course')
                    ->options(fn () => Course::pluck('code', 'id'))
                    ->searchable(),
                DatePicker::make('from')
                    ->label('From')
                    ->required(),
                DatePicker::make('to')
                    ->label('To')
                    ->required(),
                Select::make('format')
                    ->label('Format')
                    ->options(ExportFormat::class)
                    ->default(ExportFormat::Pdf->value)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $data = $this->form->getState();

        $format = $data['format'] instanceof ExportFormat
            ? $data['format']->value
            : $data['format'];

        GenerateAttendanceReport::dispatch(
            type: $data['type'],
            departmentId: $data['department_id'] ?? null,
            courseId: $data['course_id'] ?? null,
            from: $data['from'],
            to: $data['to'],
            format: $format,
            requestedBy: auth()->id(),
        );

        Notification::make()
            ->title('Report queued')
            ->body('Your report is being generated and will be available for download shortly.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_report')
                ->label('Generate Report')
                ->action('generateReport'),
        ];
    }
}
