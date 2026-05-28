<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\SystemSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SystemSettingsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'System Settings';

    protected static ?string $slug = 'system-settings';

    protected string $view = 'filament.super-admin.pages.system-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'app_name' => SystemSetting::get('app_name', config('app.name')),
            'qr_rotation_seconds' => SystemSetting::get('qr_rotation_seconds', '30'),
            'max_devices_per_student' => SystemSetting::get('max_devices_per_student', '1'),
            'attendance_window_mins' => SystemSetting::get('attendance_window_mins', '120'),
            'faculty_can_review_flags' => SystemSetting::get('faculty_can_review_flags', 'false') === 'true',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('app_name')
                    ->label('Application Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('qr_rotation_seconds')
                    ->label('QR Rotation Interval (seconds)')
                    ->numeric()
                    ->required()
                    ->minValue(10),
                TextInput::make('max_devices_per_student')
                    ->label('Max Devices per Student')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('attendance_window_mins')
                    ->label('Attendance Window (minutes)')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                Toggle::make('faculty_can_review_flags')
                    ->label('Faculty Can Review Proxy Flags'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SystemSetting::set('app_name', $data['app_name']);
        SystemSetting::set('qr_rotation_seconds', $data['qr_rotation_seconds']);
        SystemSetting::set('max_devices_per_student', $data['max_devices_per_student']);
        SystemSetting::set('attendance_window_mins', $data['attendance_window_mins']);
        SystemSetting::set('faculty_can_review_flags', $data['faculty_can_review_flags'] ? 'true' : 'false');

        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save'),
        ];
    }
}
